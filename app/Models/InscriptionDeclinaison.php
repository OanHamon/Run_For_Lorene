<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscriptionDeclinaison extends Model
{
    protected $table = 'inscription_declinaisons';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_inscription',
        'id_declinaison',
        'quantite',
        'prix_unitaire',
    ];

    protected function casts(): array
    {
        return [
            'prix_unitaire' => 'decimal:2',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'id_inscription', 'id_inscription');
    }

    public function declinaison(): BelongsTo
    {
        return $this->belongsTo(Declinaison::class, 'id_declinaison', 'id_declinaison');
    }
}
