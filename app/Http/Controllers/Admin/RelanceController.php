<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Reglage;
use App\Models\Relance;
use App\Models\RelanceProgrammee;
use App\Services\Admin\Journal;
use App\Services\Admin\RelancesCiblees;
use App\Support\EvenementAdmin;
use App\Support\ModelesRelance;
use App\Support\ReglagesRelances;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Relances (niveau 2), pensées pour être prises en main sans habitude de l'informatique :
 * un assistant en quatre étapes (quel message, à qui, quand, vérifier), des choix en français
 * plutôt que des filtres, un aperçu personnalisé avant tout envoi.
 *
 * « Quand » : tout de suite, ou programmé (un jour et une heure précis, ou tous les N jours
 * sur une période). Le « N jours avant l'événement » a été retiré (trop abstrait, réunion du
 * 01/11/2026) : une ancienne relance de ce type se rouvre en date précise. Les deux relances automatiques du site (documents, paiement)
 * se règlent aussi ici.
 */
class RelanceController extends Controller
{
    public function __construct(private RelancesCiblees $relances, private Journal $journal) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.relances.index', [
            'evenement' => $evenement,
            'modeles' => ModelesRelance::tous(),
            'programmees' => $evenement
                ? RelanceProgrammee::with('evenement', 'admin')->where('id_evenement', $evenement->id_evenement)->orderByDesc('id_programmee')->get()
                : collect(),
            'documents' => ReglagesRelances::documents(),
            'relances' => $evenement
                ? Relance::with('admin')->where('id_evenement', $evenement->id_evenement)->orderByDesc('created_at')->paginate(20)
                : null,
        ]);
    }

    /** Assistant : l'état vient de l'URL (modèle choisi, recherche d'une personne…). */
    public function create(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        return $this->assistant($evenement, $this->etat($request->query()));
    }

    public function edit(Request $request, RelanceProgrammee $programmee): View
    {
        abort_if($programmee->etat() === 'terminee', 404);
        $evenement = $programmee->evenement;
        $cible = $programmee->cible;

        $etat = $this->etat([
            'modele' => $programmee->modele,
            'sujet' => $programmee->sujet,
            'message' => $programmee->message,
            'qui' => $cible['qui'] ?? 'tous',
            'course' => $cible['course'] ?? null,
            'inscription' => $cible['inscription'] ?? null,
            'quand' => $programmee->quand === 'avant' ? 'date' : $programmee->quand,
            'date_jour' => $programmee->quand === 'avant'
                ? $programmee->evenement->date_evenement->copy()->subDays((int) $programmee->jours_avant)->toDateString()
                : $programmee->date_envoi?->toDateString(),
            'heure' => substr((string) $programmee->heure, 0, 5),
            'tous_les' => $programmee->tous_les,
            'repetee_du' => $programmee->repetee_du?->toDateString(),
            'repetee_au' => $programmee->repetee_au?->toDateString(),
        ] + $request->query());

        return $this->assistant($evenement, $etat, $programmee);
    }

    public function apercu(Request $request): View
    {
        $programmee = $request->filled('programmee') ? RelanceProgrammee::findOrFail($request->integer('programmee')) : null;
        $evenement = $programmee?->evenement ?? EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $etat = $this->etat($request->all());
        $this->valider($request, $evenement, $etat);

        return $this->assistant($evenement, $etat, $programmee, apercu: true);
    }

    public function store(Request $request): RedirectResponse
    {
        $programmee = $request->filled('programmee') ? RelanceProgrammee::findOrFail($request->integer('programmee')) : null;
        $evenement = $programmee?->evenement ?? EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $etat = $this->etat($request->all());
        $this->valider($request, $evenement, $etat);
        $request->validate(['confirmation' => ['accepted']], ['confirmation.accepted' => 'Coche la case pour confirmer.']);
        $cible = RelancesCiblees::cible(ModelesRelance::cible($etat['qui'], $etat['course'], $etat['inscription']));

        if ($etat['quand'] === 'maintenant') {
            $relance = $this->relances->envoyer($evenement, $etat['modele'], $etat['sujet'], $etat['message'], $cible, $request->boolean('inclure_recents'), $request->user());

            return $relance->nb_destinataires === 0
                ? redirect()->route('admin.relances.index')->with('erreur', 'Aucun email envoyé : personne ne correspond.')
                : redirect()->route('admin.relances.index')->with('statut', "C'est parti : « ".ModelesRelance::lisible($relance->sujet, $evenement)." » est en cours d'envoi à {$relance->nb_destinataires} personne(s).");
        }

        $champs = $this->programmation($etat) + [
            'modele' => $etat['modele'],
            'sujet' => $etat['sujet'],
            'message' => $etat['message'],
            // « qui » est gardé avec la cible pour rouvrir l'assistant tel qu'il a été rempli.
            'cible' => $cible + ['qui' => $etat['qui']],
            'id_evenement' => $evenement->id_evenement,
        ];

        if ($programmee) {
            $programmee->update($champs);
            $this->journal->noter('relance.programmee_modifiee', $programmee, ['objet' => $programmee->sujet, 'quand' => $programmee->enClair()]);
        } else {
            $programmee = RelanceProgrammee::create($champs + ['id_admin' => $request->user()->id_user, 'actif' => true]);
            $this->journal->noter('relance.programmee', $programmee, ['objet' => $programmee->sujet, 'quand' => $programmee->enClair()]);
        }

        return redirect()->route('admin.relances.index')->with('statut', "C'est programmé : « ".ModelesRelance::lisible($programmee->sujet, $evenement).' », '.lcfirst($programmee->load('evenement')->enClair()).'.');
    }

    public function pause(RelanceProgrammee $programmee): RedirectResponse
    {
        $programmee->update(['actif' => ! $programmee->actif]);
        $this->journal->noter($programmee->actif ? 'relance.programmee_reprise' : 'relance.programmee_pause', $programmee, ['objet' => $programmee->sujet]);

        $sujet = ModelesRelance::lisible($programmee->sujet, $programmee->evenement);

        return back()->with('statut', $programmee->actif ? "« {$sujet} » reprend." : "« {$sujet} » est en pause : rien ne partira tant que tu ne la relances pas.");
    }

    public function destroy(RelanceProgrammee $programmee): RedirectResponse
    {
        $this->journal->noter('relance.programmee_supprimee', $programmee, ['objet' => $programmee->sujet]);
        $programmee->delete();

        return back()->with('statut', 'Envoi programmé supprimé.');
    }

    /** Réglages des deux relances automatiques du site. */
    public function reglages(Request $request): RedirectResponse
    {
        $d = $request->validateWithBag('reglages', [
            'documents_jours' => ['required', 'integer', 'min:1', 'max:90'],
            'documents_heure' => ['required', 'date_format:H:i'],
        ], [
            'documents_jours.*' => 'Indique un nombre de jours entre 1 et 90.',
            'documents_heure.*' => 'Indique une heure, par exemple 10:00.',
        ]);

        Reglage::ecrire('relance.documents', ['actif' => $request->boolean('documents_actif'), 'jours_avant' => (int) $d['documents_jours'], 'heure' => $d['documents_heure']]);
        $documents = ReglagesRelances::documents();
        $this->journal->noter('relance.reglages', null, [
            'documents' => $documents['actif'] ? "{$documents['jours_avant']} j avant, à {$documents['heure']}" : 'en pause',
        ]);

        return back()->with('statut', 'Relances automatiques enregistrées.');
    }

    /** État de l'assistant : ce qui est saisi, complété par le modèle choisi. */
    private function etat(array $entree): array
    {
        $cle = ModelesRelance::existe($entree['modele'] ?? null) ? $entree['modele'] : 'documents';
        $modele = ModelesRelance::tous()[$cle];
        $inscription = isset($entree['inscription']) && $entree['inscription'] !== '' ? (int) $entree['inscription'] : null;
        $qui = array_key_exists($entree['qui'] ?? '', ModelesRelance::QUI) ? $entree['qui'] : ($inscription ? 'personne' : $modele['qui']);

        return [
            'modele' => $cle,
            'sujet' => (string) ($entree['sujet'] ?? $modele['sujet']),
            'message' => (string) ($entree['message'] ?? $modele['message']),
            'qui' => $qui,
            'course' => isset($entree['course']) && $entree['course'] !== '' ? (int) $entree['course'] : null,
            'inscription' => $inscription,
            'chercher' => trim((string) ($entree['chercher'] ?? '')),
            'quand' => in_array($entree['quand'] ?? null, ['maintenant', 'date', 'repetee'], true) ? $entree['quand'] : 'maintenant',
            'date_jour' => $entree['date_jour'] ?? null,
            'heure' => $entree['heure'] ?? '10:00',
            'tous_les' => $entree['tous_les'] ?? 7,
            'repetee_du' => $entree['repetee_du'] ?? today()->toDateString(),
            'repetee_au' => $entree['repetee_au'] ?? null,
        ];
    }

    private function valider(Request $request, Evenement $evenement, array $etat): void
    {
        $request->validate([
            'modele' => ['required', 'in:'.implode(',', array_keys(ModelesRelance::tous()))],
            'sujet' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'qui' => ['required', 'in:'.implode(',', array_keys(ModelesRelance::QUI))],
            'inscription' => ['nullable', 'integer', 'required_if:qui,personne'],
            'course' => ['nullable', 'integer'],
            'quand' => ['required', 'in:maintenant,date,repetee'],
            'heure' => ['exclude_if:quand,maintenant', 'required', 'date_format:H:i'],
            'date_jour' => ['exclude_unless:quand,date', 'required', 'date'],
            'tous_les' => ['exclude_unless:quand,repetee', 'required', 'integer', 'min:1', 'max:60'],
            'repetee_du' => ['exclude_unless:quand,repetee', 'required', 'date'],
            'repetee_au' => ['exclude_unless:quand,repetee', 'required', 'date', 'after_or_equal:repetee_du'],
        ], [
            'sujet.required' => 'Écris l\'objet de l\'email.',
            'message.required' => 'Écris le message.',
            'inscription.required_if' => 'Cherche la personne par son nom, puis choisis-la dans la liste.',
            'heure.*' => 'Indique l\'heure d\'envoi, par exemple 10:00.',
            'date_jour.*' => 'Choisis le jour de l\'envoi.',
            'tous_les.*' => 'Indique tous les combien de jours (entre 1 et 60).',
            'repetee_du.*' => 'Choisis le premier jour.',
            'repetee_au.*' => 'Choisis le dernier jour (le même jour ou après le premier).',
        ]);

        if ($etat['qui'] === 'personne' && ! Inscription::where('id_evenement', $evenement->id_evenement)->whereIn('statut', RelancesCiblees::STATUTS)->whereKey($etat['inscription'])->exists()) {
            throw ValidationException::withMessages(['inscription' => 'Cherche la personne par son nom, puis choisis-la dans la liste.']);
        }

        if ($etat['quand'] === 'maintenant') {
            return;
        }

        // Un envoi programmé doit tomber dans le futur et avant l'événement.
        $programmation = $this->programmation($etat);
        $premier = match ($etat['quand']) {
            'date' => CarbonImmutable::parse($programmation['date_envoi']),
            'repetee' => CarbonImmutable::parse($etat['repetee_au'].' '.$etat['heure']),
        };

        if ($premier->lte(now())) {
            throw ValidationException::withMessages(['quand' => 'Ce moment est déjà passé : choisis une date à venir.']);
        }

        if ($premier->startOfDay()->gt($evenement->date_evenement)) {
            throw ValidationException::withMessages(['quand' => 'Ce moment tombe après l\'événement ('.$evenement->date_evenement->translatedFormat('j F Y').').']);
        }
    }

    private function programmation(array $etat): array
    {
        return [
            'quand' => $etat['quand'],
            'heure' => $etat['heure'].':00',
            'date_envoi' => $etat['quand'] === 'date' ? $etat['date_jour'].' '.$etat['heure'].':00' : null,
            'jours_avant' => null,
            'tous_les' => $etat['quand'] === 'repetee' ? (int) $etat['tous_les'] : null,
            'repetee_du' => $etat['quand'] === 'repetee' ? $etat['repetee_du'] : null,
            'repetee_au' => $etat['quand'] === 'repetee' ? $etat['repetee_au'] : null,
        ];
    }

    private function assistant(Evenement $evenement, array $etat, ?RelanceProgrammee $programmee = null, bool $apercu = false): View
    {
        $cible = RelancesCiblees::cible(ModelesRelance::cible($etat['qui'], $etat['course'], $etat['inscription']));
        $destinataires = $apercu ? $this->relances->destinataires($evenement, $cible) : null;
        $exemple = $destinataires?->first();

        return view('admin.relances.create', [
            'evenement' => $evenement,
            'programmee' => $programmee,
            'etat' => $etat,
            'modeles' => ModelesRelance::tous(),
            'courses' => $evenement->courses()->orderBy('heure_depart')->get(),
            'personne' => $etat['inscription'] ? Inscription::with('participant.user', 'tarif.course')->where('id_evenement', $evenement->id_evenement)->find($etat['inscription']) : null,
            'trouvees' => $etat['chercher'] !== '' ? $this->chercher($evenement, $etat['chercher']) : collect(),
            'apercu' => $apercu,
            'destinataires' => $destinataires,
            'recents' => $etat['quand'] === 'maintenant' ? $destinataires?->whereNotNull('relancee_le') : collect(),
            'exemple' => $exemple ? $this->relances->personnaliser($exemple, $etat['sujet'], $etat['message']) : null,
            'quandEnClair' => $apercu ? $this->quandEnClair($evenement, $etat) : null,
        ]);
    }

    private function chercher(Evenement $evenement, string $terme)
    {
        return Inscription::with('participant.user', 'tarif.course')
            ->where('id_evenement', $evenement->id_evenement)
            ->whereIn('statut', RelancesCiblees::STATUTS)
            ->whereHas('participant.user', fn ($u) => $u->whereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"])->orWhereRaw("concat(nom, ' ', prenom) like ?", ["%{$terme}%"]))
            ->limit(10)
            ->get();
    }

    private function quandEnClair(Evenement $evenement, array $etat): string
    {
        if ($etat['quand'] === 'maintenant') {
            return 'tout de suite';
        }

        $p = new RelanceProgrammee($this->programmation($etat));
        $p->setRelation('evenement', $evenement);
        $premier = $p->prochaine()?->translatedFormat('l j F Y \\à G\\hi');

        return match ($etat['quand']) {
            'repetee' => "le {$premier}, puis tous les {$p->tous_les} jour(s) jusqu'au ".$p->repetee_au->translatedFormat('j F Y'),
            default => lcfirst($p->enClair()),
        };
    }
}
