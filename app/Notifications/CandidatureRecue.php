<?php

namespace App\Notifications;

use App\Models\CandidatureBenevole;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Accusé de réception d'une candidature bénévole (message de fin proposé par le brief). */
class CandidatureRecue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CandidatureBenevole $candidature) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $candidature = $this->candidature->load('evenement');
        $mail = (new MailMessage)
            ->subject("Ta candidature bénévole pour {$candidature->evenement->nom} est bien reçue")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Merci pour ces informations et pour ta proposition d\'aide. Ta candidature a bien été enregistrée.')
            ->line('L\'organisation reviendra vers toi pour confirmer ou non ta participation, selon les besoins et tes disponibilités.');

        if ($candidature->autorisationRequise() && ! $candidature->autorisation_parentale) {
            $mail->line('N\'oublie pas : comme tu as moins de 16 ans, il nous faut l\'autorisation parentale signée. Tu peux la déposer depuis ton espace.');
        }

        if (! $candidature->photo) {
            $mail->line('Pense aussi à déposer ta photo pour ton badge, depuis ton espace.');
        }

        return $mail
            ->action('Suivre ma candidature', route('espace.benevole'))
            ->salutation("Merci pour ton élan,\nL'équipe Run For Lorène");
    }
}
