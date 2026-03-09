<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('chat.{chatId}', function ($user, string $chatId) {
    return DB::table('chats')
        ->where('id', $chatId)
        ->where(function ($q) use ($user) {
            $q->where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id);
        })
        ->exists();
});

Broadcast::channel('private-chat.{chatId}', function ($user, string $chatId) {
    return DB::table('chats')
        ->where('id', $chatId)
        ->where(function ($q) use ($user) {
            $q->where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id);
        })
        ->exists();
});

Broadcast::channel('presence-chat.{chatId}', function ($user, string $chatId) {
    $allowed = DB::table('chats')
        ->where('id', $chatId)
        ->where(function ($q) use ($user) {
            $q->where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id);
        })
        ->exists();

    if (! $allowed) {
        return false;
    }

    return [
        'id' => (string) $user->id,
        'display_name' => $user->display_name
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
    ];
});

Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return (string) $user->id === (string) $id;
});


Broadcast::channel('user.{userId}', function ($user, string $userId) {
    return (string) $user->id === (string) $userId;
});
