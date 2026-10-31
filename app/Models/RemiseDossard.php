<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Remise d'un dossard (ou son annulation), avec la personne qui l'a faite. */
class RemiseDossard extends Model
{
    protected $table = 'remises_dossard';

    protected $primaryKey = 'id_remise';

    public const UPDATED_AT = null;

    public const REMISE = 'remise';

    public const ANNULATION = 'annulation';

    protected $fillable = ['action', 'par', 'id_inscription', 'id_poste', 'id_admin'];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'id_inscription', 'id_inscription');
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(PosteRetrait::class, 'id_poste', 'id_poste');
    }
}
