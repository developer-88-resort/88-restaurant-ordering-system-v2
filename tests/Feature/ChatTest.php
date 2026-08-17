<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\ChatInboxUpdated;
use App\Events\MessageEdited;
use App\Events\MessageReactionUpdated;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_chat_is_idempotent_regardless_of_who_initiates(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $first = $this->actingAs($a)->post(route('chat.start'), ['user_id' => $b->id]);
        $second = $this->actingAs($a)->post(route('chat.start'), ['user_id' => $b->id]);

        $this->assertSame(1, Conversation::count());
        $conversation = Conversation::first();
        $first->assertRedirect(route('chat.show', $conversation));
        $second->assertRedirect(route('chat.show', $conversation));

        // Starting it from the other side must resolve to the exact same row.
        $this->actingAs($b)->post(route('chat.start'), ['user_id' => $a->id]);
        $this->assertSame(1, Conversation::count());

        $this->assertLessThan($conversation->user_two_id, $conversation->user_one_id);
    }

    public function test_sending_a_message_persists_it_and_broadcasts_both_events(): void
    {
        Event::fake([MessageSent::class, ChatInboxUpdated::class]);

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $response = $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'hello there']);

        $response->assertCreated();
        $this->assertSame(1, Message::count());
        $message = Message::first();
        $this->assertSame('hello there', $message->body);
        $this->assertSame($a->id, $message->sender_id);

        Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message->id === $message->id);
        Event::assertDispatched(ChatInboxUpdated::class, fn (ChatInboxUpdated $e) => $e->recipientId === $b->id
            && $e->conversationId === $conversation->id);
    }

    public function test_a_non_participant_cannot_read_or_send_into_a_conversation(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $outsider = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($outsider)->get(route('chat.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('chat.messages.store', $conversation), ['body' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->getJson(route('chat.messages', $conversation))->assertForbidden();
    }

    public function test_unread_count_increments_and_clears_on_read(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'first']);
        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'second']);

        $this->assertSame(2, Message::unreadCountForUser($b->id));
        $this->assertSame(0, Message::unreadCountForUser($a->id));

        $this->actingAs($b)->get(route('chat.show', $conversation));

        $this->assertSame(0, Message::unreadCountForUser($b->id));
    }

    public function test_unread_count_for_conversation_is_scoped_to_that_conversation_only(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $c = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $convoAB = Conversation::between($a, $b);
        $convoCB = Conversation::between($c, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $convoAB), ['body' => 'one']);
        $this->actingAs($a)->postJson(route('chat.messages.store', $convoAB), ['body' => 'two']);
        $this->actingAs($c)->postJson(route('chat.messages.store', $convoCB), ['body' => 'three']);

        // b has 3 unread total, but only 2 of them belong to the a<->b thread —
        // this is the count the conversation-list row must show for that row,
        // not the global sidebar-badge total.
        $this->assertSame(3, Message::unreadCountForUser($b->id));
        $this->assertSame(2, Message::unreadCountForConversation($convoAB->id, $b->id));
        $this->assertSame(1, Message::unreadCountForConversation($convoCB->id, $b->id));

        $this->actingAs($b)->get(route('chat.show', $convoAB));

        $this->assertSame(0, Message::unreadCountForConversation($convoAB->id, $b->id));
        $this->assertSame(1, Message::unreadCountForConversation($convoCB->id, $b->id));
        $this->assertSame(1, Message::unreadCountForUser($b->id));
    }

    public function test_message_status_progresses_from_sent_to_delivered_to_seen(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        // b is offline — the message should land as "Sent" only.
        $response = $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'are you there']);
        $messageId = $response->json('message.id');
        $this->assertNull(Message::find($messageId)->delivered_at);
        $this->assertNull(Message::find($messageId)->read_at);

        // b comes online (an active session row) — a message sent now should
        // be immediately "Delivered".
        DB::table('sessions')->insert([
            'id' => 'sess-'.$b->id,
            'user_id' => $b->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'ping']);
        $deliveredMessageId = $response->json('message.id');
        $this->assertNotNull(Message::find($deliveredMessageId)->delivered_at);
        $this->assertNull(Message::find($deliveredMessageId)->read_at);

        // b opens the thread — both messages become "Seen", including the
        // one that was never marked delivered, and the sender is notified.
        Event::fake([MessageStatusUpdated::class]);
        $this->actingAs($b)->get(route('chat.show', $conversation));

        $this->assertNotNull(Message::find($messageId)->fresh()->delivered_at);
        $this->assertNotNull(Message::find($messageId)->fresh()->read_at);
        $this->assertNotNull(Message::find($deliveredMessageId)->fresh()->read_at);

        Event::assertDispatched(MessageStatusUpdated::class, fn (MessageStatusUpdated $e) => $e->conversationId === $conversation->id
            && in_array($messageId, $e->messageIds, true)
            && in_array($deliveredMessageId, $e->messageIds, true));
    }

    public function test_a_partial_reload_for_an_unrelated_prop_does_not_mark_messages_read(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'are you there']);
        $this->assertSame(1, Message::unreadCountForUser($b->id));

        // The Chat page's online-status effect does exactly this kind of
        // partial reload in the background whenever ANY user anywhere logs
        // in or out — it must not silently mark b's unread messages read.
        $this->actingAs($b)->get(route('chat.show', $conversation), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Data' => 'onlineUserIds',
            'X-Inertia-Partial-Component' => 'Chat/Index',
        ]);

        $this->assertSame(1, Message::unreadCountForUser($b->id));

        // A genuine full load (no partial header) still marks it read.
        $this->actingAs($b)->get(route('chat.show', $conversation));
        $this->assertSame(0, Message::unreadCountForUser($b->id));
    }

    public function test_a_speculative_prefetch_request_does_not_mark_messages_read(): void
    {
        // The real root cause of the "Seen before actually opened" bug:
        // Turbo Drive (loaded globally for the Blade<->Inertia hybrid nav)
        // prefetches any <a href> on hover by default, sending a genuine GET
        // to this exact route just from a mouse hovering a conversation row.
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), ['body' => 'are you there']);
        $this->assertSame(1, Message::unreadCountForUser($b->id));

        $this->actingAs($b)->get(route('chat.show', $conversation), ['X-Sec-Purpose' => 'prefetch']);
        $this->assertSame(1, Message::unreadCountForUser($b->id));

        $this->actingAs($b)->get(route('chat.show', $conversation), ['Sec-Purpose' => 'prefetch']);
        $this->assertSame(1, Message::unreadCountForUser($b->id));

        $this->actingAs($b)->get(route('chat.show', $conversation));
        $this->assertSame(0, Message::unreadCountForUser($b->id));
    }

    public function test_inactive_users_are_excluded_from_the_directory_and_cannot_be_started(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $inactive = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

        $response = $this->actingAs($a)->get(route('chat.index'));
        $response->assertOk();

        preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
        $page = json_decode(htmlspecialchars_decode($matches[1]), true);
        $directoryIds = collect($page['props']['directory'])->pluck('id');

        $this->assertNotContains($inactive->id, $directoryIds);
        $this->actingAs($a)->post(route('chat.start'), ['user_id' => $inactive->id])->assertNotFound();
    }

    public function test_reacting_to_a_message_adds_it_and_broadcasts(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $response = $this->actingAs($b)->postJson(
            route('chat.messages.react', [$conversation, $message]),
            ['emoji' => '❤️'],
        );

        $response->assertOk();
        $this->assertSame(1, MessageReaction::count());
        $this->assertSame('❤️', $response->json('reactions.0.emoji'));
        $this->assertSame(1, $response->json('reactions.0.count'));
        $this->assertTrue($response->json('reactions.0.reacted_by_me'));

        Event::assertDispatched(MessageReactionUpdated::class, fn (MessageReactionUpdated $e) => $e->messageId === $message->id
            && $e->conversationId === $conversation->id
            && $e->reactions[0]['emoji'] === '❤️'
            && ! array_key_exists('reacted_by_me', $e->reactions[0]));
    }

    public function test_reacting_with_the_same_emoji_twice_removes_it(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($b)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '👍']);
        $this->assertSame(1, MessageReaction::count());

        $response = $this->actingAs($b)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '👍']);

        $this->assertSame(0, MessageReaction::count());
        $this->assertSame([], $response->json('reactions'));
    }

    public function test_reacting_with_a_different_emoji_replaces_the_first(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($b)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '👍']);
        $this->actingAs($b)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '😂']);

        $this->assertSame(1, MessageReaction::count());
        $this->assertSame('😂', MessageReaction::first()->emoji);
    }

    public function test_two_participants_reacting_with_the_same_emoji_share_one_pill_with_count_two(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($a)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '🔥']);
        $response = $this->actingAs($b)->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '🔥']);

        $this->assertSame(2, MessageReaction::count());
        $this->assertSame(1, count($response->json('reactions')));
        $this->assertSame(2, $response->json('reactions.0.count'));
    }

    public function test_a_non_participant_cannot_react_to_a_message(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $outsider = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($outsider)
            ->postJson(route('chat.messages.react', [$conversation, $message]), ['emoji' => '👍'])
            ->assertForbidden();

        $this->assertSame(0, MessageReaction::count());
    }

    public function test_reacting_to_a_message_from_a_mismatched_conversation_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $c = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $convoAB = Conversation::between($a, $b);
        $convoAC = Conversation::between($a, $c);
        $messageInAC = $convoAC->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        // b is a genuine participant of convoAB, but tries to react to a
        // message that actually belongs to a different conversation (AC).
        $this->actingAs($b)
            ->postJson(route('chat.messages.react', [$convoAB, $messageInAC]), ['emoji' => '👍'])
            ->assertNotFound();

        $this->assertSame(0, MessageReaction::count());
    }

    public function test_sending_a_reply_links_it_to_the_original_message_and_broadcasts_the_quote(): void
    {
        Event::fake([MessageSent::class]);

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $original = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'what time do we open']);

        $response = $this->actingAs($b)->postJson(
            route('chat.messages.store', $conversation),
            ['body' => '9am', 'reply_to_message_id' => $original->id],
        );

        $response->assertCreated();
        $reply = Message::find($response->json('message.id'));
        $this->assertSame($original->id, $reply->reply_to_message_id);
        $this->assertSame($original->id, $response->json('message.reply_to.id'));
        $this->assertSame('what time do we open', $response->json('message.reply_to.body'));
        $this->assertSame($a->name, $response->json('message.reply_to.sender_name'));

        Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message->id === $reply->id
            && $e->message->replyTo->id === $original->id);
    }

    public function test_replying_to_a_message_from_a_different_conversation_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $c = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $convoAB = Conversation::between($a, $b);
        $convoAC = Conversation::between($a, $c);
        $messageInAC = $convoAC->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($a)
            ->postJson(route('chat.messages.store', $convoAB), ['body' => 'x', 'reply_to_message_id' => $messageInAC->id])
            ->assertNotFound();

        $this->assertSame(1, Message::count());
    }

    public function test_replying_to_a_nonexistent_message_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($a)
            ->postJson(route('chat.messages.store', $conversation), ['body' => 'x', 'reply_to_message_id' => 999999])
            ->assertUnprocessable();

        $this->assertSame(0, Message::count());
    }

    public function test_sending_a_file_only_message_succeeds_and_carries_the_attachment(): void
    {
        Storage::fake('public');

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $file = UploadedFile::fake()->create('daily-report.pdf', 100, 'application/pdf');

        $response = $this->actingAs($a)->post(route('chat.messages.store', $conversation), ['attachment' => $file]);

        $response->assertCreated();
        $message = Message::first();
        $this->assertNull($message->body);
        $this->assertNotNull($message->attachment_path);
        $this->assertSame('daily-report.pdf', $response->json('message.attachment.name'));
        $this->assertNotNull($response->json('message.attachment.url'));
        Storage::disk('public')->assertExists($message->attachment_path);
    }

    public function test_sending_with_neither_body_nor_attachment_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);

        $this->actingAs($a)->postJson(route('chat.messages.store', $conversation), [])->assertUnprocessable();
        $this->assertSame(0, Message::count());
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('public');

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $this->actingAs($a)
            ->post(route('chat.messages.store', $conversation), ['attachment' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame(0, Message::count());
    }

    public function test_the_inbox_preview_falls_back_to_the_filename_for_a_file_only_message(): void
    {
        Storage::fake('public');
        Event::fake([ChatInboxUpdated::class]);

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $file = UploadedFile::fake()->image('table-issue.jpg');

        $this->actingAs($a)->post(route('chat.messages.store', $conversation), ['attachment' => $file]);

        Event::assertDispatched(ChatInboxUpdated::class, fn (ChatInboxUpdated $e) => $e->preview === '📎 table-issue.jpg');
    }

    public function test_editing_your_own_message_updates_it_and_broadcasts(): void
    {
        Event::fake([MessageEdited::class]);

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'quarter to 3']);

        $response = $this->actingAs($a)->patchJson(
            route('chat.messages.update', [$conversation, $message]),
            ['body' => 'half past 3'],
        );

        $response->assertOk();
        $message->refresh();
        $this->assertSame('half past 3', $message->body);
        $this->assertNotNull($message->edited_at);
        $this->assertSame('half past 3', $response->json('message.body'));
        $this->assertNotNull($response->json('message.edited_at'));

        Event::assertDispatched(MessageEdited::class, fn (MessageEdited $e) => $e->messageId === $message->id
            && $e->body === 'half past 3');
    }

    public function test_editing_someone_elses_message_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'original']);

        $this->actingAs($b)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), ['body' => 'hijacked'])
            ->assertForbidden();

        $this->assertSame('original', $message->fresh()->body);
    }

    public function test_editing_a_text_only_message_to_an_empty_body_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'body' => 'original']);

        $this->actingAs($a)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), ['body' => ''])
            ->assertUnprocessable();

        $this->assertSame('original', $message->fresh()->body);
        $this->assertNull($message->fresh()->edited_at);
    }

    public function test_editing_an_attachment_messages_caption_down_to_empty_is_allowed(): void
    {
        Storage::fake('public');

        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $conversation = Conversation::between($a, $b);
        $message = $conversation->messages()->create([
            'sender_id' => $a->id,
            'body' => 'here is the report',
            'attachment_path' => 'chat-attachments/fake.pdf',
            'attachment_name' => 'report.pdf',
        ]);

        $this->actingAs($a)
            ->patchJson(route('chat.messages.update', [$conversation, $message]), ['body' => ''])
            ->assertOk();

        $this->assertNull($message->fresh()->body);
        $this->assertNotNull($message->fresh()->edited_at);
    }

    public function test_editing_a_message_from_a_mismatched_conversation_is_rejected(): void
    {
        $a = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $b = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $c = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);
        $convoAB = Conversation::between($a, $b);
        $convoAC = Conversation::between($a, $c);
        $messageInAC = $convoAC->messages()->create(['sender_id' => $a->id, 'body' => 'hi']);

        $this->actingAs($a)
            ->patchJson(route('chat.messages.update', [$convoAB, $messageInAC]), ['body' => 'edited'])
            ->assertNotFound();

        $this->assertSame('hi', $messageInAC->fresh()->body);
    }
}
