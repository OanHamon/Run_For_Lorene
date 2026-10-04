<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Don extends Model
{
    protected $table = 'dons';

    protected $primaryKey = 'id_don';

    protected $fillable = [
        'montant',
        'recu_fiscal_demande',
        'anonyme',
        'id_commande',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'recu_fiscal_demande' => 'boolean',
            'anonyme' => 'boolean',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'id_commande', 'id_commande');
    }
}
