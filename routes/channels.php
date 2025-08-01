<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channels
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);
    
    if (!$conversation) {
        return false;
    }

    return $conversation->isUserParticipant($user);
});

Broadcast::channel('user.{userType}.{userId}', function ($user, $userType, $userId) {
    $model = $userType === 'student' ? \App\Models\Student::class : \App\Models\Staff::class;
    
    return get_class($user) === $model && (int) $user->id === (int) $userId;
});