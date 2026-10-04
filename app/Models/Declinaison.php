<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Declinaison extends Model
{
    protected $table = 'declinaisons';

    protected $primaryKey = 'id_declinaison';

    public $timestamps = false;

    protected $fillable = [
        'libelle',
        'stock',
        'id_option',
    ];

    public function optionEvenement(): BelongsTo
    {
        return $this->belongsTo(OptionEvenement::class, 'id_option', 'id_option');
    }
}
