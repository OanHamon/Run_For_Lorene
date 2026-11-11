<?php

namespace App\Notifications;

use App\Models\Commande;
use App\Models\Inscription;
use App\Support\Billet;
use App\Support\Format;
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

        $donSeul = $commande->inscriptions->isEmpty();

        $mail = (new MailMessage)
            ->subject($donSeul ? 'Merci pour ton don à Run For Lorène' : 'Ton inscription à Run For Lorène est enregistrée')
            ->greeting("Merci {$notifiable->prenom} !")
            ->line("Ta commande n° {$commande->id_commande} de ".Format::euros($commande->montant_total).' est bien réglée.');

        foreach ($commande->inscriptions as $inscription) {
            $ligne = "{$inscription->participant->user->prenom} {$inscription->participant->user->nom} · {$inscription->tarif->course->nom}";

            $ligne .= match (true) {
                $inscription->statut === Inscription::VALIDEE && $inscription->dossard !== null => " · dossard n° {$inscription->dossard}",
                $inscription->statut === Inscription::VALIDEE => ' · inscription validée',
                default => ' · à compléter dans ton espace : '.implode(' et ', $inscription->documentsManquants()).' (obligatoire pour prendre le départ)',
            };

            // Billet à présenter au retrait des dossards, comme dans « Inscription validée ».
            if ($inscription->statut === Inscription::VALIDEE) {
                $ligne .= ' · [ouvrir le billet]('.Billet::url($inscription).')';
            }

            $mail->line($ligne);
        }

        // Un billet PDF par inscription validée (empreinte, QR de secours, dossard), à imprimer.
        $validees = $commande->inscriptions->where('statut', Inscription::VALIDEE);
        if ($validees->isNotEmpty()) {
            $mail->line($validees->count() > 1
                ? 'Les billets, à présenter au retrait des dossards, sont joints à cet email (PDF, à imprimer ou à garder sur ton téléphone).'
                : 'Le billet, à présenter au retrait des dossards, est joint à cet email (PDF, à imprimer ou à garder sur ton téléphone).');
            foreach ($validees as $inscription) {
                $mail->attachData(Billet::pdf($inscription), Billet::nomFichier($inscription), ['mime' => 'application/pdf']);
            }
        }

        if ($commande->don) {
            $mail->line('Et merci pour ton don de '.Format::euros($commande->don->montant).' !');
        }

        return $mail
            ->action('Voir mon espace', route('espace'))
            ->salutation($donSeul ? "Merci pour ton soutien,\nL'équipe Run For Lorène" : "À très vite sur la ligne de départ,\nL'équipe Run For Lorène");
    }
}
