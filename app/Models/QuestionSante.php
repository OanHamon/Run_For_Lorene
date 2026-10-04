<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionSante extends Model
{
    protected $table = 'question_santes';

    protected $primaryKey = 'id_question';

    public $timestamps = false;

    protected $fillable = [
        'libelle',
        'ordre',
        'version_questionnaire',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
