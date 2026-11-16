<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CandidatureBenevole;
use App\Models\CandidatureStatut;
use App\Models\Evenement;
use App\Notifications\CandidatureStatutModifie;
use App\Services\Admin\Journal;
use App\Services\Benevoles\FichiersCandidature;
use App\Support\Benevolat;
use App\Support\Csv;
use App\Support\EvenementAdmin;
use App\Support\Telephone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Candidatures bénévoles (brief complémentaire) : liste et fiche dès le niveau 1, sans les
 * données sensibles (urgence, responsable légal, allergies, photo, autorisation) ; celles-ci,
 * les changements de statut et les exports au niveau 2. Ouverture des candidatures au niveau 3.
 */
class BenevoleController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        $compteurs = $evenement
            ? CandidatureBenevole::where('id_evenement', $evenement->id_evenement)
                ->selectRaw('statut, count(*) as nombre')->groupBy('statut')->pluck('nombre', 'statut')
            : collect();

        return view('admin.benevoles.index', [
            'evenement' => $evenement,
            'candidatures' => $evenement ? $this->requete($request, $evenement)->paginate(50)->withQueryString() : null,
            'compteurs' => $compteurs,
            'ouvert' => Benevolat::ouvert(),
        ]);
    }

    public function show(CandidatureBenevole $candidature): View
    {
        return view('admin.benevoles.show', [
            'c' => $candidature->load('user', 'evenement', 'course', 'texteDroitImage', 'historique.admin'),
        ]);
    }

    /** Nouveau statut (et mission affectée), avec email au bénévole si demandé ; tout est historisé. */
    public function statut(Request $request, CandidatureBenevole $candidature): RedirectResponse
    {
        abort_unless($candidature->envoyee(), 404);

        $donnees = $request->validateWithBag('statut', [
            'statut' => ['required', Rule::in(CandidatureBenevole::STATUTS_ADMIN)],
            'mission_affectee' => ['nullable', Rule::in(array_keys(Benevolat::MISSIONS))],
            'message' => ['nullable', 'string', 'max:3000'],
            'notifier' => ['boolean'],
        ], [], ['mission_affectee' => 'mission']);

        $avant = ['statut' => $candidature->statut, 'mission' => $candidature->mission_affectee];
        $notifier = $request->boolean('notifier');
        $message = trim((string) ($donnees['message'] ?? '')) ?: null;

        if ($avant['statut'] === $donnees['statut'] && $avant['mission'] === ($donnees['mission_affectee'] ?? null) && ! $message) {
            return back()->with('statut', 'Rien n\'a changé.');
        }

        DB::transaction(function () use ($candidature, $donnees, $notifier, $message) {
            $candidature->forceFill([
                'statut' => $donnees['statut'],
                'mission_affectee' => $donnees['mission_affectee'] ?? null,
            ])->save();

            CandidatureStatut::create([
                'id_candidature' => $candidature->id_candidature,
                'statut' => $donnees['statut'],
                'id_admin' => auth()->id(),
                'message' => $message,
                'notifie' => $notifier,
            ]);
        });

        if ($notifier) {
            $candidature->user->notify(new CandidatureStatutModifie($candidature, $message));
        }

        $this->journal->noter('benevole.statut', $candidature, [
            'avant' => $avant['statut'],
            'apres' => $donnees['statut'],
            'mission' => $donnees['mission_affectee'] ?? null,
            'email' => $notifier,
        ]);

        return redirect()->route('admin.benevoles.show', $candidature)
            ->with('statut', 'Candidature : '.$candidature->libelleStatut().'.'.($notifier ? ' Un email part au bénévole.' : ''));
    }

    public function fichier(CandidatureBenevole $candidature, string $fichier, FichiersCandidature $fichiers): Response
    {
        $this->journal->noter('benevole.fichier', $candidature, ['fichier' => $fichier]);

        return $fichiers->reponse($candidature, $fichier);
    }

    /** Ouvre ou ferme les candidatures (bénévoles attendus en février). */
    public function ouverture(Request $request): RedirectResponse
    {
        $ouvert = $request->boolean('ouvert');
        Benevolat::ouvrir($ouvert);
        $this->journal->noter('benevole.ouverture', null, ['ouvert' => $ouvert]);

        return back()->with('statut', $ouvert ? 'Les candidatures bénévoles sont ouvertes.' : 'Les candidatures bénévoles sont fermées.');
    }

    /** Liste complète pour l'organisation (données sensibles comprises : chaque export est tracé). */
    public function export(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $this->journal->noter('export.benevoles', $evenement, array_filter($request->only(['q', 'statut', 'mission', 'mineur'])));

        $entetes = [
            'N°', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Âge le jour J', 'Statut', 'Mission affectée',
            'Missions préférées', 'Missions impossibles', 'Communication', 'Expérience', 'Compétences', 'Commentaire',
            'Jour J', ...array_map(fn ($p) => $p['libelle'], array_values(Benevolat::PERIODES)),
            'Véhicule', 'Transport du matériel', 'Précisions transport', 'Binôme', 'Court aussi', 'Tee-shirt', 'Repas',
            'Allergies', 'Soirée', 'Droit à l\'image', 'Photo', 'Autorisation parentale',
            'Responsable légal', 'Tél. responsable', 'Contact d\'urgence', 'Tél. urgence', 'Lien', 'Envoyée le',
        ];

        $lignes = function () use ($request, $evenement) {
            foreach ($this->requete($request, $evenement)->lazy() as $c) {
                $d = $c->disponibilites ?? [];
                $ouiNon = fn (?bool $v) => $v === null ? '' : ($v ? 'oui' : 'non');

                yield [
                    $c->id_candidature, $c->user->nom, $c->user->prenom, $c->user->email, Telephone::lisible($c->telephone),
                    Benevolat::AGES[$c->age] ?? '', $c->libelleStatut(), Benevolat::mission($c->mission_affectee),
                    collect($c->missions_preferees)->map(fn ($m, $i) => ($i + 1).'. '.Benevolat::mission($m))->join(' | '),
                    collect($c->missions_impossibles)->map(fn ($m) => Benevolat::mission($m))->join(' | '),
                    collect($c->competences)->map(fn ($m) => Benevolat::COMPETENCES[$m] ?? $m)->join(', '),
                    $c->experience, $c->competences_detail, $c->commentaire,
                    ($d['toute_journee'] ?? false) ? 'Toute la journée' : collect($d['tranches'] ?? [])->map(fn ($t) => Benevolat::TRANCHES[$t] ?? $t)->join(', '),
                    ...array_map(function ($cle) use ($d) {
                        $p = $d['periodes'][$cle] ?? [];

                        return trim((Benevolat::ETATS_PERIODE[$p['etat'] ?? ''] ?? '').(($p['debut'] ?? null) ? ' '.$p['debut'] : '').(($p['fin'] ?? null) ? '-'.$p['fin'] : ''));
                    }, array_keys(Benevolat::PERIODES)),
                    $ouiNon($c->vehicule), $ouiNon($c->transport), $c->transport_detail, $c->binome,
                    $c->court ? 'oui'.($c->course ? ' ('.$c->course->nom.')' : '') : 'non',
                    $c->taille_tshirt, $ouiNon($c->repas), $c->allergies, Benevolat::SOIREE[$c->soiree] ?? '',
                    $c->droit_image === null ? '' : ($c->droit_image ? 'accepté' : 'refusé'),
                    $c->photo ? 'oui' : 'non',
                    $c->autorisationRequise() ? ($c->autorisation_parentale ? 'déposée' : 'manquante') : '',
                    $c->responsable_nom, Telephone::lisible($c->responsable_tel),
                    $c->urgence_nom, Telephone::lisible($c->urgence_tel), $c->urgence_lien,
                    $c->envoyee_le?->format('d/m/Y H:i'),
                ];
            }
        };

        return Csv::telecharger('benevoles-'.Str::slug($evenement->nom), $entetes, $lignes());
    }

    /** Photos des badges en ZIP, nommées « Nom Prénom (n°) », des candidatures filtrées. */
    public function photos(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $candidatures = $this->requete($request, $evenement)->whereNotNull('photo')->get()
            ->filter(fn ($c) => Storage::disk('local')->exists($c->photo));

        if ($candidatures->isEmpty()) {
            return back()->with('statut', 'Aucune photo à télécharger pour ces filtres.');
        }

        $this->journal->noter('export.photos-benevoles', $evenement, ['nombre' => $candidatures->count()]);

        $zip = new \ZipArchive;
        $chemin = tempnam(sys_get_temp_dir(), 'rfl-photos');
        $zip->open($chemin, \ZipArchive::OVERWRITE);

        foreach ($candidatures as $c) {
            $nom = Str::ascii("{$c->user->nom} {$c->user->prenom}")." ({$c->id_candidature}).".pathinfo($c->photo, PATHINFO_EXTENSION);
            $zip->addFile(Storage::disk('local')->path($c->photo), $nom);
        }

        $zip->close();

        return response()->download($chemin, 'photos-badges-'.Str::slug($evenement->nom).'.zip')->deleteFileAfterSend();
    }

    /** Candidatures envoyées de l'événement, filtrées ; « brouillons » seulement si demandé. */
    private function requete(Request $request, Evenement $evenement): Builder
    {
        $statut = (string) $request->query('statut');

        return CandidatureBenevole::with('user', 'course')
            ->where('id_evenement', $evenement->id_evenement)
            ->when(
                isset(CandidatureBenevole::LIBELLES[$statut]),
                fn ($q) => $q->where('statut', $statut),
                fn ($q) => $q->where('statut', '!=', CandidatureBenevole::BROUILLON),
            )
            ->when($request->filled('mission'), fn ($q) => $q->where(fn ($q) => $q
                ->whereJsonContains('missions_preferees', (string) $request->query('mission'))
                ->orWhere('mission_affectee', (string) $request->query('mission'))))
            ->when($request->boolean('mineur'), fn ($q) => $q->whereIn('age', ['moins_16', '16_17']))
            ->when($request->boolean('sans_photo'), fn ($q) => $q->whereNull('photo'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->query('q'))).'%';
                $q->whereHas('user', fn ($u) => $u->where('nom', 'like', $terme)->orWhere('prenom', 'like', $terme)->orWhere('email', 'like', $terme));
            })
            ->orderByRaw("FIELD(statut, 'recue', 'infos_demandees', 'en_etude', 'confirmee', 'brouillon', 'non_retenue', 'desistement')")
            ->orderBy('envoyee_le');
    }
}
