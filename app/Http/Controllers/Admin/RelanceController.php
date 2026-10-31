<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Relance;
use App\Services\Admin\RelancesCiblees;
use App\Support\EvenementAdmin;
use App\Support\ModelesRelance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Relances (niveau 2) : choisir un modèle, cibler un groupe ou une seule inscription, relire
 * l'aperçu (nombre de destinataires, exemple personnalisé, déjà relancés), puis envoyer.
 */
class RelanceController extends Controller
{
    public function __construct(private RelancesCiblees $relances) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.relances.index', [
            'evenement' => $evenement,
            'relances' => $evenement
                ? Relance::with('admin')->where('id_evenement', $evenement->id_evenement)->orderByDesc('created_at')->paginate(30)
                : null,
        ]);
    }

    /** Formulaire, prérempli par le modèle choisi (et l'inscription visée, depuis sa fiche). */
    public function create(Request $request): View|RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $cle = ModelesRelance::existe($request->query('modele')) ? $request->query('modele') : null;
        $inscription = $request->filled('inscription') ? Inscription::with('participant.user', 'tarif.course')->find($request->integer('inscription')) : null;

        // Depuis une fiche : le modèle suit le statut de l'inscription.
        $cle ??= $inscription?->statut === Inscription::DOSSIER_INCOMPLET ? 'documents' : ($inscription ? 'libre' : 'documents');
        $modele = ModelesRelance::tous()[$cle];

        $cible = RelancesCiblees::cible($inscription ? ['inscription' => $inscription->id_inscription] : $modele['cible']);

        return $this->formulaire($evenement, $cle, $modele['sujet'], $modele['message'], $cible);
    }

    public function apercu(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);
        $donnees = $this->valider($request);

        return $this->formulaire($evenement, $donnees['modele'], $donnees['sujet'], $donnees['message'], RelancesCiblees::cible($request->all()), apercu: true);
    }

    public function store(Request $request): RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);
        $donnees = $this->valider($request);
        $request->validate(['confirmation' => ['accepted']], ['confirmation.accepted' => 'Coche la case pour confirmer l\'envoi.']);

        $relance = $this->relances->envoyer($evenement, $donnees['modele'], $donnees['sujet'], $donnees['message'], RelancesCiblees::cible($request->all()), $request->boolean('inclure_recents'), $request->user());

        if ($relance->nb_destinataires === 0) {
            return redirect()->route('admin.relances.index')->with('erreur', 'Aucun email envoyé : personne ne correspond à la cible.');
        }

        return redirect()->route('admin.relances.index')->with('statut', "Relance « {$relance->sujet} » en cours d'envoi à {$relance->nb_destinataires} personne(s).");
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'modele' => ['required', 'in:'.implode(',', array_keys(ModelesRelance::tous()))],
            'sujet' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'inscription' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:'.implode(',', RelancesCiblees::STATUTS)],
            'course' => ['nullable', 'integer'],
        ], [
            'sujet.required' => 'Écris l\'objet de l\'email.',
            'message.required' => 'Écris le message.',
        ]);
    }

    private function formulaire($evenement, string $cle, string $sujet, string $message, array $cible, bool $apercu = false): View
    {
        $destinataires = $apercu ? $this->relances->destinataires($evenement, $cible) : null;
        $exemple = $destinataires?->first();

        return view('admin.relances.create', [
            'evenement' => $evenement,
            'modeles' => ModelesRelance::tous(),
            'cle' => $cle,
            'sujet' => $sujet,
            'message' => $message,
            'cible' => $cible,
            'inscription' => $cible['inscription'] ? Inscription::with('participant.user', 'tarif.course')->find($cible['inscription']) : null,
            'courses' => $evenement->courses()->orderBy('heure_depart')->get(),
            'apercu' => $apercu,
            'destinataires' => $destinataires,
            'recents' => $destinataires?->whereNotNull('relancee_le'),
            'exemple' => $exemple ? $this->relances->personnaliser($exemple, $sujet, $message) : null,
        ]);
    }
}
