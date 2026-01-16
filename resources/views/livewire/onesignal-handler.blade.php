<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component {
    /**
     * Update the user's OneSignal Player ID.
     */
    public function updateId($id)
    {
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('OneSignal Update ID Attempt', [
                'user_id' => $user->id,
                'new_player_id' => $id,
                'current_player_id' => $user->onesignal_player_id,
            ]);

            if ($user->onesignal_player_id !== $id) {
                // Using forceFill + save to bypass any potential attribute property issues
                $user->forceFill(['onesignal_player_id' => $id])->save();
                Log::info('OneSignal Player ID Saved', ['user_id' => $user->id, 'player_id' => $id]);
            }
        } else {
            Log::warning('OneSignal Update ID skipped: User not authenticated');
        }
    }
}; ?>

<div x-data="{
    init() {
        console.log('OneSignal Handler Initialized');

        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async (OneSignal) => {
            console.log('OneSignal SDK Ready in Handler');

            const checkAndSaveId = async () => {
                try {
                    // Try to get subscription ID - v16 often needs await or is ready after sync
                    const subscriptionId = await OneSignal.User.PushSubscription.id;
                    console.log('Detected Subscription ID:', subscriptionId);

                    if (subscriptionId) {
                        await $wire.updateId(subscriptionId);
                        console.log('OneSignal ID sync triggered');
                    } else {
                        console.warn('OneSignal ID not yet available, will retry on change.');
                    }
                } catch (e) {
                    console.error('Error checking OneSignal ID:', e);
                }
            };

            // Initial check
            await checkAndSaveId();

            // Listen for subscription changes
            OneSignal.User.PushSubscription.addEventListener('change', async (event) => {
                console.log('OneSignal Subscription changed:', event);
                const newId = event.current?.id;
                if (newId) {
                    await $wire.updateId(newId);
                }
            });

            // Listen for permission changes which might trigger a sub
            OneSignal.Notifications.addEventListener('permissionChange', async (permission) => {
                console.log('OneSignal Permission changed:', permission);
                if (permission === 'granted') {
                    // Slight delay to allow ID generation
                    setTimeout(checkAndSaveId, 2000);
                }
            });
        });
    }
}"></div>
