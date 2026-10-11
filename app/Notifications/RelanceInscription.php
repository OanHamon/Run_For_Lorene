<?php

namespace App\Notifications;

use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Relance envoyée par un admin : paiement en attente ou documents manquants. */
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

        if ($inscription->statut === Inscription::EN_ATTENTE_PAIEMENT) {
            $mail->subject('Ton inscription attend son paiement')
                ->line("{$qui} ({$course}) est presque terminée : il ne manque que le paiement.")
                ->line("Ta commande est gardée, tu n'as rien à ressaisir. La place n'est garantie qu'une fois le paiement reçu.");
        } else {
            $mail->subject('Il manque un document pour prendre le départ')
                ->line("{$qui} ({$course}) est bien réglée.")
                ->line('Pour recevoir le dossard, il reste à remplir : '.implode(' et ', $inscription->documentsManquants()).'.')
                ->line("Ça prend deux minutes, depuis ton espace.");
        }

        return $mail
            ->action('Ouvrir mon espace', route('espace.inscription', $inscription))
            ->salutation("À très vite,\nL'équipe Run For Lorène");
    }
}
