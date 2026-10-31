<?php

namespace App\Services\Admin;

use App\Http\Controllers\Admin\QuestionnaireController;
use App\Models\Admin;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\QuestionnaireSante;
use App\Models\QuestionSante;
use App\Models\User;
use App\Support\Format;

/**
 * « À traiter » : tout ce qui attend une action d'un admin, regroupé par type, le plus urgent
 * d'abord. Les inscriptions non finalisées (brouillons, paiement en attente) n'y sont pas :
 * l'inscription se fait d'une traite, on ne les relance pas.
 *
 * Chaque groupe : clé, titre, explication, éléments (libellé, détail, lien) et le nombre total.
 */
class ATraiter
{
    /** Au-delà, la page renvoie vers la liste filtrée. */
    public const MAX_PAR_GROUPE = 50;

    public function __construct(private Statistiques $stats, private PreparationEvenement $preparation) {}

    /**
     * @return list<array{cle: string, titre: string, aide: string, nombre: int, elements: list<array{libelle: string, detail: ?string, url: ?string}>, voir_tout: ?string}>
     */
    public function groupes(?Evenement $evenement, User $admin): array
    {
        $groupes = [];

        if ($evenement?->statut === Evenement::PUBLIE && $evenement->fermeture->isFuture()) {
            $problemes = $this->preparation->problemes($evenement);
            $groupes[] = $this->groupe('bloquants', 'Inscriptions bloquées', "L'événement est en ligne mais incomplet : des personnes ne peuvent pas s'inscrire.",
                array_map(fn ($p) => ['libelle' => ucfirst($p), 'detail' => null, 'url' => route('admin.evenements.show', $evenement)], $problemes));
        }

        $paiements = Statistiques::paiementsAVerifier();
        $groupes[] = $this->groupe('paiements', 'Paiements à vérifier', 'Argent reçu sur une commande qui ne le reflète pas : à confirmer ou à rembourser.',
            $paiements->map(fn ($a) => [
                'libelle' => "Commande n° {$a->paiement->id_commande} · ".Format::euros($a->paiement->montant),
                'detail' => 'Reçu le '.$a->paiement->date_paiement?->format('d/m/Y').' : '.$a->motif,
                'url' => route('admin.commandes.show', $a->paiement->commande),
            ])->all());

        if ($evenement) {
            $courses = $this->stats->courses($evenement);

            $groupes[] = $this->groupe('quota', 'Quota dépassé', 'Un paiement est arrivé après la fin de la réservation : augmente le quota ou contacte les personnes concernées.',
                $courses->filter(fn ($c) => $c->quota && $c->places_prises > $c->quota)->map(fn ($c) => [
                    'libelle' => $c->course->nom,
                    'detail' => "{$c->places_prises} places prises pour {$c->quota} prévues",
                    'url' => route('admin.courses.edit', $c->course),
                ])->values()->all());

            $groupes[] = $this->groupe('dossards', 'Dossards', 'Plage de dossards pleine ou absente : les inscriptions validées restent sans numéro.',
                $courses->filter(fn ($c) => $c->dossards['sans_dossard'] > 0 || ($c->dossards['libres'] !== null && $c->dossards['libres'] === 0))->map(fn ($c) => [
                    'libelle' => $c->course->nom,
                    'detail' => ($c->dossards['plage'] === null ? 'Aucune plage définie' : "Plage {$c->course->dossard_debut}–{$c->course->dossard_fin} pleine")
                        .($c->dossards['sans_dossard'] > 0 ? " · {$c->dossards['sans_dossard']} inscription(s) validée(s) sans dossard" : ''),
                    'url' => route('admin.courses.edit', $c->course),
                ])->values()->all());

            $incomplets = Inscription::with('participant.user', 'tarif.course', 'documents.questionnaireSante', 'documents.consentement.texteLegal', 'evenement')
                ->where('id_evenement', $evenement->id_evenement)
                ->where('statut', Inscription::DOSSIER_INCOMPLET);
            $total = (clone $incomplets)->count();
            $groupes[] = $this->groupe('incomplets', 'Dossiers incomplets', 'Payés, mais un document manque : pas de dossard tant que le dossier n\'est pas complet.',
                $incomplets->orderBy('id_inscription')->limit(self::MAX_PAR_GROUPE)->get()->map(fn (Inscription $i) => [
                    'libelle' => $i->participant->user->name.' · '.$i->tarif->course->nom,
                    'detail' => 'Manque : '.(implode(', ', $i->documentsManquants()) ?: 'à vérifier'),
                    'url' => route('admin.inscriptions.show', $i),
                ])->all(), $total, route('admin.inscriptions.index', ['statut' => Inscription::DOSSIER_INCOMPLET]));

            if ($admin->peutAdmin(Admin::GESTION)) {
                $sante = $this->questionnairesAVerifier($evenement);
                $groupes[] = $this->groupe('sante', 'Questionnaires de santé à vérifier', 'À lire puis marquer « vérifié ». Une réponse « oui » ne bloque pas l\'inscription : contacte la famille si besoin.',
                    $sante->take(self::MAX_PAR_GROUPE)->map(fn (QuestionnaireSante $q) => [
                        'libelle' => $q->document->inscription->participant->user->name.' · '.$q->document->inscription->tarif->course->nom,
                        'detail' => $q->nb_oui > 0 ? "{$q->nb_oui} réponse(s) « oui »" : 'Aucune réponse « oui »',
                        'url' => route('admin.questionnaires.show', $q->document),
                    ])->values()->all(), $sante->count(), route('admin.questionnaires.index'));
            }
        }

        return array_values(array_filter($groupes, fn ($g) => $g['nombre'] > 0));
    }

