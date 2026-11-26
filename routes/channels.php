<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kanban.board.{boardId}', function ($user, $boardId) {
    if (! $user->is_admin) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});

Broadcast::channel('reports.{reportId}', function (User $user, int|string $reportId): bool {
    if (! $user->is_admin) {
        return false;
    }

    return Report::query()->whereKey((int) $reportId)->exists();
});
