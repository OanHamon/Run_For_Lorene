<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne du journal des actions admin (traçabilité, spec). Jamais modifiée ni supprimée. */
class JournalAdmin extends Model
{
    protected $table = 'journal_admins';

    protected $primaryKey = 'id_journal';

    public const UPDATED_AT = null;

    /** Libellés affichés dans le journal, par action. */
    public const LIBELLES = [
        'evenement.creation' => 'Événement créé',
        'evenement.modification' => 'Événement modifié',
        'evenement.duplication' => 'Événement dupliqué',
        'evenement.suppression' => 'Événement supprimé',
        'course.creation' => 'Course créée',
        'course.modification' => 'Course modifiée',
        'course.suppression' => 'Course supprimée',
        'tarif.creation' => 'Tarif créé',
        'tarif.modification' => 'Tarif modifié',
        'tarif.suppression' => 'Tarif supprimé',
        'option.creation' => 'Option créée',
        'option.modification' => 'Option modifiée',
        'option.suppression' => 'Option supprimée',
        'texte.publication' => 'Texte légal publié',
        'inscription.dossard' => 'Dossard modifié',
        'inscription.annulation' => 'Inscription annulée',
        'inscription.relance' => 'Relance envoyée',
        'inscription.participant' => 'Informations du participant corrigées',
        'inscription.gratuite' => 'Inscription gratuite créée',
        'document.consultation' => 'Questionnaire de santé consulté',
        'document.verification' => 'Questionnaire vérifié',
        'paiement.remboursement' => 'Remboursement',
        'admin.niveau' => 'Niveau d\'accès modifié',
        'admin.retrait' => 'Accès admin retiré',
        'user.reinitialisation' => 'Accès réinitialisé',
    ];

    protected $fillable = [
        'id_admin',
        'action',
        'cible_type',
        'cible_id',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }

    public function libelle(): string
    {
        return self::LIBELLES[$this->action] ?? $this->action;
    }
}
