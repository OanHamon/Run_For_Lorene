<?php

namespace App\Notifications;

use App\Models\Inscription;
use App\Support\Activation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Relance des documents manquants d'une inscription payée (admin ou relance automatique). */
class RelanceInscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Inscription $inscription) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inscription = $this->inscription->load('participant.user', 'tarif.course', 'evenement');
        $qui = $inscription->participant->id_user === $notifiable->id_user
            ? 'Ton inscription'
            : "L'inscription de {$inscription->participant->user->prenom}";
        $course = "{$inscription->tarif->course->nom} · {$inscription->evenement->nom}";

        $mail = (new MailMessage)->greeting("Bonjour {$notifiable->prenom},");
        // Compte créé par l'association et pas encore activé : le lien fait d'abord choisir le mot de passe.
        $lien = Activation::lienEspace($notifiable, route('espace.inscription', $inscription, absolute: false));

        $mail->subject('Il manque un document pour prendre le départ')
            ->line("{$qui} ({$course}) est bien réglée.")
            ->line('Pour recevoir le dossard, il reste à remplir : '.implode(' et ', $inscription->documentsManquants()).'.')
            ->line('Ça prend deux minutes, depuis ton espace.');

        return $mail
            ->action($lien['active'] ? 'Ouvrir mon espace' : 'Créer mon mot de passe', $lien['url'])
            ->salutation("À très vite,\nL'équipe Run For Lorène");
    }
}
