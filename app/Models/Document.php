<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $primaryKey = 'id_document';

    protected $fillable = [
        'date_depot',
        'statut',
        'motif_refus',
        'date_validation',
        'id_deposant',
        'id_validateur',
        'id_inscription',
    ];

    protected function casts(): array
    {
        return [
            'date_depot' => 'datetime',
            'date_validation' => 'datetime',
        ];
    }

    public function deposant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_deposant', 'id_user');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_validateur', 'id_user');
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'id_inscription', 'id_inscription');
    }

    public function questionnaireSante(): HasOne
    {
        return $this->hasOne(QuestionnaireSante::class, 'id_document', 'id_document');
    }

    public function consentement(): HasOne
    {
        return $this->hasOne(Consentement::class, 'id_document', 'id_document');
    }
}
