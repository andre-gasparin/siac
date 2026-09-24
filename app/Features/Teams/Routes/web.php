<?php

use App\Features\Teams\Http\Controllers\CurrentTeamController;
use App\Features\Teams\Http\Controllers\TeamController;
use App\Features\Teams\Http\Controllers\TeamIndexController;
use App\Features\Teams\Http\Controllers\TeamInvitationController;
use App\Features\Teams\Http\Controllers\TeamMemberController;
use App\Features\Teams\Http\Controllers\TeamSystemController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])
        ->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])
        ->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('unidades', TeamIndexController::class)->name('teams.index');
    Route::post('unidades', [TeamController::class, 'store'])->name('teams.store');

    Route::middleware(EnsureTeamMembership::class)->group(function (): void {
        Route::get('unidades/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('unidades/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('unidades/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('unidades/{team}/switch', [CurrentTeamController::class, 'switch'])->name('teams.switch');
        Route::delete('unidades/{team}/leave', [CurrentTeamController::class, 'leave'])->name('teams.leave');

        Route::get('unidades/{team}/sistemas', [TeamSystemController::class, 'edit'])->name('teams.systems.edit');
        Route::put('unidades/{team}/sistemas', [TeamSystemController::class, 'update'])->name('teams.systems.update');

        Route::patch('unidades/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('unidades/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

        Route::post('unidades/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('unidades/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
    });
});
