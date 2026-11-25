<?php

namespace App\Features\Reports\Notifications;

use App\Models\ReportRecipient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportAccessNotification extends Notification
{
    /**
     * @param  list<array{system: string, author: string, body: string}>  $interactions
     */
    public function __construct(
        public ReportRecipient $recipient,
        public array $interactions = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->recipient->report;
        $report->loadMissing(['team:id,name', 'finisher:id,name', 'creator:id,name']);

        $dateReference = $report->date_reference?->format('d/m/Y') ?? '—';
        $teamName = $report->team->name;
        $subject = $this->interactions !== []
            ? "Novas interações no relatório ({$dateReference}) - {$teamName}"
            : "Relatório de Controle Químico ({$dateReference}) - {$teamName}";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.reports.access', [
                'subjectLine' => $subject,
                'appName' => (string) config('app.name', 'sIAc3'),
                'recipientName' => $this->recipient->name,
                'teamName' => $teamName,
                'dateReference' => $dateReference,
                'issuerName' => $report->finisher->name ?? $report->creator->name ?? null,
                'interactions' => $this->interactions,
                'actionUrl' => route('reports.public.show', ['token' => $this->recipient->access_token]),
            ]);
    }
}
