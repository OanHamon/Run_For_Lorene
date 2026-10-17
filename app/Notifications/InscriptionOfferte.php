<?php

namespace App\Notifications;

use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Inscription créée par l'association : la famille est prévenue et guidée pas à pas pour
 * compléter le dossier. Écrit pour quelqu'un qui n'a jamais utilisé le site : pas de mot de
 * passe, un code reçu par email, des étapes numérotées.
 */
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
        $moi = $inscription->participant->id_user === $notifiable->id_user;
        $qui = $moi ? 'tu es inscrit(e)' : "{$inscription->participant->user->prenom} est inscrit(e)";
        $evenement = $inscription->evenement;
        $manquants = $inscription->documentsManquants();

        $mail = (new MailMessage)
            ->subject(($moi ? 'Tu es inscrit(e)' : "{$inscription->participant->user->prenom} est inscrit(e)")." à {$evenement->nom}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("Bonne nouvelle : {$qui} à la {$inscription->tarif->course->nom} de {$evenement->nom}, le {$evenement->date_evenement->translatedFormat('l j F Y')}. L'inscription est offerte par l'association.");

        if (! $manquants) {
            return $mail
                ->line($inscription->dossard ? "Le dossard porte le numéro {$inscription->dossard}." : 'Tout est en ordre.')
                ->action('Voir l\'inscription', route('espace.inscription', $inscription))
                ->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
        }

        return $mail
            ->line('Il reste une petite étape, environ 2 minutes : remplir '.implode(' et ', $manquants).'.')
            ->line('**Comment faire :**')
            ->line('1. Clique sur le bouton ci-dessous.')
            ->line("2. Tape ton adresse email : {$notifiable->email}")
            ->line('3. Tu reçois un code à 6 chiffres par email : recopie-le. Pas de mot de passe à créer.')
            ->action('Remplir les documents', route('espace.inscription', $inscription))
            ->line("Le code est valable 15 minutes. S'il a expiré, la page te propose d'en recevoir un nouveau.")
            ->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
