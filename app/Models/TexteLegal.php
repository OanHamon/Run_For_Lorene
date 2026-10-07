<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TexteLegal extends Model
{
    protected $table = 'texte_legals';

    protected $primaryKey = 'id_texte';

    public $timestamps = false;

    public const OBLIGATOIRES = ['reglement', 'decharge', 'rgpd'];

    /** Acceptés au volet « Conditions » du tunnel ; la décharge l'est à son propre volet. */
    public const AVANT_PAIEMENT = ['reglement', 'rgpd'];

    public const FACULTATIFS = ['droit_image'];

    protected $fillable = [
        'type_texte',
        'version',
        'date_publication',
        'fichier',
        'id_evenement',
    ];

    protected function casts(): array
    {
        return [
            'date_publication' => 'date',
        ];
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }
}
