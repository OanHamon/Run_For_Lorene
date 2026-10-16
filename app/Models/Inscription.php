<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Inscriptions qui engagent l'événement (payées ou en cours de paiement) : dès la première,
     * le paramétrage se verrouille (âges, date, montants des tarifs commencés, quota minimal).
     */
    public const ENGAGEES = [self::EN_ATTENTE_PAIEMENT, self::VALIDEE, self::DOSSIER_INCOMPLET];

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

    /** Options choisies (taille de t-shirt…), avec leur prix historisé. */
    public function declinaisons(): HasMany
    {
        return $this->hasMany(InscriptionDeclinaison::class, 'id_inscription', 'id_inscription');
    }

    /** Participants mineurs le jour de l'événement (moins de 18 ans à cette date). */
    public function scopeMineurs(Builder $query, Evenement $evenement): void
    {
        $query->whereHas('participant', fn ($q) => $q->whereDate('date_naissance', '>', $evenement->date_evenement->copy()->subYears(18)));
    }

    /** Même règle que documentsManquants() : décharge absente, ou questionnaire de santé absent pour un mineur. */
    public function scopeAvecDocumentsManquants(Builder $query, Evenement $evenement): void
    {
        $query->where(function ($q) use ($evenement) {
            self::questionsDechargeActives()
                ? $q->whereDoesntHave('documents.questionnaireSante', fn ($qs) => $qs->where('type_questionnaire', QuestionSante::DECHARGE))
                : $q->whereDoesntHave('documents.consentement', fn ($c) => $c->where('accepte', true)
                    ->whereHas('texteLegal', fn ($t) => $t->where('type_texte', 'decharge')));

            $q->orWhere(fn ($mineur) => $mineur->mineurs($evenement)
                    ->whereDoesntHave('documents.questionnaireSante', fn ($qs) => $qs->where('type_questionnaire', QuestionSante::SANTE)));
        });
    }

    /** Titulaire du compte qui suit l'inscription : le payeur, sinon le responsable ou le participant lui-même. */
    public function titulaire(): ?User
    {
        return collect([$this->commande?->user, $this->participant->responsable, $this->participant->user])
            ->first(fn (?User $user) => $user?->email !== null);
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    /** Règlement et RGPD acceptés : exigés dans le tunnel avant de payer. */
    public function consentementsAcceptes(): bool
    {
        $types = $this->typesConsentis();

        return collect(TexteLegal::AVANT_PAIEMENT)->every(fn ($type) => in_array($type, $types, true));
    }

    /** Tous les consentements obligatoires, décharge comprise (questionnaire et acceptation). */
    public function consentementsComplets(): bool
    {
        return $this->consentementsAcceptes() && ! $this->dechargeManquante();
    }

    /**
     * Décharge pas encore faite. Elle se fait par les affirmations à cocher du tunnel (le PDF
     * est facultatif) ; sans affirmations publiées, il faut accepter le PDF de la décharge.
     * Exigée dans le tunnel avant de payer ; sans elle, pas de départ.
     */
    public function dechargeManquante(): bool
    {
        if (self::questionsDechargeActives()) {
            return ! $this->aRempli(QuestionSante::DECHARGE);
        }

        return ! in_array('decharge', $this->typesConsentis(), true);
    }

    public static function questionsDechargeActives(): bool
    {
        return QuestionSante::where('type_questionnaire', QuestionSante::DECHARGE)->where('active', true)->exists();
    }

    /** @return list<string> libellés des documents à remplir pour pouvoir prendre le départ */
    public function documentsManquants(): array
    {
        return array_values(array_filter([
            $this->dechargeManquante() ? 'la décharge de responsabilité' : null,
            $this->questionnaireManquant() ? 'le questionnaire de santé' : null,
        ]));
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

    /** Le questionnaire de santé n'est demandé qu'aux participants mineurs le jour de l'événement. */
    public function santeRequise(): bool
    {
        return $this->participant->ageAu($this->evenement->date_evenement) < 18;
    }

    /** Questionnaire de santé demandé (participant mineur) et pas encore rempli. */
    public function questionnaireManquant(): bool
    {
        return $this->santeRequise() && ! $this->aRempli(QuestionSante::SANTE);
    }

    private function aRempli(string $type): bool
    {
        return $this->documents()
            ->whereHas('questionnaireSante', fn ($q) => $q->where('type_questionnaire', $type))
            ->exists();
    }
}
