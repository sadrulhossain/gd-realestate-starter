<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the assigned agent (or the site owner when unassigned) about a new lead. Queued; replies go to the lead. */
class NewLead extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead->loadMissing('property:id,ref_code,title');

        $mail = (new MailMessage)
            ->subject('New lead: '.$lead->name)
            ->line("From: {$lead->name}".($lead->email ? " <{$lead->email}>" : '').($lead->phone ? " ({$lead->phone})" : ''));

        if ($lead->property) {
            $mail->line("Property: {$lead->property->ref_code} {$lead->property->title}");
        }

        if ($lead->viewing_requested_at) {
            $mail->line('Viewing requested for: '.$lead->viewing_requested_at->format('Y-m-d H:i T'));
        }

        if (filled($lead->message)) {
            $mail->line($lead->message);
        }

        return filled($lead->email) ? $mail->replyTo($lead->email, $lead->name) : $mail;
    }
}
