<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Paiement;
use App\Models\Remboursement;
use App\Models\Tarif;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Données de la page Statistiques, calculées côté serveur (le navigateur ne fait que dessiner).
 * Une inscription compte à la date de sa commande, une fois réglée (validée ou dossier
 * incomplet) : les brouillons et les paiements en attente ne sont pas des inscrits.
 */
class Graphiques
{
    /** Jours pris en compte pour le rythme des inscriptions (projection du remplissage). */
    public const RYTHME_JOURS = 14;

    public const TRANCHES_AGE = ['Moins de 12 ans' => [0, 11], '12 à 17 ans' => [12, 17], '18 à 29 ans' => [18, 29], '30 à 39 ans' => [30, 39], '40 à 49 ans' => [40, 49], '50 à 59 ans' => [50, 59], '60 ans et plus' => [60, 200]];

    public const SEXES = ['femme' => 'Femmes', 'homme' => 'Hommes', 'autre' => 'Autre', null => 'Non renseigné'];

    /**
     * Inscrits cumulés jour par jour, de l'ouverture à aujourd'hui (ou à la fermeture), avec les
     * dates qui expliquent la courbe : ouverture, changements de tarif, relances envoyées.
     *
     * @return array{jours: list<string>, cumul: list<int>, parJour: list<int>, jalons: list<array{jour: string, libelle: string}>}
     */
    public function cumul(Evenement $evenement): array
    {
        $debut = CarbonImmutable::parse($evenement->ouverture)->startOfDay();
        $fin = CarbonImmutable::parse(min(now(), $evenement->fermeture))->startOfDay();

        $parDate = $this->reglees($evenement)
            ->join('commandes', 'commandes.id_commande', '=', 'inscriptions.id_commande')
            ->selectRaw('date(commandes.date_commande) as jour, count(*) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        // Inscriptions passées avant l'ouverture publique (inscriptions gratuites admin) : au premier jour.
        $avant = $parDate->filter(fn ($n, $jour) => $jour < $debut->toDateString())->sum();

        $jours = $cumul = $parJour = [];
        $total = $avant;
        if ($debut <= $fin) {
            foreach (CarbonPeriod::create($debut, $fin) as $jour) {
                $cle = $jour->toDateString();
                $n = (int) ($parDate[$cle] ?? 0) + ($cle === $debut->toDateString() ? $avant : 0);
                $total += (int) ($parDate[$cle] ?? 0);
                $jours[] = $cle;
                $parJour[] = $n;
                $cumul[] = $total;
            }
        }

        $jalons = collect([['jour' => $debut->toDateString(), 'libelle' => 'Ouverture']]);

        Tarif::whereIn('id_course', $evenement->courses()->select('id_course'))
            ->whereNotNull('valide_du')
            ->whereBetween('valide_du', [$debut->addDay(), $fin])
            ->get()
            ->groupBy(fn (Tarif $t) => $t->valide_du->toDateString())
            ->each(fn ($tarifs, $jour) => $jalons->push(['jour' => $jour, 'libelle' => 'Tarif « '.$tarifs->first()->libelle.' »']));

        JournalAdmin::where('action', 'like', 'relance.%')
            ->whereBetween('created_at', [$debut, $fin->endOfDay()])
            ->get()
            ->groupBy(fn ($j) => $j->created_at->toDateString())
            ->each(fn ($envois, $jour) => $jalons->push(['jour' => $jour, 'libelle' => count($envois) > 1 ? count($envois).' relances' : 'Relance']));

        return [
            'jours' => $jours,
            'cumul' => $cumul,
            'parJour' => $parJour,
            'jalons' => $jalons->sortBy('jour')->values()->all(),
        ];
    }

    /**
     * Remplissage des courses à quota, et date à laquelle elles seraient complètes au rythme des
     * 14 derniers jours (aucune projection sans inscription récente, ou au-delà de la fermeture).
     *
     * @return list<array{course: string, places: int, quota: int, pourcentage: int, rythme: float, complete_le: ?string}>
     */
    public function remplissage(Evenement $evenement): array
    {
        return $evenement->courses()->orderBy('heure_depart')->get()
            ->filter(fn (Course $c) => $c->quota)
            ->map(function (Course $c) use ($evenement) {
                $places = $c->placesPrises();
                $recentes = Inscription::whereIn('id_tarif', $c->tarifs()->select('id_tarif'))
                    ->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])
                    ->whereHas('commande', fn ($q) => $q->where('date_commande', '>=', now()->subDays(self::RYTHME_JOURS)))
                    ->count();
                $rythme = round($recentes / self::RYTHME_JOURS, 2);
                $reste = $c->quota - $places;

                $completeLe = null;
                if ($reste <= 0) {
                    $completeLe = 'complète';
                } elseif ($rythme > 0) {
                    $date = today()->addDays((int) ceil($reste / $rythme));
                    $completeLe = $date->lte($evenement->fermeture) ? $date->translatedFormat('j F Y') : null;
                }

                return [
                    'course' => $c->nom,
                    'places' => $places,
                    'quota' => $c->quota,
                    'pourcentage' => min(100, (int) round($places * 100 / $c->quota)),
                    'rythme' => $rythme,
                    'complete_le' => $completeLe,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Entonnoir du tunnel : inscriptions commencées, paiement lancé, réglées, validées. Les
     * annulées comptent dans « commencées » seulement.
     *
     * @return list<array{etape: string, nombre: int}>
     */
    public function entonnoir(Evenement $evenement): array
    {
        $toutes = Inscription::where('id_evenement', $evenement->id_evenement);

        return [
            ['etape' => 'Commencées', 'nombre' => (clone $toutes)->count()],
            ['etape' => 'Paiement lancé', 'nombre' => (clone $toutes)->where(fn ($q) => $q->whereHas('commande.paiements')
                ->orWhereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET]))->count()],
            ['etape' => 'Réglées', 'nombre' => (clone $toutes)->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])->count()],
            ['etape' => 'Validées', 'nombre' => (clone $toutes)->where('statut', Inscription::VALIDEE)->count()],
        ];
    }

    /**
     * Recettes par semaine (lundi) : inscriptions et dons des commandes payées, remboursements
     * et frais Stripe, de la première à la dernière semaine avec un mouvement.
     *
     * @return list<array{semaine: string, inscriptions: float, dons: float, rembourse: float, frais: float}>
     */
    public function recettes(Evenement $evenement): array
    {
        $commandes = Statistiques::commandes($evenement)->where('statut', Commande::PAYEE)->select('id_commande');
        $semaine = fn ($date) => CarbonImmutable::parse($date)->startOfWeek()->toDateString();

        $lignes = collect();
        $ajouter = function (string $sem, string $cle, float $montant) use ($lignes) {
            $ligne = $lignes->get($sem, ['semaine' => $sem, 'inscriptions' => 0.0, 'dons' => 0.0, 'rembourse' => 0.0, 'frais' => 0.0]);
            $ligne[$cle] += $montant;
            $lignes->put($sem, $ligne);
        };

        Inscription::where('id_evenement', $evenement->id_evenement)
            ->whereIn('id_commande', $commandes)
            ->with('commande:id_commande,date_commande')
            ->get(['id_inscription', 'id_commande', 'prix_paye'])
            ->each(fn ($i) => $ajouter($semaine($i->commande->date_commande), 'inscriptions', (float) $i->prix_paye));

        Statistiques::dons($evenement)->with('commande:id_commande,date_commande')->get()
            ->each(fn (Don $d) => $ajouter($semaine($d->commande->date_commande), 'dons', (float) $d->montant));

        Paiement::whereIn('id_commande', Statistiques::commandes($evenement)->select('id_commande'))
            ->where('statut', Paiement::REUSSI)->whereNotNull('frais')->whereNotNull('date_paiement')
            ->get()
            ->each(fn (Paiement $p) => $ajouter($semaine($p->date_paiement), 'frais', (float) $p->frais));

        Remboursement::whereHas('paiement', fn ($p) => $p->whereIn('id_commande', Statistiques::commandes($evenement)->select('id_commande')))
            ->get()
            ->each(fn (Remboursement $r) => $ajouter($semaine($r->date_remboursement), 'rembourse', (float) $r->montant));

        if ($lignes->isEmpty()) {
            return [];
        }

        // Semaines sans mouvement : à zéro, pour une courbe régulière.
        $premiere = CarbonImmutable::parse($lignes->keys()->min());
        $derniere = CarbonImmutable::parse($lignes->keys()->max());
        $resultat = [];
        for ($s = $premiere; $s <= $derniere; $s = $s->addWeek()) {
            $resultat[] = $lignes->get($s->toDateString(), ['semaine' => $s->toDateString(), 'inscriptions' => 0.0, 'dons' => 0.0, 'rembourse' => 0.0, 'frais' => 0.0]);
        }

        return array_map(fn ($l) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $l), $resultat);
    }

    /**
     * Inscrits réglés par tranche d'âge (âge le jour de l'événement) et par sexe, et par course.
     *
     * @return array{ages: array<string, array<string, int>>, courses: array<string, int>}
     */
    public function repartition(Evenement $evenement): array
    {
        $inscrits = $this->reglees($evenement)->with('participant', 'tarif.course')->get();

        $ages = [];
        foreach (array_keys(self::TRANCHES_AGE) as $tranche) {
            $ages[$tranche] = array_fill_keys(array_values(self::SEXES), 0);
        }

        foreach ($inscrits as $i) {
            $age = $i->participant?->date_naissance?->diffInYears($evenement->date_evenement);
            $tranche = collect(self::TRANCHES_AGE)->search(fn ($b) => $age !== null && $age >= $b[0] && $age <= $b[1]);
            if ($tranche !== false) {
                $ages[$tranche][self::SEXES[$i->participant->sexe] ?? self::SEXES[null]]++;
            }
        }

        return [
            'ages' => $ages,
            'courses' => $inscrits->groupBy(fn ($i) => $i->tarif->course->nom)->map->count()->sortDesc()->all(),
        ];
    }

    /**
     * Inscrits réglés par département (deux premiers chiffres du code postal, trois en outre-mer),
     * les 10 premiers puis « Autres » ; hors de France, « Étranger ».
     *
     * @return array<string, int>
     */
    public function departements(Evenement $evenement): array
    {
        $compte = $this->reglees($evenement)->with('participant')->get()
            ->map(function ($i) {
                $p = $i->participant;
                if ($p && $p->pays && mb_strtolower($p->pays) !== 'france') {
                    return 'Étranger';
                }
                $cp = preg_replace('/\D/', '', (string) $p?->code_postal);
                if (strlen($cp) !== 5) {
                    return 'Non renseigné';
                }

                return str_starts_with($cp, '97') || str_starts_with($cp, '98') ? substr($cp, 0, 3) : (str_starts_with($cp, '20') ? 'Corse (20)' : substr($cp, 0, 2));
            })
            ->countBy()
            ->sortDesc();

        $premiers = $compte->except(['Non renseigné', 'Étranger'])->take(10);
        $autres = $compte->except(['Non renseigné', 'Étranger'])->skip(10)->sum();

        return $premiers
            ->when($autres > 0, fn (Collection $c) => $c->put('Autres', $autres))
            ->when(isset($compte['Étranger']), fn (Collection $c) => $c->put('Étranger', $compte['Étranger']))
            ->when(isset($compte['Non renseigné']), fn (Collection $c) => $c->put('Non renseigné', $compte['Non renseigné']))
            ->all();
    }

    /**
     * Inscriptions réglées par jour de la semaine et tranche de deux heures (heure de la commande).
     *
     * @return array{jours: list<string>, tranches: list<string>, valeurs: list<list<int>>, max: int}
     */
    public function moments(Evenement $evenement): array
    {
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $valeurs = array_fill(0, 7, array_fill(0, 12, 0));

        $this->reglees($evenement)->with('commande:id_commande,date_commande')->get(['id_inscription', 'id_commande'])
            ->each(function ($i) use (&$valeurs) {
                $date = CarbonImmutable::parse($i->commande->date_commande);
                $valeurs[$date->dayOfWeekIso - 1][intdiv($date->hour, 2)]++;
            });

        return [
            'jours' => $jours,
            'tranches' => array_map(fn ($h) => sprintf('%dh', $h * 2), range(0, 11)),
            'valeurs' => $valeurs,
            'max' => max(1, max(array_map('max', $valeurs))),
        ];
    }

    /** Inscriptions réglées (validées ou dossier incomplet) de l'événement. */
    private function reglees(Evenement $evenement): Builder
    {
        return Inscription::query()
            ->where('inscriptions.id_evenement', $evenement->id_evenement)
            ->whereIn('inscriptions.statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET]);
    }
}
