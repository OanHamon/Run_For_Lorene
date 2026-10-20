<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evenement extends Model
{
    protected $table = 'evenements';

    protected $primaryKey = 'id_evenement';

    /** Brouillon : en préparation, invisible. Publié : sur le site. Clôturé : édition terminée, retirée du site. */
    public const BROUILLON = 'brouillon';

    public const PUBLIE = 'publie';

    public const CLOTURE = 'cloture';

    public const LIBELLES = [
        self::BROUILLON => 'Brouillon',
        self::PUBLIE => 'Publié',
        self::CLOTURE => 'Clôturé',
    ];

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
            'documents_purges_le' => 'datetime',
        ];
    }

    /**
     * L'événement sur lequel porte le parcours d'inscription : le prochain événement publié
     * (à défaut le dernier publié). Une seule édition est ouverte à la fois en V1.
     */
    public static function courant(): ?self
    {
        return static::where('statut', self::PUBLIE)
            ->whereDate('date_evenement', '>=', today())
            ->orderBy('date_evenement')
            ->first()
            ?? static::where('statut', self::PUBLIE)->orderByDesc('date_evenement')->first();
    }

    /** Au moins une inscription engagée (payée ou en cours de paiement) : date figée, plus de dépublication. */
    public function aDesInscrits(): bool
    {
        return $this->exists && $this->inscriptions()->whereIn('statut', Inscription::ENGAGEES)->exists();
    }

    /** Brouillon sans aucune inscription (même brouillon) : on peut le supprimer. */
    public function supprimable(): bool
    {
        return $this->statut === self::BROUILLON && ! $this->inscriptions()->exists();
    }

    public function inscriptionsOuvertes(): bool
    {
        return $this->statut === self::PUBLIE && now()->between($this->ouverture, $this->fermeture);
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
