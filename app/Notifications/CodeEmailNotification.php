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

    public function __construct(public readonly string $code, public readonly string $usage = CodeEmail::CONNEXION) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ton code Run For Lorène : {$this->code}")
            ->markdown('emails.code', [
                'prenom' => $notifiable->prenom,
                'intro' => $this->usage === CodeEmail::ADMIN
                    ? "Voici ton code pour entrer dans l'espace d'administration :"
                    : 'Voici ton code pour confirmer ton adresse email et te connecter :',
                'code' => $this->code,
                'minutes' => CodeEmail::VALIDITE_MINUTES,
            ]);
    }
}
