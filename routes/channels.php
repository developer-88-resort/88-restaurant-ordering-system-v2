<?php

use App\Enums\UserRole;
use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kitchen', function ($user) {
    return in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
});

Broadcast::channel('audit-logs', function ($user) {
    return $user->role === UserRole::Superadmin;
});

// Was Superadmin-only (only Manage Users listened to it). Chat's online
// dot needs it too, and the event carries no payload — just a "something
// changed, go refetch" ping — so widening this to the same operational
// tier as kitchen/spaces/staff-alerts/dashboard-stats is safe.
Broadcast::channel('user-presence', function ($user) {
    return in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
});

Broadcast::channel('spaces', function ($user) {
    return in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
});

Broadcast::channel('staff-alerts', function ($user) {
    return in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
});

Broadcast::channel('dashboard-stats', function ($user) {
    return in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
});

Broadcast::channel('chat.conversation.{conversation}', function ($user, Conversation $conversation) {
    return $conversation->hasParticipant($user);
});

Broadcast::channel('chat-inbox.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
