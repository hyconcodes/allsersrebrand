<x-mail::message>
    # Hi {{ $recipient->name }},

    {{ $messageText }}

    <x-mail::button :url="config('app.url') . '/dashboard'">
        Go to Dashboard
    </x-mail::button>

    Thanks,<br>
    The {{ config('app.name') }} Team
</x-mail::message>