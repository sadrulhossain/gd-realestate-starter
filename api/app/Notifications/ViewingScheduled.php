<?php

namespace App\Notifications;

use App\Models\Viewing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the agent and the lead that a viewing was booked (queued). */
class ViewingScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Viewing $viewing) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $v = $this->viewing->loadMissing(['property:id,ref_code,title,address_line', 'agent:id,name,phone']);

        return (new MailMessage)
            ->subject('Viewing booked: '.$v->property->title)
            ->line("Property: {$v->property->ref_code} {$v->property->title}")
            ->line('When: '.$v->starts_at->format('D j M Y, H:i').' to '.$v->ends_at->format('H:i').' ('.$v->starts_at->timezoneName.')')
            ->line("Agent: {$v->agent->name}".($v->agent->phone ? " ({$v->agent->phone})" : ''));
    }
}
