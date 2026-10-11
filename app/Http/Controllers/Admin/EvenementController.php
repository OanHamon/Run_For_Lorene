<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\TexteLegal;
use App\Services\Admin\DupliquerEvenement;
use App\Services\Admin\Journal;
use App\Services\Inscriptions\Consentements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Paramétrage des événements (lot 2) : l'événement et ses onglets (courses et tarifs,
 * options et tailles, textes légaux). Lecture dès le niveau 1, écriture au niveau 3.
 */
class EvenementController extends Controller
{
    public const STATUTS = [
        'brouillon' => 'Brouillon (invisible du public)',
        'publie' => 'Publié',
    ];

    public const ONGLETS = [
        'general' => 'Général',
        'courses' => 'Courses et tarifs',
        'options' => 'Options et tailles',
        'textes' => 'Textes légaux',
    ];

    public function __construct(private Journal $journal) {}

    public function index(): View
    {
        return view('admin.evenements.index', [
            'evenements' => Evenement::withCount(['courses', 'inscriptions' => fn ($q) => $q->where('statut', '!=', 'brouillon')])
                ->orderByDesc('date_evenement')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.evenements.form', ['evenement' => new Evenement(['statut' => 'brouillon'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $evenement = Evenement::create($this->valider($request));
        $this->journal->noter('evenement.creation', $evenement, ['nom' => $evenement->nom]);

        return redirect()->route('admin.evenements.show', [$evenement, 'onglet' => 'courses'])
            ->with('statut', 'Événement créé. Ajoute maintenant ses courses.');
    }

    public function show(Request $request, Evenement $evenement, Consentements $consentements): View
    {
        $onglet = array_key_exists($request->query('onglet'), self::ONGLETS) ? $request->query('onglet') : 'general';

        $evenement->load([
            'courses' => fn ($q) => $q->orderBy('heure_depart')->with(['tarifs' => fn ($t) => $t->orderBy('valide_du')]),
            'optionEvenements.declinaisons',
            'texteLegals' => fn ($q) => $q->orderBy('type_texte')->orderByDesc('version'),
        ]);

        return view('admin.evenements.show', [
            'evenement' => $evenement,
            'onglet' => $onglet,
            'textesEnVigueur' => $consentements->textes($evenement),
            'typesTextes' => TexteLegalController::TYPES,
        ]);
    }

    public function update(Request $request, Evenement $evenement): RedirectResponse
    {
        $evenement->fill($this->valider($request));
        $modifs = $evenement->getDirty();
        $evenement->save();

        if ($modifs) {
            $this->journal->noter('evenement.modification', $evenement, array_map(fn ($v) => (string) $v, $modifs));
        }

        return redirect()->route('admin.evenements.show', $evenement)->with('statut', 'Événement enregistré.');
    }

    public function dupliquer(Evenement $evenement, DupliquerEvenement $dupliquer): RedirectResponse
    {
        $copie = $dupliquer($evenement);
        $this->journal->noter('evenement.duplication', $copie, ['source' => $evenement->id_evenement]);

        return redirect()->route('admin.evenements.show', $copie)
            ->with('statut', "Événement dupliqué, avec les dates décalées d'un an. Il reste en brouillon : vérifie ses dates, tarifs et textes avant de le publier.");
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'date_evenement' => ['required', 'date'],
            'ouverture' => ['required', 'date'],
            'fermeture' => ['required', 'date', 'after:ouverture'],
            'statut' => ['required', 'in:'.implode(',', array_keys(self::STATUTS))],
        ]);
    }
}
