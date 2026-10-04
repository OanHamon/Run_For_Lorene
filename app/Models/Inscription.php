<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscription extends Model
{
    protected $table = 'inscriptions';

    protected $primaryKey = 'id_inscription';

    public const BROUILLON = 'brouillon';

    public const EN_ATTENTE_PAIEMENT = 'en_attente_paiement';

    public const VALIDEE = 'validee';

    public const DOSSIER_INCOMPLET = 'dossier_incomplet';

    public const ANNULEE = 'annulee';

    public const LIBELLES = [
        self::BROUILLON => 'Brouillon',
        self::EN_ATTENTE_PAIEMENT => 'En attente de paiement',
        self::VALIDEE => 'Validée',
        self::DOSSIER_INCOMPLET => 'Dossier incomplet',
        self::ANNULEE => 'Annulée',
    ];

    protected $fillable = [
        'prix_paye',
        'statut',
        'dossard',
        'urgence_nom',
        'urgence_tel',
        'date_retrait',
        'id_participant',
        'id_tarif',
        'id_commande',
        'id_evenement',
    ];

    protected function casts(): array
    {
        return [
            'prix_paye' => 'decimal:2',
            'date_retrait' => 'date',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'id_participant', 'id_user');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class, 'id_tarif', 'id_tarif');
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'id_commande', 'id_commande');
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'id_inscription', 'id_inscription');
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    /**
     * Consentements obligatoires (règlement, décharge, RGPD) tous donnés, et questionnaire de
     * décharge rempli dès qu'il comporte des questions.
     */
    public function consentementsComplets(): bool
    {
        $types = $this->typesConsentis();

        return collect(TexteLegal::OBLIGATOIRES)->every(fn ($type) => in_array($type, $types, true))
            && ! $this->dechargeManquante();
    }

    /** Questionnaire de décharge publié mais pas encore rempli pour cette inscription. */
    public function dechargeManquante(): bool
    {
        $questionsActives = QuestionSante::where('type_questionnaire', QuestionSante::DECHARGE)->where('active', true)->exists();

        return $questionsActives && ! $this->aRempli(QuestionSante::DECHARGE);
    }

    /** @return list<string> types de textes acceptés pour cette inscription */
    public function typesConsentis(): array
    {
        return Consentement::query()
            ->join('documents', 'documents.id_document', '=', 'consentements.id_document')
            ->join('texte_legals', 'texte_legals.id_texte', '=', 'consentements.id_texte')
            ->where('documents.id_inscription', $this->id_inscription)
            ->whereNull('documents.deleted_at')
            ->where('consentements.accepte', true)
            ->pluck('texte_legals.type_texte')
            ->unique()
            ->values()
            ->all();
    }

    /** Questionnaire de santé exigé par la course et pas encore rempli. */
    public function questionnaireManquant(): bool
    {
        if (! $this->tarif->course->questionnaire_requis) {
            return false;
        }

        return ! $this->aRempli(QuestionSante::SANTE);
    }

    private function aRempli(string $type): bool
    {
        return $this->documents()
            ->whereHas('questionnaireSante', fn ($q) => $q->where('type_questionnaire', $type))
            ->exists();
    }
}
