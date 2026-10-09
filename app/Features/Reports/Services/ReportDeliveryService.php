<?php

namespace App\Features\Reports\Services;

use App\Jobs\SendReportRecipientEmail;
use App\Models\Report;
use App\Models\ReportRecipient;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportDeliveryService
{
    public function __construct(private ConsolidatedReportService $reports) {}

    public function finalize(Team $team, Report $report, User $user): Report
    {
        return Cache::lock("reports:finalize:{$report->id}", 15)->block(5, function () use ($team, $report, $user): Report {
            $recipientIds = [];
            $batch = (string) Str::uuid();
            $shouldSend = false;

            $report = DB::transaction(function () use ($team, $report, $user, &$recipientIds, $batch, &$shouldSend): Report {
                $locked = Report::query()->whereBelongsTo($team)->whereKey($report->id)->lockForUpdate()->firstOrFail();

                if (! $locked->items()->where('show_data_results', true)->exists()) {
                    throw ValidationException::withMessages([
                        'report' => 'Habilite ao menos um sistema antes de finalizar o relatório.',
                    ]);
                }

                $recipients = $this->syncRecipients($team, $locked);
                if ($recipients->isEmpty()) {
                    throw ValidationException::withMessages([
                        'recipients' => 'Adicione ao menos um destinatário para receber o relatório.',
                    ]);
                }

                $recipientIds = $this->recipientIds($recipients);
                $shouldSend = $locked->status !== 'completed';

                if ($shouldSend) {
                    $locked->update([
                        'status' => 'completed',
                        'finished_by' => $user->id,
                        'finished_at' => now(),
                    ]);
                    $this->reports->record($locked, 'report.finalized', $user, metadata: [
                        'batch' => $batch,
                        'recipient_count' => count($recipientIds),
                    ]);
                }

                return $locked;
            });

            if ($shouldSend) {
                $this->dispatch($report, $recipientIds, $batch, $user);
            }

            return $report->refresh();
        });
    }

    public function resend(Team $team, Report $report, User $user): Report
    {
        if ($report->status !== 'completed') {
            throw ValidationException::withMessages(['report' => 'Finalize o relatório antes de reenviá-lo.']);
        }

        $batch = (string) Str::uuid();
        $recipients = DB::transaction(fn () => $this->syncRecipients($team, $report));
        $this->dispatch($report, $this->recipientIds($recipients), $batch, $user);

        return $report->refresh();
    }

    public function addRecipient(Team $team, Report $report, string $email, ?string $name, User $user): ReportRecipient
    {
        abort_unless($report->team_id === $team->id, 404);

        $email = trim(mb_strtolower($email));
        $name = trim($name ?? '');

        $matchedUser = $team->members()->where('users.email', $email)->first()
            ?? User::query()->where('email', $email)->first();

        if ($name === '') {
            if ($matchedUser) {
                $name = $matchedUser->name;
            } else {
                $part = explode('@', $email)[0];
                $name = ucwords(str_replace(['.', '_', '-'], ' ', $part));
            }
        }

        $recipient = ReportRecipient::query()
            ->whereBelongsTo($report)
            ->where('email', $email)
            ->first();

        if ($recipient) {
            if ($recipient->revoked_at === null) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está ativo na lista de destinatários.',
                ]);
            }

            $recipient->update([
                'user_id' => $matchedUser?->id ?? $recipient->user_id,
                'name' => $name,
                'revoked_at' => null,
            ]);
        } else {
            $token = Str::random(64);
            $recipient = ReportRecipient::query()->create([
                'team_id' => $team->id,
                'report_id' => $report->id,
                'user_id' => $matchedUser?->id,
                'name' => $name,
                'email' => $email,
                'access_token' => $token,
                'token_hash' => hash('sha256', $token),
            ]);
        }

        $this->reports->record($report, 'recipient.added', $user, recipient: $recipient, metadata: [
            'email' => $recipient->email,
            'name' => $recipient->name,
        ]);

        return $recipient;
    }

    public function resendIndividual(Team $team, Report $report, ReportRecipient $recipient, User $user): void
    {
        abort_unless($recipient->report_id === $report->id && $report->team_id === $team->id, 404);

        if ($report->status !== 'completed') {
            throw ValidationException::withMessages(['report' => 'Finalize o relatório antes de reenviá-lo.']);
        }

        if ($recipient->revoked_at !== null) {
            throw ValidationException::withMessages(['recipient' => 'Não é possível reenviar para um destinatário revogado.']);
        }

        $batch = (string) Str::uuid();
        $this->reports->record($report, 'delivery.queued', $user, recipient: $recipient, metadata: [
            'batch' => $batch,
            'recipient_count' => 1,
            'individual' => true,
            'email' => $recipient->email,
        ]);

        SendReportRecipientEmail::dispatch($recipient->id, $batch)->afterCommit();
    }

    public function revoke(Team $team, Report $report, ReportRecipient $recipient, User $user): void
    {
        abort_unless($recipient->report_id === $report->id && $report->team_id === $team->id, 404);

        if ($recipient->send_count === 0) {
            $recipient->delete();
            $this->reports->record($report, 'recipient.deleted', $user, metadata: [
                'email' => $recipient->email,
                'name' => $recipient->name,
            ]);

            return;
        }

        $recipient->update(['revoked_at' => now()]);
        $this->reports->record($report, 'recipient.revoked', $user, recipient: $recipient, metadata: [
            'email' => $recipient->email,
        ]);
    }

    /** @return Collection<int, ReportRecipient> */
    public function syncRecipients(Team $team, Report $report): Collection
    {
        if ($report->recipients()->doesntExist()) {
            $this->reports->ensureRecipientsInitialized($team, $report);

            if ($report->recipients()->doesntExist()) {
                foreach ($team->members()->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']) as $member) {
                    $token = Str::random(64);
                    ReportRecipient::query()->create([
                        'team_id' => $team->id,
                        'report_id' => $report->id,
                        'user_id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'access_token' => $token,
                        'token_hash' => hash('sha256', $token),
                    ]);
                }
            }
        }

        return $report->recipients()->whereNull('revoked_at')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, ReportRecipient>  $recipients
     * @return list<int>
     */
    private function recipientIds(Collection $recipients): array
    {
        return array_values($recipients->map(fn (ReportRecipient $recipient): int => $recipient->id)->all());
    }

    /**
     * @param  list<int>  $recipientIds
     */
    private function dispatch(Report $report, array $recipientIds, string $batch, User $user): void
    {
        $this->reports->record($report, 'delivery.queued', $user, metadata: [
            'batch' => $batch,
            'recipient_count' => count($recipientIds),
        ]);

        foreach ($recipientIds as $recipientId) {
            SendReportRecipientEmail::dispatch($recipientId, $batch)->afterCommit();
        }
    }
}
