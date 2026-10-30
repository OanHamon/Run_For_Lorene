<?php

namespace App\Notifications;

use App\Support\Activation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Compte créé par l'association : lien pour choisir son mot de passe et accéder à son espace. */
class CompteCree extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $reinitialisation  accès réinitialisé par un admin : choisir un nouveau mot de passe
     * @param  bool  $admin  compte admin créé par un super admin : accès à l'administration
     */
    public function __construct(
        public readonly ?string $suite = null,
        public readonly bool $reinitialisation = false,
        public readonly bool $admin = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->reinitialisation) {
            return (new MailMessage)
                ->subject('Choisis ton nouveau mot de passe Run For Lorène')
                ->greeting("Bonjour {$notifiable->prenom},")
                ->line("L'association a réinitialisé l'accès à ton compte ({$notifiable->email}). Ton ancien mot de passe ne fonctionne plus.")
                ->action('Choisir un nouveau mot de passe', Activation::url($notifiable, $this->suite))
                ->line('Ce lien est valable '.Activation::VALIDITE_JOURS.' jours et ne sert qu\'une fois.')
                ->salutation("À bientôt,\nL'équipe Run For Lorène");
        }

        if ($this->admin) {
            return (new MailMessage)
                ->subject('Ton accès à l\'administration Run For Lorène')
                ->greeting("Bonjour {$notifiable->prenom},")
                ->line("L'association Effervescence Jeunes t'a créé un compte admin sur le site d'inscription de Run For Lorène, avec ton adresse {$notifiable->email}.")
                ->line('Pour y accéder, choisis ton mot de passe :')
                ->action('Créer mon mot de passe', Activation::url($notifiable))
                ->line('Ce lien est valable '.Activation::VALIDITE_JOURS.' jours et ne sert qu\'une fois. Ensuite, tu te connectes à l\'administration avec ton email, ton mot de passe et un code reçu par email.')
                ->line('Tu ne t\'attendais pas à ce message ? Préviens l\'association rapidement.')
                ->salutation("L'équipe Run For Lorène");
        }

        return (new MailMessage)
            ->subject('Ton compte Run For Lorène est prêt')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("L'association Effervescence Jeunes t'a créé un compte sur le site d'inscription de Run For Lorène, avec ton adresse {$notifiable->email}.")
            ->line('Pour y accéder, choisis ton mot de passe :')
            ->action('Créer mon mot de passe', Activation::url($notifiable, $this->suite))
            ->line('Ce lien est valable '.Activation::VALIDITE_JOURS.' jours et ne sert qu\'une fois. Ensuite, tu te connectes avec ton email et ton mot de passe.')
            ->line('Tu n\'attendais pas ce message ? Ignore-le simplement.')
            ->salutation("À bientôt,\nL'équipe Run For Lorène");
    }
}
