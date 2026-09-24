<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('spreadsheet-imports:fetch-emails')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->description('Buscar e-mails e capturar planilhas correspondentes às regras ativas');

Schedule::command('spreadsheet-imports:process-queue')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Processar fila customizada de importação de planilhas');
