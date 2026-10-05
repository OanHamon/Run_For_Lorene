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

    /** Rubriques du questionnaire de santé, dans l'ordre du Google Form, avec leur titre. */
    public const RUBRIQUES = [
        'annee' => "Depuis l'année dernière",
        'semaines' => 'Depuis un certain temps (plus de 2 semaines)',
        'aujourdhui' => "Aujourd'hui",
        'parents' => 'Ta famille et ton suivi médical',
    ];

    /** Titre de la rubrique « parents » quand le coureur est mineur. */
    public const RUBRIQUE_PARENTS_MINEUR = 'Questions à faire remplir par tes parents';

    /** Découpage du questionnaire de santé en diapos : rubriques affichées sur chacune. */
    public const DIAPOS = [
        ['annee'],
        ['semaines', 'aujourdhui'],
        ['parents'],
    ];

    protected $fillable = [
        'type_questionnaire',
        'libelle',
        'rubrique',
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
