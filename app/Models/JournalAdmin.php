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
        'relance.envoi' => 'Relance ciblée envoyée',
        'relance.programmee' => 'Relance programmée',
        'relance.programmee_modifiee' => 'Relance programmée modifiée',
        'relance.programmee_pause' => 'Relance programmée mise en pause',
        'relance.programmee_reprise' => 'Relance programmée reprise',
        'relance.programmee_supprimee' => 'Relance programmée supprimée',
        'relance.reglages' => 'Relances automatiques réglées',
        'inscription.note' => 'Note interne ajoutée',
        'export.emargement' => 'Liste d\'émargement imprimée',
        'export.retraits' => 'Export des retraits',
        'inscription.retrait_annule' => 'Remise du dossard annulée',
        'retrait.poste' => 'Poste de retrait ouvert',
        'retrait.revocation' => 'Poste de retrait fermé',
        'inscription.participant' => 'Informations du participant corrigées',
        'inscription.gratuite' => 'Inscription gratuite créée',
        'inscription.reprise' => 'Inscription annulée reprise (ancienne commande notée)',
        'document.consultation' => 'Questionnaire de santé consulté',
        'document.verification' => 'Questionnaire vérifié',
        'paiement.remboursement' => 'Remboursement',
        'commande.confirmation' => 'Commande confirmée à la main',
        'admin.niveau' => 'Niveau d\'accès modifié',
        'admin.retrait' => 'Accès admin retiré',
        'user.reinitialisation' => 'Accès réinitialisé',
        'user.email' => 'Email du compte corrigé',
        'user.creation' => 'Compte créé',
        'user.suppression' => 'Compte supprimé',
        'user.activation' => 'Lien d\'activation renvoyé',
        'export.inscrits' => 'Export des inscrits',
        'export.dossards' => 'Export des dossards',
        'export.dons' => 'Export des dons',
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
