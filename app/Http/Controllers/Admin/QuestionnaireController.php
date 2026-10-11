<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Evenement;
use App\Models\QuestionnaireSante;
use App\Models\QuestionSante;
use App\Services\Admin\Journal;
use App\Support\EvenementAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Questionnaires de santé : réponses déchiffrées à l'affichage, réservées au niveau 2 et plus
 * (spec : niveaux habilités). Un « oui » ne bloque pas l'inscription : l'admin le consulte,
 * contacte la famille si besoin et marque le questionnaire « vérifié ».
 */
class QuestionnaireController extends Controller
{
    public const VERIFIE = 'verifie';

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        $filtre = in_array($request->query('filtre'), ['a_verifier', 'oui', 'verifies'], true) ? $request->query('filtre') : 'a_verifier';

        $questionnaires = $evenement
            ? QuestionnaireSante::with('document.inscription.participant.user', 'document.inscription.tarif.course', 'document.validateur', 'reponses')
                ->where('type_questionnaire', QuestionSante::SANTE)
                ->whereHas('document.inscription', fn ($q) => $q->where('id_evenement', $evenement->id_evenement)->where('statut', '!=', 'annulee'))
                ->when($filtre === 'verifies', fn ($q) => $q->whereHas('document', fn ($d) => $d->where('statut', self::VERIFIE)))
                ->when($filtre !== 'verifies', fn ($q) => $q->whereHas('document', fn ($d) => $d->where('statut', '!=', self::VERIFIE)))
                ->orderByDesc('id_document')
                ->get()
                // Les réponses sont chiffrées : le compte des « oui » se fait après déchiffrement.
                ->each(fn (QuestionnaireSante $q) => $q->setAttribute('nb_oui', $q->reponses->where('reponse', 'oui')->count()))
                ->when($filtre === 'oui', fn ($c) => $c->filter(fn ($q) => $q->nb_oui > 0))
            : collect();

        return view('admin.questionnaires.index', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'questionnaires' => $questionnaires,
            'filtre' => $filtre,
        ]);
    }

    public function show(Document $document): View
    {
        $questionnaire = QuestionnaireSante::with('reponses.question', 'document.deposant', 'document.validateur', 'document.inscription.participant.user', 'document.inscription.evenement', 'document.inscription.tarif.course')
            ->where('type_questionnaire', QuestionSante::SANTE)
            ->findOrFail($document->id_document);

        $inscription = $questionnaire->document->inscription;

        return view('admin.questionnaires.show', [
            'questionnaire' => $questionnaire,
            'inscription' => $inscription,
            'titulaire' => $inscription->titulaire(),
            'mineur' => $inscription->santeRequise(),
            'reponses' => $questionnaire->reponses->sortBy(fn ($r) => [array_search($r->question->rubrique, array_keys(QuestionSante::RUBRIQUES)), $r->question->ordre])
                ->groupBy(fn ($r) => $r->question->rubrique),
        ]);
    }

    public function verifier(Document $document, Journal $journal): RedirectResponse
    {
        abort_unless($document->questionnaireSante?->type_questionnaire === QuestionSante::SANTE, 404);

        if ($document->statut !== self::VERIFIE) {
            $document->forceFill([
                'statut' => self::VERIFIE,
                'id_validateur' => auth()->id(),
                'date_validation' => now(),
            ])->save();

            $journal->noter('document.verification', $document->inscription, ['document' => $document->id_document]);
        }

        return back()->with('statut', 'Questionnaire marqué comme vérifié.');
    }
}
