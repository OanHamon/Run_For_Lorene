<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireSante extends Model
{
    protected $table = 'questionnaire_santes';

    protected $primaryKey = 'id_document';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_document',
        'attestation',
    ];

    protected function casts(): array
    {
        return [
            'attestation' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id_document', 'id_document');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseSante::class, 'id_document', 'id_document');
    }
}
