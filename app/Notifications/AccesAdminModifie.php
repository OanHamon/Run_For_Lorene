<?php

namespace App\Notifications;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Accès au back-office donné, modifié ou retiré : la personne est toujours prévenue. Une
 * promotion faite par erreur (ou à son insu) se repère ainsi tout de suite.
 */
class AccesAdminModifie extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $avant, public readonly int $apres, public readonly string $auteur) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting("Bonjour {$notifiable->prenom},");

        if ($this->apres === 0) {
            return $mail->subject('Ton accès à l\'administration Run For Lorène est retiré')
                ->line("{$this->auteur} a retiré ton accès à l'administration. Ton espace participant reste disponible.")
                ->salutation("L'équipe Run For Lorène");
        }

        return $mail->subject('Ton accès à l\'administration Run For Lorène')
            ->line("{$this->auteur} t'a donné l'accès « ".Admin::LIBELLES[$this->apres]." » à l'administration du site.")
            ->line(match ($this->apres) {
                Admin::LECTURE => 'Tu peux consulter les inscriptions, les commandes et les dons.',
                Admin::GESTION => 'Tu peux gérer les inscriptions, les dossards et les questionnaires de santé.',
                default => 'Tu as tous les droits : paramétrage, remboursements, gestion des admins.',
            })
            ->action('Ouvrir l\'administration', route('admin.tableau'))
            ->line('Tu ne t\'attendais pas à ce message ? Préviens l\'association rapidement.')
            ->salutation("L'équipe Run For Lorène");
    }
}
