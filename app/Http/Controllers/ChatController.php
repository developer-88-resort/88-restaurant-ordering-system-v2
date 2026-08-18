<?php

namespace App\Http\Controllers;

use App\Events\ChatInboxUpdated;
use App\Events\MessageEdited;
use App\Events\MessageReactionUpdated;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Chat/Index', [
            'conversations' => $this->conversationsFor($request->user()),
            'directory' => $this->directoryFor($request->user()),
            'onlineUserIds' => $this->onlineUserIds(),
            'activeConversation' => null,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorizeParticipant($request->user(), $conversation);

        if ($this->shouldMarkRead($request)) {
            $this->markThreadRead($conversation, $request->user());
        }

        $latest = $conversation->messages()->with(['sender', 'reactions', 'replyTo.sender'])->latest('id')->limit(30)->get()->reverse()->values();

        return Inertia::render('Chat/Index', [
            'conversations' => $this->conversationsFor($request->user()),
            'directory' => $this->directoryFor($request->user()),
            'onlineUserIds' => $this->onlineUserIds(),
            'activeConversation' => [
                'id' => $conversation->id,
                'other_user' => $this->userSummary($conversation->otherParticipant($request->user())),
                'messages' => $latest->map(fn ($m) => $this->formatMessage($m, $request->user()))->all(),
                'has_more' => $conversation->messages()->where('id', '<', $latest->first()->id ?? 0)->exists(),
            ],
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::where('is_active', true)->findOrFail($data['user_id']);
        $conversation = Conversation::between($request->user(), $target);

        return redirect()->route('chat.show', $conversation);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);

        $query = $conversation->messages()->with(['sender', 'reactions', 'replyTo.sender'])->orderByDesc('id')->limit(30);

        if ($before = $request->integer('before')) {
            $query->where('id', '<', $before);
        }

        $page = $query->get()->reverse()->values();

        return response()->json([
            'messages' => $page->map(fn ($m) => $this->formatMessage($m, $request->user())),
            'has_more' => $conversation->messages()->where('id', '<', $page->first()->id ?? 0)->exists(),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);

        $data = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:4000'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:messages,id'],
            // Allowlist covers photos and common office/business documents
            // while excluding executables/scripts (.exe, .js, .html, .svg)
            // and anything else not on the list — closes off the obvious
            // file-upload security risk without needing extra checks.
            'attachment' => [
                'required_without:body', 'nullable', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
            ],
        ]);

        if (! empty($data['reply_to_message_id'])) {
            // Prevents quoting a message from a conversation the sender
            // isn't even part of — same shape as react()'s existing check.
            abort_unless(
                Message::where('id', $data['reply_to_message_id'])->where('conversation_id', $conversation->id)->exists(),
                404,
            );
        }

        $recipient = $conversation->otherParticipant($request->user());

        $attachment = $request->file('attachment');
        $attachmentPath = $attachment?->store('chat-attachments', 'public');

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
            'body' => $data['body'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachment?->getClientOriginalName(),
            // Server-detected mime, not the client-supplied one — used to
            // decide image-thumbnail vs file-card rendering on the frontend.
            'attachment_mime' => $attachment?->getMimeType(),
            'attachment_size' => $attachment?->getSize(),
            // If the recipient is already online, count it delivered right
            // away; otherwise this stays null until they open the thread,
            // where markThreadRead() fills it in alongside read_at.
            'delivered_at' => $this->isUserOnline($recipient->id) ? now() : null,
        ]);
        $message->load(['sender', 'reactions', 'replyTo.sender']);
        $conversation->touch();

        broadcast(new MessageSent($message));

        broadcast(new ChatInboxUpdated(
            recipientId: $recipient->id,
            conversationId: $conversation->id,
            senderName: $request->user()->name,
            preview: ($data['body'] ?? null) ? Str::limit($data['body'], 80) : '📎 '.$message->attachment_name,
        ));

        return response()->json(['message' => $this->formatMessage($message, $request->user())], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);
        $this->markThreadRead($conversation, $request->user());

        return response()->json(['unread_count' => Message::unreadCountForUser($request->user()->id)]);
    }

    public function updateMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        // Only the original sender may edit their own message.
        abort_unless($message->sender_id === $request->user()->id, 403);

        // An attachment-only message may be edited down to no caption, but
        // a text-only message can't be edited into nothing.
        $data = $request->validate([
            'body' => [$message->attachment_path ? 'nullable' : 'required', 'string', 'max:4000'],
        ]);

        $message->update(['body' => $data['body'] ?? null, 'edited_at' => now()]);
        $message->load(['sender', 'reactions', 'replyTo.sender']);

        broadcast(new MessageEdited($conversation->id, $message->id, $message->body, $message->edited_at->toIso8601String()));

        return response()->json(['message' => $this->formatMessage($message, $request->user())]);
    }

    public function react(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:8'],
        ]);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing && $existing->emoji === $data['emoji']) {
            // Tapping the same emoji again removes it — a toggle, not just
            // an upsert.
            $existing->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $request->user()->id],
                ['emoji' => $data['emoji']],
            );
        }

        $message->load('reactions');

        // The broadcast payload is viewer-agnostic (no reacted_by_me — that
        // flag only makes sense from one specific person's perspective, and
        // this goes to everyone viewing the thread) — each receiving client
        // computes its own by checking whether its id is in user_ids.
        $reactions = array_map(
            fn (array $group) => array_diff_key($group, ['reacted_by_me' => null]),
            $message->reactionSummary($request->user()->id),
        );

        broadcast(new MessageReactionUpdated($conversation->id, $message->id, $reactions));

        return response()->json(['reactions' => $message->reactionSummary($request->user()->id)]);
    }

    private function authorizeParticipant(User $user, Conversation $conversation): void
    {
        abort_unless($conversation->hasParticipant($user), 403);
    }

    /**
     * A full page load always means the user is genuinely opening this
     * thread. But this same route also answers Inertia PARTIAL reloads
     * fired by background pings the Chat page listens for — e.g. the
     * online-status effect's `router.reload({ only: ['onlineUserIds'] })`
     * whenever ANYONE anywhere logs in/out — and Laravel runs the full
     * controller body for those too, only trimming the response payload
     * afterward. Without this guard, those pings were silently marking
     * every message "read" within seconds of being sent, regardless of
     * whether the recipient had actually looked at the thread. Only mark
     * read on a full load, or a partial reload that explicitly asks for
     * activeConversation again.
     *
     * Real root cause of the premature-"Seen" bug: Turbo Drive (loaded
     * globally, see resources/js/app.jsx) prefetches any <a href> on hover
     * by default — including the conversation-list links, which are plain
     * <a> tags under Inertia's <Link> — sending a genuine GET to this exact
     * route just from a mouse hovering over a row, no click required. Fixed
     * at the source with data-turbo-prefetch="false" on those links (see
     * SidebarLink.jsx, sidebar-link.blade.php, Chat/Index.jsx), but this
     * header check is kept as defense-in-depth against any future link
     * that forgets that attribute, or any other speculative-prefetch
     * mechanism (Turbo sends X-Sec-Purpose; the emerging web standard is
     * the unprefixed Sec-Purpose).
     */
    private function shouldMarkRead(Request $request): bool
    {
        if ($request->header('X-Sec-Purpose') === 'prefetch' || $request->header('Sec-Purpose') === 'prefetch') {
            return false;
        }

        $partialKeys = $request->header('X-Inertia-Partial-Data');

        return ! $partialKeys || in_array('activeConversation', explode(',', $partialKeys), true);
    }

    private function markThreadRead(Conversation $conversation, User $user): void
    {
        $unreadIds = $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->pluck('id');

        if ($unreadIds->isEmpty()) {
            return;
        }

        $now = now();
        // A message can't be "seen" without having been "delivered" first —
        // this backfills delivered_at for the (rarer) case the recipient
        // was offline when it was sent and only just opened the thread.
        $conversation->messages()->whereIn('id', $unreadIds)->whereNull('delivered_at')->update(['delivered_at' => $now]);
        $conversation->messages()->whereIn('id', $unreadIds)->update(['read_at' => $now]);

        broadcast(new MessageStatusUpdated($conversation->id, $unreadIds->all(), $now->toIso8601String()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function conversationsFor(User $user): array
    {
        return Conversation::query()
            ->where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)])
            ->get()
            ->sortByDesc(fn (Conversation $c) => $c->latestMessage?->created_at ?? $c->created_at)
            ->values()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'other_user' => $this->userSummary($c->otherParticipant($user)),
                'last_message' => $c->latestMessage ? [
                    'body' => Str::limit($c->latestMessage->body, 80),
                    'sender_id' => $c->latestMessage->sender_id,
                    'created_at' => $c->latestMessage->created_at->toIso8601String(),
                ] : null,
                'unread_count' => $c->unread_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directoryFor(User $user): array
    {
        return User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderByRaw("CASE role WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 WHEN 'staff' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->userSummary($u))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'avatar_url' => $user->avatarUrl(),
            'initials' => $user->initials(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function onlineUserIds(): array
    {
        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    private function isUserOnline(int $userId): bool
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(Message $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(),
            'reactions' => $message->reactionSummary($viewer->id),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'body' => Str::limit($message->replyTo->body, 80),
                'sender_name' => $message->replyTo->sender->name,
            ] : null,
            'attachment' => $message->attachment_path ? [
                'url' => $message->attachmentUrl(),
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => $message->attachment_size,
            ] : null,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'avatar_url' => $message->sender->avatarUrl(),
                'initials' => $message->sender->initials(),
            ],
        ];
    }
}
