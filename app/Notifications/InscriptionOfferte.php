<?php

namespace App\Notifications;

use App\Models\Inscription;
use App\Support\Activation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Inscription créée par l'association : la famille est prévenue et guidée pas à pas pour
 * compléter le dossier. Compte pas encore activé : le bouton fait choisir le mot de passe,
 * puis mène à l'inscription ; sinon il mène directement à l'inscription.
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

        // Compte pas encore activé : le bouton fait d'abord choisir le mot de passe, puis mène à l'inscription.
        $lien = Activation::lienEspace($notifiable, route('espace.inscription', $inscription, absolute: false));

        if (! $manquants) {
            $mail->line($inscription->dossard ? "Le dossard porte le numéro {$inscription->dossard}." : 'Tout est en ordre.');
        } else {
            $mail->line('Il reste une petite étape, environ 2 minutes : remplir '.implode(' et ', $manquants).'.');
        }

        if ($lien['active']) {
            $mail->line("Connecte-toi avec ton email ({$notifiable->email}) et ton mot de passe. Mot de passe oublié ? La page de connexion te propose de recevoir un code.")
                ->action($manquants ? 'Remplir les documents' : 'Voir l\'inscription', $lien['url']);
        } else {
            $mail->line('Commence par choisir ton mot de passe : le bouton t\'emmène ensuite directement à l\'inscription.')
                ->action('Créer mon mot de passe', $lien['url'])
                ->line('Ce lien est valable '.Activation::VALIDITE_JOURS.' jours et ne sert qu\'une fois.');
        }

        return $mail->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
