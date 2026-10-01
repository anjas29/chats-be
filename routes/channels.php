<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->id === $id);

Broadcast::channel('conversation.{conversation}', fn (User $user, Conversation $conversation) => Gate::forUser($user)->allows('view', $conversation));

Broadcast::channel('online', fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
