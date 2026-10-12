<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\InscriptionDeclinaison;
use App\Models\Paiement;
use App\Models\Remboursement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chiffres du tableau de bord admin pour un événement. Une inscription compte dans les
 * recettes dès qu'elle est réglée (validée ou dossier incomplet) ; les gratuites comptent
 * dans les inscrits, pour 0 €.
 */
class Statistiques
{
    public const REGLEES = [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET];

    /** @return array<string, int> nombre d'inscriptions par statut (hors brouillons) */
    public function inscrits(Evenement $evenement): array
    {
        $parStatut = $evenement->inscriptions()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            Inscription::VALIDEE => (int) ($parStatut[Inscription::VALIDEE] ?? 0),
            Inscription::DOSSIER_INCOMPLET => (int) ($parStatut[Inscription::DOSSIER_INCOMPLET] ?? 0),
            Inscription::EN_ATTENTE_PAIEMENT => (int) ($parStatut[Inscription::EN_ATTENTE_PAIEMENT] ?? 0),
            Inscription::ANNULEE => (int) ($parStatut[Inscription::ANNULEE] ?? 0),
        ];
    }

    /** @return array{inscriptions: float, options: float, dons: float, total: float, rembourse: float, frais: float} */
    public function recettes(Evenement $evenement): array
    {
        $inscriptions = (float) $evenement->inscriptions()->whereIn('statut', self::REGLEES)->sum('prix_paye');

        $options = (float) InscriptionDeclinaison::query()
            ->whereIn('id_inscription', $this->reglees($evenement)->select('id_inscription'))
            ->selectRaw('coalesce(sum(prix_unitaire * quantite), 0) as total')
            ->value('total');

        $dons = (float) self::dons($evenement)->sum('montant');

        $commandes = self::commandes($evenement)->pluck('id_commande');

        $rembourse = (float) Remboursement::whereHas('paiement', fn ($q) => $q->whereIn('id_commande', $commandes))->sum('montant');

        $frais = (float) Paiement::whereIn('id_commande', $commandes)->where('statut', Paiement::REUSSI)->sum('frais');

        return [
            'inscriptions' => $inscriptions,
            'options' => $options,
            'dons' => $dons,
            'total' => $inscriptions + $options + $dons,
            'rembourse' => $rembourse,
            'frais' => $frais,
        ];
    }

    /**
     * Options vendues par taille. Le stock est la quantité mise en vente (null = illimité) :
     * le restant s'en déduit, les ventes ne le décrémentent pas.
     *
     * @return Collection<int, object{option: string, taille: string, vendus: int, stock: ?int, restant: ?int}>
     */
    public function tshirts(Evenement $evenement): Collection
    {
        $vendus = InscriptionDeclinaison::query()
            ->whereIn('id_inscription', $this->reglees($evenement)->select('id_inscription'))
            ->selectRaw('id_declinaison, sum(quantite) as total')
            ->groupBy('id_declinaison')
            ->pluck('total', 'id_declinaison');

        return $evenement->optionEvenements()->with('declinaisons')->get()
            ->flatMap(fn ($option) => $option->declinaisons->map(fn ($d) => (object) [
                'option' => $option->libelle,
                'taille' => $d->libelle,
                'vendus' => (int) ($vendus[$d->id_declinaison] ?? 0),
                'stock' => $d->stock,
                'restant' => $d->stock === null ? null : max($d->stock - (int) ($vendus[$d->id_declinaison] ?? 0), 0),
            ]));
    }

    /**
     * Répartition par course : inscrits réglés, en attente, quota et jauge, dossards.
     *
     * @return Collection<int, object>
     */
    public function courses(Evenement $evenement): Collection
    {
        return $evenement->courses()->orderBy('heure_depart')->get()->map(function (Course $course) {
            $parStatut = Inscription::whereIn('id_tarif', $course->tarifs()->select('id_tarif'))
                ->selectRaw('statut, count(*) as total')
                ->groupBy('statut')
                ->pluck('total', 'statut');

            $reglees = (int) ($parStatut[Inscription::VALIDEE] ?? 0) + (int) ($parStatut[Inscription::DOSSIER_INCOMPLET] ?? 0);
            $places = $course->placesPrises();

            return (object) [
                'course' => $course,
                'reglees' => $reglees,
                'validees' => (int) ($parStatut[Inscription::VALIDEE] ?? 0),
                'en_attente' => (int) ($parStatut[Inscription::EN_ATTENTE_PAIEMENT] ?? 0),
                'places_prises' => $places,
                'quota' => $course->quota,
                'remplissage' => $course->quota ? min(100, (int) round($places * 100 / $course->quota)) : null,
                'dossards' => $this->dossards($course),
            ];
        });
    }

    /**
     * Occupation de la plage de dossards d'une course. Plage pleine (ou absente) avec des
     * inscriptions validées sans dossard : alerte admin (spec).
     *
     * @return array{plage: ?int, attribues: int, libres: ?int, sans_dossard: int}
     */
    public function dossards(Course $course): array
    {
        $tarifs = $course->tarifs()->select('id_tarif');
        $plage = $course->dossard_debut !== null && $course->dossard_fin !== null
            ? $course->dossard_fin - $course->dossard_debut + 1
            : null;

        $attribues = $plage === null ? 0 : Inscription::where('id_evenement', $course->id_evenement)
            ->whereBetween('dossard', [$course->dossard_debut, $course->dossard_fin])
            ->count();

        return [
            'plage' => $plage,
            'attribues' => $attribues,
            'libres' => $plage === null ? null : max($plage - $attribues, 0),
            'sans_dossard' => Inscription::whereIn('id_tarif', $tarifs)
                ->where('statut', Inscription::VALIDEE)
                ->whereNull('dossard')
                ->count(),
        ];
    }

    /**
     * Dons d'un événement (commandes payées) : ceux d'une commande qui contient une inscription
     * à l'événement, et les dons seuls passés avant lui depuis l'édition précédente (pour la
     * dernière édition, aussi ceux passés après).
     */
    public static function dons(Evenement $evenement): Builder
    {
        $precedent = Evenement::where('date_evenement', '<', $evenement->date_evenement)->max('date_evenement');
        $estDernier = ! Evenement::where('date_evenement', '>', $evenement->date_evenement)->exists();

        return Don::query()->whereHas('commande', function ($commande) use ($evenement, $precedent, $estDernier) {
            $commande->where('statut', Commande::PAYEE)->where(function ($q) use ($evenement, $precedent, $estDernier) {
                $q->whereHas('inscriptions', fn ($i) => $i->where('id_evenement', $evenement->id_evenement))
                    ->orWhere(function ($seul) use ($evenement, $precedent, $estDernier) {
                        $seul->whereDoesntHave('inscriptions')
                            ->when($precedent, fn ($d) => $d->whereDate('date_commande', '>', $precedent))
                            ->when(! $estDernier, fn ($d) => $d->whereDate('date_commande', '<=', $evenement->date_evenement));
                    });
            });
        });
    }

    private function reglees(Evenement $evenement): Builder
    {
        return Inscription::where('id_evenement', $evenement->id_evenement)->whereIn('statut', self::REGLEES);
    }

    /** Commandes liées à l'événement (par leurs inscriptions ou leur don). */
    public static function commandes(Evenement $evenement): Builder
    {
        return Commande::query()->where(function ($q) use ($evenement) {
            $q->whereHas('inscriptions', fn ($i) => $i->where('id_evenement', $evenement->id_evenement))
                ->orWhereIn('id_commande', self::dons($evenement)->select('id_commande'));
        });
    }
}
