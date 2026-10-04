<?php

namespace App\Services\Inscriptions;

use App\Models\Document;
use App\Models\Inscription;
use App\Models\QuestionnaireSante;
use App\Models\QuestionSante;
use App\Models\ReponseSante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Questionnaires oui/non d'une inscription : santé (avec attestation finale) et décharge.
 * Chaque réponse est chiffrée et liée à la question posée, donc à sa version. Remplir à
 * nouveau archive l'ancien questionnaire (suppression logique) au lieu de le modifier.
 */
class Questionnaires
{
    public const REPONSES = ['oui', 'non'];

    /** @return Collection<int, QuestionSante> questions actives de la dernière version, dans l'ordre */
    public function questions(string $type): Collection
    {
        $actives = QuestionSante::where('type_questionnaire', $type)->where('active', true);
        $version = (clone $actives)->max('version_questionnaire');

        if ($version === null) {
            return collect();
        }

        return $actives->where('version_questionnaire', $version)
            ->orderBy('ordre')
            ->orderBy('id_question')
            ->get();
    }

    /** Dernier questionnaire de ce type rempli pour l'inscription (documents archivés exclus). */
    public function dernier(Inscription $inscription, string $type): ?QuestionnaireSante
    {
        return QuestionnaireSante::where('type_questionnaire', $type)
            ->whereHas('document', fn ($q) => $q->where('id_inscription', $inscription->id_inscription))
            ->with('document')
            ->orderByDesc('id_document')
            ->first();
    }

    /** @return array<int, string> réponses du dernier questionnaire, par id_question */
    public function reponses(Inscription $inscription, string $type): array
    {
        $questionnaire = $this->dernier($inscription, $type);

        return $questionnaire
            ? $questionnaire->reponses->mapWithKeys(fn (ReponseSante $r) => [$r->id_question => $r->reponse])->all()
            : [];
    }

    /**
     * Enregistre un questionnaire complet. Toutes les questions de la version en cours
     * attendent oui ou non ; la santé exige en plus l'attestation finale.
     *
     * @param  array<int|string, mixed>  $reponses  par id_question
     *
     * @throws ValidationException clés « reponses.{id} » et « attestation »
     */
    public function enregistrer(Inscription $inscription, string $type, array $reponses, bool $attestation, int $deposant): QuestionnaireSante
    {
        $questions = $this->questions($type);
        $propres = [];
        $erreurs = [];

        foreach ($questions as $question) {
            $reponse = $reponses[$question->id_question] ?? null;

            if (! in_array($reponse, self::REPONSES, true)) {
                $erreurs["reponses.{$question->id_question}"] = 'Réponds par oui ou par non.';
            } else {
                $propres[$question->id_question] = $reponse;
            }
        }

        if ($type === QuestionSante::SANTE && ! $attestation) {
            $erreurs['attestation'] = "Coche l'attestation pour valider le questionnaire.";
        }

        if ($questions->isEmpty()) {
            $erreurs['reponses'] = "Le questionnaire n'est pas encore disponible. Réessaie plus tard.";
        }

        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        return DB::transaction(function () use ($inscription, $type, $propres, $attestation, $deposant) {
            $existant = $this->dernier($inscription, $type);

            // Mêmes réponses qu'avant (retour en arrière dans le tunnel) : rien à archiver.
            if ($existant && $this->reponses($inscription, $type) == $propres && $existant->attestation === $attestation) {
                return $existant;
            }

            $existant?->document->delete();

            $document = Document::create([
                'date_depot' => now(),
                // La santé peut être marquée « vérifiée » par un admin ; la décharge vaut acceptation.
                'statut' => $type === QuestionSante::SANTE ? 'en_attente' : 'valide',
                'id_deposant' => $deposant,
                'id_inscription' => $inscription->id_inscription,
            ]);

            $questionnaire = QuestionnaireSante::create([
                'id_document' => $document->id_document,
                'type_questionnaire' => $type,
                'attestation' => $attestation,
            ]);

            foreach ($propres as $idQuestion => $reponse) {
                ReponseSante::create([
                    'id_document' => $document->id_document,
                    'id_question' => $idQuestion,
                    'reponse' => $reponse,
                ]);
            }

            return $questionnaire;
        });
    }
}
