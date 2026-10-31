<?php

namespace App\Notifications;

use App\Models\Inscription;
use App\Support\Activation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Relance écrite depuis l'admin (Services\Admin\RelancesCiblees) : objet et message déjà
 * personnalisés, découpés en paragraphes ; le texte est échappé par le gabarit d'email.
 */
class RelanceAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Inscription $inscription, public readonly string $sujet, public readonly string $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paragraphes = array_map('trim', preg_split('/\R{2,}/', trim($this->message)));

        // Le message commence d'habitude par « Bonjour … » : c'est la formule d'appel de l'email.
        $appel = str_starts_with((string) ($paragraphes[0] ?? ''), 'Bonjour') ? array_shift($paragraphes) : "Bonjour {$notifiable->prenom},";
        $mail = (new MailMessage)->subject($this->sujet)->greeting($appel);

        foreach (array_filter($paragraphes) as $paragraphe) {
            $mail->line($paragraphe);
        }

        // Compte créé par l'association et pas encore activé : le lien fait d'abord choisir le mot de passe.
        $lien = Activation::lienEspace($notifiable, route('espace.inscription', $this->inscription, absolute: false));

        return $mail
            ->action($lien['active'] ? 'Ouvrir mon espace' : 'Créer mon mot de passe', $lien['url'])
            ->salutation("L'équipe Run For Lorène");
    }
}
