<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evenement extends Model
{
    protected $table = 'evenements';

    protected $primaryKey = 'id_evenement';

    protected $fillable = [
        'nom',
        'date_evenement',
        'ouverture',
        'fermeture',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_evenement' => 'date',
            'ouverture' => 'datetime',
            'fermeture' => 'datetime',
        ];
    }

    /**
     * L'événement sur lequel porte le parcours d'inscription : le prochain événement publié
     * (à défaut le dernier publié). Une seule édition est ouverte à la fois en V1.
     */
    public static function courant(): ?self
    {
        return static::where('statut', 'publie')
            ->whereDate('date_evenement', '>=', today())
            ->orderBy('date_evenement')
            ->first()
            ?? static::where('statut', 'publie')->orderByDesc('date_evenement')->first();
    }

    public function inscriptionsOuvertes(): bool
    {
        return $this->statut === 'publie' && now()->between($this->ouverture, $this->fermeture);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'id_evenement', 'id_evenement');
    }

    public function optionEvenements(): HasMany
    {
        return $this->hasMany(OptionEvenement::class, 'id_evenement', 'id_evenement');
    }

    public function texteLegals(): HasMany
    {
        return $this->hasMany(TexteLegal::class, 'id_evenement', 'id_evenement');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class, 'id_evenement', 'id_evenement');
    }
}
