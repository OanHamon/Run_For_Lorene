<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\TexteLegal;
use App\Services\Admin\ControleTarifs;
use App\Services\Admin\DupliquerEvenement;
use App\Services\Admin\Journal;
use App\Services\Admin\PreparationEvenement;
use App\Services\Inscriptions\Consentements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Paramétrage des événements (lot 2) : l'événement et ses onglets (courses et tarifs,
 * options et tailles, textes légaux). Lecture dès le niveau 1, écriture au niveau 3.
 */
class EvenementController extends Controller
{
    /** Libellés du formulaire, plus explicites que Evenement::LIBELLES. */
    public const STATUTS = [
        Evenement::BROUILLON => 'Brouillon (invisible du public)',
        Evenement::PUBLIE => 'Publié (sur le site)',
        Evenement::CLOTURE => 'Clôturé (édition terminée, retirée du site)',
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
        return view('admin.evenements.form', ['evenement' => new Evenement(['statut' => Evenement::BROUILLON])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $evenement = Evenement::create($this->valider($request));
        $this->journal->noter('evenement.creation', $evenement, ['nom' => $evenement->nom]);

        return redirect()->route('admin.evenements.show', [$evenement, 'onglet' => 'courses'])
            ->with('statut', 'Événement créé. Ajoute maintenant ses courses.');
    }

    public function show(Request $request, Evenement $evenement, Consentements $consentements, ControleTarifs $controle, PreparationEvenement $preparation): View
    {
        $onglets = config('rfl.options') ? self::ONGLETS : array_diff_key(self::ONGLETS, ['options' => true]);
        $onglet = array_key_exists($request->query('onglet'), $onglets) ? $request->query('onglet') : 'general';

        $evenement->load([
            'courses' => fn ($q) => $q->orderBy('heure_depart')->with(['tarifs' => fn ($t) => $t->orderBy('valide_du')]),
            'optionEvenements.declinaisons',
            'texteLegals' => fn ($q) => $q->orderBy('type_texte')->orderByDesc('version'),
        ]);

        return view('admin.evenements.show', [
            'evenement' => $evenement,
            'onglet' => $onglet,
            'onglets' => $onglets,
            // Nombre de trous de tarifs par course (badge « Tarifs à compléter »).
            'trous' => $evenement->courses->mapWithKeys(fn ($c) => [$c->id_course => $controle->trous($c->setRelation('evenement', $evenement))->count()]),
            // Checklist « Prêt à publier ? », tant que l'événement est en brouillon.
            'preparation' => $evenement->statut === Evenement::BROUILLON ? $preparation->etapes($evenement) : null,
            'textesEnVigueur' => $consentements->textes($evenement),
            'typesTextes' => TexteLegalController::TYPES,
        ]);
    }

    public function update(Request $request, Evenement $evenement, PreparationEvenement $preparation): RedirectResponse
    {
        $evenement->fill($this->valider($request, $evenement));
        $modifs = $evenement->getDirty();

        // Avec des inscrits : la date ne bouge plus (les âges, donc les règles d'âge et le
        // questionnaire de santé, en dépendent) et on ne dépublie plus.
        if ($evenement->aDesInscrits()) {
            $erreurs = [];
            if ($evenement->isDirty('date_evenement')) {
                $erreurs['date_evenement'] = ["L'événement a déjà des inscrits : sa date ne peut plus changer (les âges en dépendent)."];
            }
            if ($evenement->isDirty('statut') && $evenement->statut === Evenement::BROUILLON) {
                $erreurs['statut'] = ["L'événement a déjà des inscrits : il ne peut plus repasser en brouillon. Pour arrêter les inscriptions, avance la date de fermeture."];
            }
            if ($erreurs) {
                throw ValidationException::withMessages($erreurs);
            }
        }

        // Publication : courses, tarifs sans trou et textes obligatoires (voir PreparationEvenement).
        if ($evenement->statut === Evenement::PUBLIE && $evenement->getOriginal('statut') !== Evenement::PUBLIE) {
            $problemes = $preparation->problemes($evenement);

            if ($problemes) {
                throw ValidationException::withMessages(['statut' => ['Publication impossible : '.implode(' ; ', $problemes).'.']]);
            }
        }

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

    /**
     * Pas d'événement dans le passé : la date doit être aujourd'hui ou plus tard à la création,
     * et quand on la change. Une édition passée reste modifiable (pour la clôturer) tant qu'on
     * ne touche pas à sa date. Les inscriptions ferment au plus tard le jour de l'événement.
     */
    private function valider(Request $request, ?Evenement $evenement = null): array
    {
        $dateChangee = ! $evenement || $request->input('date_evenement') !== $evenement->date_evenement?->toDateString();
        // Lendemain de l'événement (la fermeture doit le précéder) ; null si la date saisie est invalide.
        $lendemain = $request->filled('date_evenement')
            ? rescue(fn () => Carbon::parse($request->input('date_evenement'))->addDay()->toDateString(), null, false)
            : null;

        return $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'date_evenement' => ['required', 'date', ...($dateChangee ? ['after_or_equal:today'] : [])],
            'ouverture' => ['required', 'date'],
            'fermeture' => ['required', 'date', 'after:ouverture', ...($lendemain ? ["before:{$lendemain}"] : [])],
            // Un nouvel événement n'a encore ni course ni tarif : il naît en brouillon (ou clôturé).
            'statut' => ['required', 'in:'.implode(',', array_keys(self::STATUTS)), ...($evenement ? [] : ['not_in:'.Evenement::PUBLIE])],
        ], [
            'statut.not_in' => 'Crée l\'événement en brouillon, ajoute ses courses et leurs tarifs, puis publie-le.',
            'date_evenement.after_or_equal' => "La date de l'événement ne peut pas être dans le passé.",
            'fermeture.before' => "Les inscriptions doivent fermer au plus tard le jour de l'événement.",
            'fermeture.after' => "La fermeture des inscriptions doit être postérieure à l'ouverture.",
        ]);
    }
}
