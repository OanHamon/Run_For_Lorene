<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $primaryKey = 'id_paiement';

    public const EN_ATTENTE = 'en_attente';
    public const REUSSI = 'reussi';
    public const ECHOUE = 'echoue';

    protected $fillable = [
        'prestataire',
        'reference',
        'montant',
        'frais',
        'statut',
        'date_paiement',
        'id_commande',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'frais' => 'decimal:2',
            'date_paiement' => 'datetime',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class, 'id_commande', 'id_commande');
    }

    public function remboursements(): HasMany
    {
        return $this->hasMany(Remboursement::class, 'id_paiement', 'id_paiement');
    }

    /** Montant encore remboursable : encaissé moins déjà remboursé. */
    public function remboursable(): float
    {
        if ($this->statut !== self::REUSSI) {
            return 0.0;
        }

        return round((float) $this->montant - (float) $this->remboursements()->sum('montant'), 2);
    }
}
