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

    public function inscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inscription::class, 'id_tarif', 'id_tarif');
    }

    /**
     * Tarif verrouillé : déjà choisi par une inscription engagée. Son montant, son début et sa
     * tranche d'âge ne bougent plus ; on peut seulement le terminer plus tôt (pas avant
     * aujourd'hui) ou le prolonger, puis créer un nouveau tarif.
     */
    public function estVerrouille(): bool
    {
        return $this->exists && $this->inscriptions()->whereIn('statut', Inscription::ENGAGEES)->exists();
    }
}
