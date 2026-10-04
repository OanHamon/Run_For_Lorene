<?php

namespace App\Notifications;

use App\Services\Auth\CodeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ton code Run For Lorène : {$this->code}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Voici ton code pour confirmer ton adresse email et te connecter :')
            ->line("**{$this->code}**")
            ->line('Il est valable '.CodeEmail::VALIDITE_MINUTES.' minutes.')
            ->line("Si tu n'es pas à l'origine de cette demande, tu peux ignorer cet email.")
            ->salutation("À très vite,\nL'équipe Run For Lorène");
    }
}
