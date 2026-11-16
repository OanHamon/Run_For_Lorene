<?php

namespace App\Notifications;

use App\Models\CandidatureBenevole;
use App\Support\Benevolat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nouveau statut d'une candidature bénévole, donné par l'organisation : une phrase par statut,
 * puis le message libre de l'admin s'il y en a un (échappé par le gabarit d'email).
 */
class CandidatureStatutModifie extends Notification implements ShouldQueue
{
    use Queueable;

    public const PHRASES = [
        CandidatureBenevole::RECUE => 'Ta candidature bénévole est bien enregistrée.',
        CandidatureBenevole::EN_ETUDE => 'L\'organisation étudie ta candidature bénévole. On revient vers toi bientôt.',
        CandidatureBenevole::INFOS_DEMANDEES => 'L\'organisation a besoin de quelques précisions sur ta candidature bénévole.',
        CandidatureBenevole::CONFIRMEE => 'Bonne nouvelle : ta participation comme bénévole est confirmée. Merci !',
        CandidatureBenevole::NON_RETENUE => 'Merci pour ta proposition d\'aide. Cette fois, nous ne pouvons pas retenir ta candidature bénévole, selon les besoins de l\'organisation.',
        CandidatureBenevole::DESISTEMENT => 'Nous avons bien noté ton désistement. Merci de nous avoir prévenus.',
    ];

    public function __construct(public readonly CandidatureBenevole $candidature, public readonly ?string $message = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $candidature = $this->candidature->load('evenement');

        $mail = (new MailMessage)
            ->subject("Ta candidature bénévole : {$candidature->libelleStatut()}")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line(self::PHRASES[$candidature->statut] ?? $candidature->libelleStatut().'.');

        if ($candidature->statut === CandidatureBenevole::CONFIRMEE && $candidature->mission_affectee) {
            $mail->line('Ta mission : '.Benevolat::mission($candidature->mission_affectee).'.');
        }

        foreach (array_filter(array_map('trim', preg_split('/\R{2,}/', trim((string) $this->message)))) as $paragraphe) {
            $mail->line($paragraphe);
        }

        if ($candidature->statut === CandidatureBenevole::INFOS_DEMANDEES) {
            $mail->replyTo(Benevolat::CONTACT)->line('Tu peux répondre directement à cet email.');
        }

        return $mail
            ->action('Voir ma candidature', route('espace.benevole'))
            ->salutation("L'équipe Run For Lorène");
    }
}
