<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth:web']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    return $user->conversations()->where('conversations.id', $id)->exists();
});
