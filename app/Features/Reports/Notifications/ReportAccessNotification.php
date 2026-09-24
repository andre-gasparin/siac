<?php

namespace App\Features\Reports\Notifications;

use App\Models\ReportRecipient;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportAccessNotification extends Notification
{
    public function __construct(public ReportRecipient $recipient) {}

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

        return (new MailMessage)
            ->subject('Relatório de dados - '.$report->team->name)
            ->greeting('Olá, '.$this->recipient->name.'!')
            ->line('O relatório de dados de '.$report->date_reference?->format('d/m/Y').' está disponível.')
            ->action('Acessar relatório', route('reports.public.show', ['token' => $this->recipient->access_token]))
            ->line('Este link é individual e identifica seus comentários no relatório.');
    }
}
