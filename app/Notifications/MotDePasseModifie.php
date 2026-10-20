<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mot de passe ajouté ou changé depuis « Mon compte » : la personne est prévenue, pour
 * repérer tout de suite un changement fait à son insu.
 */
class MotDePasseModifie extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ton mot de passe Run For Lorène a changé')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Le mot de passe de ton compte vient d\'être modifié. Tes autres appareils ont été déconnectés.')
            ->line('Ce n\'était pas toi ? Connecte-toi avec un code reçu par email, choisis un nouveau mot de passe depuis « Mon compte », puis préviens l\'association.')
            ->action('Me connecter', route('login'))
            ->salutation("L'équipe Run For Lorène");
    }
}
