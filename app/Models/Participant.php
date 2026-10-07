<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Participant extends Model
{
    protected $table = 'participants';

    protected $primaryKey = 'id_user';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'sexe',
        'date_naissance',
        'email_contact',
        'adresse',
        'code_postal',
        'ville',
        'pays',
        'id_responsable',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsable', 'id_user');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'id_participant', 'id_user');
    }

    /** Âge à une date donnée : pour les règles d'âge, c'est la date de l'événement (spec). */
    public function ageAu(Carbon $date): int
    {
        return (int) $this->date_naissance->diffInYears($date);
    }

    public function estGere(): bool
    {
        return $this->id_responsable !== null;
    }
}
