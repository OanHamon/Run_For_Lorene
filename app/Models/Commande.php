<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    protected $table = 'commandes';

    protected $primaryKey = 'id_commande';

    public const EN_COURS = 'en_cours';
    public const PAYEE = 'payee';
    public const ABANDONNEE = 'abandonnee';
    public const GRATUITE = 'gratuite';

    protected $fillable = [
        'date_commande',
        'montant_total',
        'statut',
        'id_user',
    ];

    protected function casts(): array
    {
        return [
            'date_commande' => 'datetime',
            'montant_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'id_commande', 'id_commande');
    }

    public function don(): HasOne
    {
        return $this->hasOne(Don::class, 'id_commande', 'id_commande');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'id_commande', 'id_commande');
    }
}
