<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Relance;
use App\Models\User;
use App\Notifications\RelanceAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relances ciblées depuis l'admin (niveau 2) : un groupe d'inscriptions filtré, ou une seule.
 * Seules les inscriptions réglées sont visées (validées, dossier incomplet) : une inscription
 * non finalisée n'est pas relancée. Le destinataire est le titulaire (payeur, responsable ou
 * participant), un email par inscription.
 */
class RelancesCiblees
{
    /** Une inscription relancée il y a moins de 48 h est signalée, et écartée sauf choix contraire. */
    public const DELAI_HEURES = 48;

    public const STATUTS = [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET];

    public function __construct(private Journal $journal) {}

    /**
     * Cible normalisée depuis la requête : une inscription précise, ou des filtres.
     *
     * @return array{inscription: ?int, statut: ?string, course: ?int, mineur: bool, documents: bool, non_retire: bool}
     */
    public static function cible(array $entree): array
    {
        return [
            'inscription' => isset($entree['inscription']) && $entree['inscription'] !== '' ? (int) $entree['inscription'] : null,
            'statut' => in_array($entree['statut'] ?? null, self::STATUTS, true) ? $entree['statut'] : null,
            'course' => isset($entree['course']) && $entree['course'] !== '' ? (int) $entree['course'] : null,
            'mineur' => filter_var($entree['mineur'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'documents' => filter_var($entree['documents'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'non_retire' => filter_var($entree['non_retire'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Inscriptions visées, avec leur titulaire et, pour chacune, la date de la dernière relance
     * de moins de 48 h (attribut `relancee_le`).
     *
     * @return Collection<int, Inscription>
     */
    public function destinataires(Evenement $evenement, array $cible): Collection
    {
        return $this->requete($evenement, $cible)
            ->with('participant.user', 'participant.responsable', 'commande.user', 'tarif.course', 'evenement', 'documents.questionnaireSante', 'documents.consentement.texteLegal')
            ->withMax(['relances as relancee_le' => fn ($q) => $q->where('relances.created_at', '>=', now()->subHours(self::DELAI_HEURES))], 'relances.created_at')
            ->orderBy('id_inscription')
            ->get()
            ->filter(fn (Inscription $i) => $i->titulaire()?->email)
            ->values();
    }

    /** Variables du message pour une inscription. */
    public function variables(Inscription $i, User $destinataire): array
    {
        return [
            '{prenom}' => $destinataire->prenom,
            '{participant}' => $i->participant->user->prenom,
            '{course}' => $i->tarif->course->nom,
            '{heure}' => $i->tarif->course->heure_depart ? substr((string) $i->tarif->course->heure_depart, 0, 5) : 'à préciser',
            '{dossard}' => $i->dossard ? (string) $i->dossard : 'bientôt attribué',
            '{evenement}' => $i->evenement->nom,
            '{date}' => $i->evenement->date_evenement->translatedFormat('l j F Y'),
            '{documents}' => implode(' et ', $i->documentsManquants()) ?: 'rien, tout est en ordre',
        ];
    }

    /** Objet et message prêts pour une inscription (variables remplacées). */
    public function personnaliser(Inscription $i, string $sujet, string $message): array
    {
        $titulaire = $i->titulaire();
        $variables = $this->variables($i, $titulaire);

        return ['sujet' => strtr($sujet, $variables), 'message' => strtr($message, $variables), 'destinataire' => $titulaire];
    }

    /**
     * Envoie la relance (par la file d'attente) et l'enregistre, avec ses destinataires.
     *
     * @param  bool  $inclureRecents  envoyer aussi aux inscriptions relancées il y a moins de 48 h
     */
    public function envoyer(Evenement $evenement, string $modele, string $sujet, string $message, array $cible, bool $inclureRecents, User $admin): Relance
    {
        $inscriptions = $this->destinataires($evenement, $cible)
            ->when(! $inclureRecents, fn ($c) => $c->filter(fn ($i) => $i->relancee_le === null))
            ->values();

        $relance = DB::transaction(function () use ($evenement, $modele, $sujet, $message, $cible, $inscriptions, $admin) {
            $relance = Relance::create([
                'modele' => $modele,
                'sujet' => $sujet,
                'message' => $message,
                'cible' => $cible,
                'nb_destinataires' => $inscriptions->count(),
                'id_evenement' => $evenement->id_evenement,
                'id_admin' => $admin->id_user,
            ]);

            $relance->inscriptions()->attach($inscriptions->mapWithKeys(fn (Inscription $i) => [
                $i->id_inscription => ['id_user' => $i->titulaire()->id_user, 'email' => $i->titulaire()->email],
            ])->all());

            return $relance;
        });

        foreach ($inscriptions as $i) {
            $envoi = $this->personnaliser($i, $sujet, $message);
            $envoi['destinataire']->notify(new RelanceAdmin($i, $envoi['sujet'], $envoi['message']));
        }

        $this->journal->noter('relance.envoi', $relance, [
            'modèle' => $modele,
            'objet' => $sujet,
            'destinataires' => $inscriptions->count(),
        ]);

        return $relance;
    }

    private function requete(Evenement $evenement, array $cible): Builder
    {
        return Inscription::query()
            ->where('id_evenement', $evenement->id_evenement)
            ->whereIn('statut', self::STATUTS)
            ->when($cible['inscription'], fn ($q, $id) => $q->whereKey($id))
            ->when(! $cible['inscription'], fn ($q) => $q
                ->when($cible['statut'], fn ($q2, $statut) => $q2->where('statut', $statut))
                ->when($cible['course'], fn ($q2, $course) => $q2->whereIn('id_tarif', Course::whereKey($course)->firstOrNew()->tarifs()->select('id_tarif')))
                ->when($cible['mineur'], fn ($q2) => $q2->mineurs($evenement))
                ->when($cible['documents'], fn ($q2) => $q2->avecDocumentsManquants($evenement))
                ->when($cible['non_retire'], fn ($q2) => $q2->where('statut', Inscription::VALIDEE)->whereNull('date_retrait')));
    }
}
