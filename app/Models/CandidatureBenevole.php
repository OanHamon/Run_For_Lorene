<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Candidature bénévole pour un événement (brief complémentaire). Brouillon tant qu'elle n'est
 * pas envoyée ; une fois envoyée, elle n'est plus modifiable par le bénévole (seuls la photo
 * du badge et l'autorisation parentale peuvent être ajoutées ou remplacées). Seule
 * l'organisation fait évoluer le statut et affecte une mission.
 */
class CandidatureBenevole extends Model
{
    protected $table = 'candidature_benevoles';

    protected $primaryKey = 'id_candidature';

    public const BROUILLON = 'brouillon';

    public const RECUE = 'recue';

    public const EN_ETUDE = 'en_etude';

    public const INFOS_DEMANDEES = 'infos_demandees';

    public const CONFIRMEE = 'confirmee';

    public const NON_RETENUE = 'non_retenue';

    public const DESISTEMENT = 'desistement';

    public const LIBELLES = [
        self::BROUILLON => 'Pas encore envoyée',
        self::RECUE => 'Candidature reçue',
        self::EN_ETUDE => 'En cours d\'étude',
        self::INFOS_DEMANDEES => 'Informations demandées',
        self::CONFIRMEE => 'Participation confirmée',
        self::NON_RETENUE => 'Non retenue',
        self::DESISTEMENT => 'Désistement',
    ];

    /** Statuts que l'organisation peut donner à une candidature envoyée. */
    public const STATUTS_ADMIN = [self::RECUE, self::EN_ETUDE, self::INFOS_DEMANDEES, self::CONFIRMEE, self::NON_RETENUE, self::DESISTEMENT];

    /** Candidature close : plus de photo ni d'autorisation à déposer. */
    public const CLOS = [self::NON_RETENUE, self::DESISTEMENT];

    protected $fillable = [
        'id_user', 'id_evenement', 'statut', 'etape',
        'telephone', 'age', 'responsable_nom', 'responsable_tel', 'urgence_nom', 'urgence_tel', 'urgence_lien',
        'missions_preferees', 'missions_impossibles', 'competences', 'experience', 'competences_detail', 'commentaire',
        'disponibilites', 'vehicule', 'transport', 'transport_detail', 'binome', 'court', 'id_course',
        'taille_tshirt', 'repas', 'allergies', 'soiree',
        'droit_image', 'droit_image_le', 'id_texte_droit_image',
        'photo', 'photo_le', 'autorisation_parentale', 'autorisation_le',
        'mission_affectee', 'envoyee_le',
    ];

    protected function casts(): array
    {
        return [
            // Données sensibles : chiffrées en base, visibles des seuls admins habilités.
            'responsable_nom' => 'encrypted',
            'responsable_tel' => 'encrypted',
            'urgence_nom' => 'encrypted',
            'urgence_tel' => 'encrypted',
            'urgence_lien' => 'encrypted',
            'allergies' => 'encrypted',
            'missions_preferees' => 'array',
            'missions_impossibles' => 'array',
            'competences' => 'array',
            'disponibilites' => 'array',
            'vehicule' => 'boolean',
            'transport' => 'boolean',
            'court' => 'boolean',
            'repas' => 'boolean',
            'droit_image' => 'boolean',
            'droit_image_le' => 'datetime',
            'photo_le' => 'datetime',
            'autorisation_le' => 'datetime',
            'envoyee_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Les fichiers privés partent avec la candidature. Une suppression en cascade par la base
        // ne passe pas ici : la suppression de compte et la purge RGPD les effacent elles-mêmes.
        static::deleted(fn (self $c) => $c->supprimerFichiers());
    }

    /** Candidature du compte pour l'événement en cours (celui de la page bénévoles). */
    public static function duCompte(User $user): ?self
    {
        $evenement = Evenement::courant();

        return $evenement
            ? static::where('id_user', $user->id_user)->where('id_evenement', $evenement->id_evenement)->first()
            : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'id_course', 'id_course');
    }

    public function texteDroitImage(): BelongsTo
    {
        return $this->belongsTo(TexteLegal::class, 'id_texte_droit_image', 'id_texte');
    }

    public function historique(): HasMany
    {
        return $this->hasMany(CandidatureStatut::class, 'id_candidature', 'id_candidature')->orderByDesc('id_statut');
    }

    public function envoyee(): bool
    {
        return $this->statut !== self::BROUILLON;
    }

    public function close(): bool
    {
        return in_array($this->statut, self::CLOS, true);
    }

    public function mineur(): bool
    {
        return in_array($this->age, ['moins_16', '16_17'], true);
    }

    public function autorisationRequise(): bool
    {
        return $this->age === 'moins_16';
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    /** Emplacements des fichiers sur le disque privé (jamais servis directement). */
    public function dossier(): string
    {
        return "benevoles/evenement-{$this->id_evenement}/candidature-{$this->id_candidature}";
    }

    public function supprimerFichiers(): void
    {
        Storage::disk('local')->deleteDirectory($this->dossier());
    }
}