    /**
     * Nombre affiché à côté de « À traiter » dans la barre : sans déchiffrer les questionnaires
     * ni charger les listes, pour rester léger sur chaque page.
     *
     * @return array{total: int, sante: int}
     */
    public function compteurs(?Evenement $evenement, User $admin): array
    {
        $total = Statistiques::paiementsAVerifier()->count();
        $sante = 0;

        if ($evenement) {
            if ($evenement->statut === Evenement::PUBLIE && $evenement->fermeture->isFuture()) {
                $total += count($this->preparation->problemes($evenement));
            }

            $courses = $this->stats->courses($evenement);
            $total += $courses->filter(fn ($c) => $c->quota && $c->places_prises > $c->quota)->count();
            $total += $courses->filter(fn ($c) => $c->dossards['sans_dossard'] > 0 || ($c->dossards['libres'] !== null && $c->dossards['libres'] === 0))->count();
            $total += Inscription::where('id_evenement', $evenement->id_evenement)->where('statut', Inscription::DOSSIER_INCOMPLET)->count();

            if ($admin->peutAdmin(Admin::GESTION)) {
                $sante = $this->requeteSante($evenement)->count();
                $total += $sante;
            }
        }

        return ['total' => $total, 'sante' => $sante];
    }

    private function questionnairesAVerifier(Evenement $evenement)
    {
        return $this->requeteSante($evenement)
            ->with('document.inscription.participant.user', 'document.inscription.tarif.course', 'reponses')
            ->orderBy('id_document')
            ->get()
            // Réponses chiffrées : le compte des « oui » se fait après déchiffrement ; les « oui » d'abord.
            ->each(fn (QuestionnaireSante $q) => $q->setAttribute('nb_oui', $q->reponses->where('reponse', 'oui')->count()))
            ->sortByDesc('nb_oui');
    }

    private function requeteSante(Evenement $evenement)
    {
        return QuestionnaireSante::where('type_questionnaire', QuestionSante::SANTE)
            ->whereHas('document', fn ($d) => $d->where('statut', '!=', QuestionnaireController::VERIFIE))
            ->whereHas('document.inscription', fn ($q) => $q->where('id_evenement', $evenement->id_evenement)->where('statut', '!=', Inscription::ANNULEE));
    }

    private function groupe(string $cle, string $titre, string $aide, array $elements, ?int $nombre = null, ?string $voirTout = null): array
    {
        $nombre ??= count($elements);

        return [
            'cle' => $cle,
            'titre' => $titre,
            'aide' => $aide,
            'nombre' => $nombre,
            'elements' => $elements,
            'voir_tout' => $nombre > count($elements) ? $voirTout : null,
        ];
    }
}
