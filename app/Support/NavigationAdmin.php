<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Evenement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Barre latérale du back-office : cinq sections par usage, chaque rubrique avec le niveau
 * d'accès qui l'ouvre. Une rubrique dont la route n'existe pas encore est masquée, une
 * section sans rubrique visible aussi. Sert aussi au fil d'Ariane (section › rubrique › page).
 */
class NavigationAdmin
{
    /**
     * @return list<array{section: string, rubriques: list<array{libelle: string, url: string, actif: bool, compteur: ?int}>}>
     */
    public static function sections(Request $request, User $admin, ?Evenement $evenement, array $compteurs = []): array
    {
        $sections = [];

        foreach (self::definition($evenement) as $section => $rubriques) {
            $visibles = collect($rubriques)
                ->filter(fn ($r) => Route::has($r['route']) && $admin->peutAdmin($r['niveau']))
                ->map(fn ($r) => [
                    'libelle' => $r['libelle'],
                    'url' => route($r['route'], $r['parametres'] ?? []),
                    'actif' => self::estActive($request, $r),
                    'compteur' => $compteurs[$r['route']] ?? null,
                ])
                ->values()
                ->all();

            if ($visibles) {
                $sections[] = ['section' => $section, 'rubriques' => $visibles];
            }
        }

        return $sections;
    }

    /**
     * Fil d'Ariane de la page : section, rubrique (en lien si on est plus bas), titre de la page.
     *
     * @return list<array{libelle: string, url: ?string}>
     */
    public static function fil(array $sections, string $titre): array
    {
        foreach ($sections as $s) {
            foreach ($s['rubriques'] as $r) {
                if (! $r['actif']) {
                    continue;
                }

                $fil = [['libelle' => $s['section'], 'url' => null], ['libelle' => $r['libelle'], 'url' => $r['url']]];

                if ($titre !== $r['libelle']) {
                    $fil[] = ['libelle' => $titre, 'url' => null];
                }

                return $fil;
            }
        }

        return [];
    }

    /** Rubriques par section, dans l'ordre de la barre. */
    private static function definition(?Evenement $evenement): array
    {
        $textes = $evenement ? ['evenement' => $evenement->id_evenement, 'onglet' => 'textes'] : [];

        return [
            'Pilotage' => [
                ['libelle' => 'Tableau de bord', 'route' => 'admin.tableau', 'motifs' => ['admin.tableau'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'À traiter', 'route' => 'admin.a-traiter', 'motifs' => ['admin.a-traiter'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'Statistiques', 'route' => 'admin.statistiques', 'motifs' => ['admin.statistiques'], 'niveau' => Admin::LECTURE],
            ],
            'Participants' => [
                ['libelle' => 'Inscriptions', 'route' => 'admin.inscriptions.index', 'motifs' => ['admin.inscriptions.*'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'Questionnaires de santé', 'route' => 'admin.questionnaires.index', 'motifs' => ['admin.questionnaires.*'], 'niveau' => Admin::GESTION],
                ['libelle' => 'Relances', 'route' => 'admin.relances.index', 'motifs' => ['admin.relances.*'], 'niveau' => Admin::GESTION],
                ['libelle' => 'Comptes', 'route' => 'admin.utilisateurs.index', 'motifs' => ['admin.utilisateurs.*'], 'niveau' => Admin::SUPER],
                ['libelle' => 'Retrait des dossards', 'route' => 'admin.retrait.index', 'motifs' => ['admin.retrait.*'], 'niveau' => Admin::GESTION],
            ],
            'Événement' => [
                ['libelle' => 'Courses et tarifs', 'route' => 'admin.evenements.index', 'motifs' => ['admin.evenements.*', 'admin.courses.*', 'admin.tarifs.*', 'admin.options.*'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'Textes et documents', 'route' => $evenement ? 'admin.evenements.show' : 'admin.evenements.index', 'parametres' => $textes, 'motifs' => ['admin.textes.*'], 'onglet' => 'textes', 'niveau' => Admin::LECTURE],
                ['libelle' => 'Exports et listes', 'route' => 'admin.exports.index', 'motifs' => ['admin.exports.*'], 'niveau' => Admin::LECTURE],
            ],
            'Finances' => [
                ['libelle' => 'Commandes et paiements', 'route' => 'admin.commandes.index', 'motifs' => ['admin.commandes.*'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'Remboursements', 'route' => 'admin.remboursements.index', 'motifs' => ['admin.remboursements.*'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'Dons et reçus', 'route' => 'admin.dons.index', 'motifs' => ['admin.dons.*'], 'niveau' => Admin::LECTURE],
            ],
            'Administration' => [
                ['libelle' => 'Admins et accès', 'route' => 'admin.admins.index', 'motifs' => ['admin.admins.*'], 'niveau' => Admin::SUPER],
                ['libelle' => 'Journal', 'route' => 'admin.journal', 'motifs' => ['admin.journal'], 'niveau' => Admin::LECTURE],
                ['libelle' => 'État technique', 'route' => 'admin.technique', 'motifs' => ['admin.technique'], 'niveau' => Admin::SUPER],
            ],
        ];
    }

    /** Rubrique de la page courante ; l'onglet « textes » de l'événement appartient à « Textes et documents ». */
    private static function estActive(Request $request, array $rubrique): bool
    {
        $ongletTextes = $request->routeIs('admin.evenements.show') && $request->query('onglet') === 'textes';

        if (isset($rubrique['onglet'])) {
            return $ongletTextes || $request->routeIs(...$rubrique['motifs']);
        }

        return ! ($ongletTextes && $rubrique['route'] === 'admin.evenements.index') && $request->routeIs(...$rubrique['motifs']);
    }

    /** Événements proposés dans le sélecteur, le plus récent d'abord. */
    public static function evenements(): Collection
    {
        return Evenement::orderByDesc('date_evenement')->get();
    }
}
