<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remboursement extends Model
{
    protected $table = 'remboursements';

    protected $primaryKey = 'id_remboursement';

    public $timestamps = false;

    protected $fillable = [
        'reference',
        'id_admin',
        'montant',
        'motif',
        'date_remboursement',
        'id_paiement',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_remboursement' => 'datetime',
        ];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'id_paiement', 'id_paiement');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }
}
