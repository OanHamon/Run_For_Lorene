<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarif extends Model
{
    protected $table = 'tarifs';

    protected $primaryKey = 'id_tarif';

    public $timestamps = false;

    protected $fillable = [
        'libelle',
        'montant',
        'valide_du',
        'valide_au',
        'age_min',
        'age_max',
        'id_course',
    ];

    protected function casts(): array
    {
        return [
            'valide_du' => 'date',
            'valide_au' => 'date',
            'montant' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'id_course', 'id_course');
    }
}
