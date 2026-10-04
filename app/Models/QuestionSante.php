<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionSante extends Model
{
    protected $table = 'question_santes';

    protected $primaryKey = 'id_question';

    public $timestamps = false;

    public const SANTE = 'sante';

    public const DECHARGE = 'decharge';

    protected $fillable = [
        'type_questionnaire',
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
