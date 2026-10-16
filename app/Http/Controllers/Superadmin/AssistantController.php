<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\Department;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantTools;
use App\Services\Assistant\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mochi, the floating assistant of a Superadmin or Admin
 * (resources/js/lib/assistant-widget.jsx), answered by Gemini with the
 * system's own data (AssistantTools, limited per role by allowedFor()).
 *
 * The browser keeps the conversation and sends it each time. Within one
 * question the model may look things up several times; an action it wants
 * is only proposed (a confirm card), and runs through confirm() once the
 * Superadmin presses Confirm.
 */
class AssistantController extends Controller
{
    protected const MAX_STEPS = 6;

    /** What a chat attachment may be (checked on the bytes, not the name). */
    protected const ATTACHMENT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    protected const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    public function chat(Request $request, GeminiClient $gemini): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'in:user,model'],
            'messages.*.text' => ['required', 'string', 'max:4000'],
            // A photo or PDF for the latest message, base64 in the JSON body
            // (production's upload_max_filesize is 2 MB; photos are shrunk
            // in the browser first). Only passed on to Gemini, never stored.
            'attachment' => ['nullable', 'array'],
            'attachment.name' => ['required_with:attachment', 'string', 'max:200'],
            'attachment.data' => ['required_with:attachment', 'string', 'max:'.(int) ceil(self::MAX_ATTACHMENT_BYTES * 4 / 3) + 4],
        ]);

        $contents = collect($data['messages'])
            ->map(fn ($message) => ['role' => $message['role'], 'parts' => [['text' => $message['text']]]])
            ->values()
            ->all();

        if (! empty($data['attachment'])) {
            $file = base64_decode($data['attachment']['data'], true);
            $mime = $file === false ? null : (new \finfo(FILEINFO_MIME_TYPE))->buffer($file);
            if (! in_array($mime, self::ATTACHMENT_TYPES, true) || strlen($file) > self::MAX_ATTACHMENT_BYTES) {
                return response()->json(['error' => __('Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.')], 422);
            }
            $last = array_key_last($contents);
            array_unshift($contents[$last]['parts'], ['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($file)]]);
        }

        $pending = [];
        $links = [];
        $allowed = AssistantTools::allowedFor($request->user());
        $declarations = AssistantTools::declarationsFor($request->user());

        try {
            for ($step = 0; $step < self::MAX_STEPS; $step++) {
                $turn = $gemini->generate($contents, $declarations, $this->instructions($request));
                $contents[] = ['role' => 'model', 'parts' => $turn['parts']];

                $calls = collect($turn['parts'])->pluck('functionCall')->filter()->values();
                if ($calls->isEmpty()) {
                    $reply = trim(collect($turn['parts'])->pluck('text')->filter()->implode("\n"));

                    return response()->json(['reply' => $reply ?: __('Sorry, I could not answer that.'), 'pending' => $pending, 'links' => $this->buttons($links)]);
                }

                $responses = [];
                foreach ($calls as $call) {
                    $name = (string) ($call['name'] ?? '');
                    $args = (array) ($call['args'] ?? []);

                    if (! in_array($name, $allowed, true)) {
                        $result = ['error' => 'This user\'s role cannot see or change this. Say it is for a Superadmin.'];
                    } elseif (in_array($name, AssistantTools::ACTIONS, true)) {
                        $prepared = AssistantTools::prepare($name, $args);
                        if (isset($prepared['error'])) {
                            $result = $prepared;
                        } else {
                            $token = (string) Str::uuid();
                            $request->session()->put("assistant.pending.$token", ['name' => $name, 'args' => $prepared['args'], 'summary' => $prepared['summary']]);
                            $pending[] = ['token' => $token, 'summary' => $prepared['summary']];
                            $result = ['status' => 'waiting_for_the_user_to_press_confirm', 'summary' => $prepared['summary']];
                        }
                    } else {
                        $result = AssistantTools::lookup($name, $args);
                        // Buttons for the answer; the model doesn't need the URLs.
                        array_push($links, ...array_filter($result['_links'] ?? []));
                        unset($result['_links']);
                    }

                    $responses[] = ['functionResponse' => ['name' => $name, 'response' => ['result' => $result]]];
                }
                $contents[] = ['role' => 'user', 'parts' => $responses];
            }

            return response()->json(['reply' => __('That needed too many steps. Please ask in a simpler way.'), 'pending' => $pending, 'links' => $this->buttons($links)]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => __('The assistant is not available right now. Please try again.')], 502);
        }
    }

    /** The page buttons under an answer: each page once, at most four. */
    protected function buttons(array $links): array
    {
        return collect($links)->unique('url')->take(4)->values()->all();
    }

    /**
     * Mochi's reminders for the speech bubble (AssistantTools::alertsFor).
     * Polled by the widget; EnforceIdleTimeout doesn't count it as activity.
     */
    public function alerts(Request $request): JsonResponse
    {
        return response()->json(['alerts' => AssistantTools::alertsFor($request->user())]);
    }

    /** Runs (or drops) an action the user confirmed in the chat. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'decision' => ['required', 'in:confirm,cancel'],
        ]);

        $action = $request->session()->pull("assistant.pending.{$data['token']}");
        if (! $action) {
            return response()->json(['reply' => __('That request has expired. Please ask again.')], 410);
        }

        if ($data['decision'] === 'cancel' || ! in_array($action['name'], AssistantTools::allowedFor($request->user()), true)) {
            return response()->json(['reply' => __('Cancelled. Nothing was changed.')]);
        }

        return response()->json(['reply' => AssistantTools::perform($action['name'], $action['args'])]);
    }

    protected function instructions(Request $request): string
    {
        $user = $request->user();
        $who = $user->role === UserRole::Superadmin
            ? 'a Superadmin (sees everything).'
            : 'an Admin of the '.($user->worksIn(Department::Restaurant) ? 'Restaurant' : 'Massage (Services)').' department. Only use your tools; for anything they have no tool or page for (accounts, Audit Logs, Settings, Maintenance Mode, Rooms, the other department) say a Superadmin handles it.';

        return implode("\n", [
            'You are Mochi, the assistant inside the 88 Hot Spring Resort ordering system (Philippines), talking to '.$user->name.', '.$who,
            'Now: '.now()->format('l, F j, Y g:i A').' (Asia/Manila). Money is in Philippine pesos (₱).',
            'Always reply in the language of the LATEST user message (English, Filipino/Taglish or Korean), even if earlier messages were in another language. Be short and clear; use bullet points for lists.',
            'For ANY number, order, sale, menu item, table or person, call a tool and answer only from what it returns. Never guess or invent data. If a tool returns nothing, say so.',
            'The user may attach a photo or PDF (e.g. a receipt, a menu, a supplier price list, a screenshot of an error). Read it and answer about it; compare it with the system through your tools when useful. You cannot save files, and you only see a file in the message it was attached to.',
            'Buttons to pages, orders and items appear under your answer by themselves when you use a tool. When you explain how to do something or where something is, also call open_page for that page. Never write links or URLs yourself.',
            'Actions (changing anything) are only proposed: after you call an action tool, tell the user to press Confirm on the card below your message. Never say it is done before they confirm. If they ask for a change you have no tool for, explain where in the system to do it instead.',
            'How the system is organised (for "how do I" questions):',
            '- Overview: today\'s sales and activity. Chat: messages between staff.',
            '- Order Management: all slips; New Order (choose dine-in/take-out, area, table, items); open an order to add items, take payment (cash, card, GCash, Maya, QR, room charge, locker/kubo charge; split payments allowed), void a payment (Admin/Superadmin), move to another table, print the kitchen slip (Direct Print or Print for A4). An order completes by itself once paid.',
            '- Weigh & Order, Daily Market Prices, Weighted Items, Cooking Styles: per-kilo seafood/meat items priced daily.',
            '- Kitchen: the kitchen display of slips by stage. Quotations: advance orders that convert into orders. Spaces: areas and tables, their QR codes and status.',
            '- Menu Management: items (variants, add-ons, photos, availability, archive), categories. Reports: sales by day/week/month, payment methods, room and locker/kubo charges, outlets (Korean resto, minibar, Massage) separately, PDF download. Promotions & Banners: the customer menu banners.',
            '- Massage: its own Overview, Massage Orders (room number, services, payment) and Massage Services (variants, add-ons).',
            '- User Management: accounts, roles (Superadmin, Admin, Staff), departments (Restaurant or Services/Massage), PIN sign-in. Audit Logs: who did what. Settings: resort and receipt details, VAT/service charge, Maintenance Mode. Rooms: rooms a room charge can go on.',
        ]);
    }
}
