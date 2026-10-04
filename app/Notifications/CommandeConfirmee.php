<?php

namespace App\Notifications;

use App\Models\Commande;
use App\Models\Inscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emails « Paiement réussi » et « Inscription validée » du spec, regroupés par commande. */
class CommandeConfirmee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commande $commande) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $commande = $this->commande->load('inscriptions.participant.user', 'inscriptions.tarif.course', 'don');
        $euros = fn ($m) => number_format((float) $m, 2, ',', ' ').' €';

        $mail = (new MailMessage)
            ->subject('Ton inscription à Run For Lorène est enregistrée')
            ->greeting("Merci {$notifiable->prenom} !")
            ->line("Ta commande n° {$commande->id_commande} de {$euros($commande->montant_total)} est bien réglée.");

        foreach ($commande->inscriptions as $inscription) {
            $ligne = "{$inscription->participant->user->prenom} {$inscription->participant->user->nom} · {$inscription->tarif->course->nom}";

            $ligne .= match (true) {
                $inscription->statut === Inscription::VALIDEE && $inscription->dossard !== null => " · dossard n° {$inscription->dossard}",
                $inscription->statut === Inscription::VALIDEE => ' · inscription validée',
                default => ' · à compléter dans ton espace : '.implode(' et ', $inscription->documentsManquants()).' (obligatoire pour prendre le départ)',
            };

            $mail->line($ligne);
        }

        if ($commande->don) {
            $mail->line("Et merci pour ton don de {$euros($commande->don->montant)} !");
        }

        return $mail
            ->action('Voir mon espace', route('espace'))
            ->salutation("À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
