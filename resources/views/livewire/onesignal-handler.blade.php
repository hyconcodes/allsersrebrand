<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    /**
     * Update the user's OneSignal Player ID.
     */
    public function updateId($id)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->onesignal_player_id !== $id) {
                $user->update(['onesignal_player_id' => $id]);
            }
        }
    }
}; ?>

<div x-data="{
    init() {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async (OneSignal) => {
            // The user's script already calls .init in head.blade.php
            // We just need to capture the ID when it's available.

            const checkId = async () => {
                const playerId = OneSignal.User.PushSubscription.id;
                if (playerId) {
                    $wire.updateId(playerId);
                }
            };

            // Capture initial ID
            await checkId();

            // Listen for changes
            OneSignal.User.PushSubscription.addEventListener('change', (event) => {
                if (event.current.id) {
                    $wire.updateId(event.current.id);
                }
            });
        });
    }
}"></div>
