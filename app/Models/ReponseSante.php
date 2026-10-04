<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReponseSante extends Model
{
    protected $table = 'reponse_santes';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_document',
        'id_question',
        'reponse',
    ];

    protected function casts(): array
    {
        return [
            'reponse' => 'encrypted',
        ];
    }

    public function questionnaireSante(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSante::class, 'id_document', 'id_document');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionSante::class, 'id_question', 'id_question');
    }
}
