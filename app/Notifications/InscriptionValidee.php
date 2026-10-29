<?php

namespace App\Notifications;

use App\Models\Inscription;
use App\Support\Activation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email « Inscription validée » du spec, pour une inscription qui le devient après coup (dossier
 * complété depuis l'espace). À la commande, c'est CommandeConfirmee qui donne les dossards.
 */
class InscriptionValidee extends Notification implements ShouldQueue
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
        $moi = $inscription->participant->id_user === $notifiable->id_user;
        $course = "{$inscription->tarif->course->nom} · {$inscription->evenement->nom}, le {$inscription->evenement->date_evenement->translatedFormat('l j F Y')}";
        $lien = Activation::lienEspace($notifiable, route('espace.inscription', $inscription, absolute: false));

        return (new MailMessage)
            ->subject($inscription->dossard ? "Dossard n° {$inscription->dossard} pour Run For Lorène" : 'Inscription validée pour Run For Lorène')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line(($moi ? 'Ton dossier est complet : ton inscription' : "Le dossier de {$inscription->participant->user->prenom} est complet : son inscription")." est validée ({$course}).")
            ->line($inscription->dossard ? "Numéro de dossard : {$inscription->dossard}." : "Le numéro de dossard sera communiqué par l'association.")
            ->action($lien['active'] ? 'Voir mon espace' : 'Créer mon mot de passe', $lien['url'])
            ->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
