<?php

namespace App\Notifications;

use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Inscription gratuite créée par l'association : le titulaire est prévenu et invité à compléter le dossier. */
class InscriptionOfferte extends Notification implements ShouldQueue
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
        $qui = $inscription->participant->id_user === $notifiable->id_user ? 'Tu es inscrit(e)' : "{$inscription->participant->user->prenom} est inscrit(e)";

        $mail = (new MailMessage)
            ->subject("Ton inscription à {$inscription->evenement->nom}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("{$qui} à {$inscription->tarif->course->nom}, le {$inscription->evenement->date_evenement->translatedFormat('j F Y')}. L'inscription est offerte par l'association.");

        $manquants = $inscription->documentsManquants();

        if ($manquants) {
            $mail->line('Pour recevoir le dossard, il reste à remplir : '.implode(' et ', $manquants).'.')
                ->line('Connecte-toi avec ton adresse email : tu recevras un code, pas besoin de mot de passe.');
        } elseif ($inscription->dossard) {
            $mail->line("Dossard n° {$inscription->dossard}.");
        }

        return $mail
            ->action('Ouvrir mon espace', route('espace.inscription', $inscription))
            ->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
