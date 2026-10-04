<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionEvenement extends Model
{
    protected $table = 'option_evenements';

    protected $primaryKey = 'id_option';

    public $timestamps = false;

    protected $fillable = [
        'libelle',
        'prix',
        'id_evenement',
    ];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function declinaisons(): HasMany
    {
        return $this->hasMany(Declinaison::class, 'id_option', 'id_option');
    }
}
