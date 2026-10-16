{{-- Mochi, the floating assistant (resources/js/lib/assistant-widget.jsx),
     for a Superadmin or Admin (not Staff). data-turbo-permanent keeps it,
     and the chat in it, as Turbo changes pages. Both layouts include this.
     data-departments picks the quick-action chips for this user. --}}
@if (auth()->user()?->isManager())
    <div id="assistant-root" data-turbo-permanent
         data-departments="{{ collect(\App\Enums\Department::cases())->filter(fn ($department) => auth()->user()->worksIn($department))->map->value->implode(',') }}"
         data-chat-url="{{ route('superadmin.assistant.chat') }}"
         data-confirm-url="{{ route('superadmin.assistant.confirm') }}"
         data-alerts-url="{{ route('superadmin.assistant.alerts') }}"></div>
    <script>
        window.assistantStrings = @js(collect([
            'Assistant', 'Online', '88 Hot Spring Resort Assistant', 'New chat', 'Close', 'Send', 'Confirm', 'Cancel',
            'Ask anything, e.g. sales today?', 'Confirm this change',
            "Hi! I'm Mochi, your 88 Hot Spring Resort assistant. Ask me about sales, orders, the menu, tables, Massage or how to do something in the system.",
            'Hi! Do you want to ask something?', "Need today's sales? Just ask me.", 'I can check orders, the menu and tables for you.', 'Not sure how to do something? Ask me!',
            "Today's Sales", 'Order Status', 'Menu & Prices', 'Massage Today', 'System Help',
            'How is the Massage department doing today?',
            'How much are our sales today?', 'What orders are in progress right now?',
            'Give me a short summary of our menu and prices by category.', 'What can you help me with in the system?',
            'Attach a photo or PDF', 'Remove', 'What is in this file?', 'Mochi can read a photo (JPG, PNG, WEBP) or a PDF of up to 5 MB.',
            'Something went wrong. Nothing was changed.', 'The assistant is not available right now. Please try again.',
        ])->mapWithKeys(fn ($key) => [$key => __($key)]));
    </script>
@endif
