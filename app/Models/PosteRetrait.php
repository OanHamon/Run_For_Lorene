<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Poste de retrait des dossards : un lien pour les bénévoles, sans compte, qui n'ouvre que la
 * page de retrait de son événement, aux dates prévues, tant qu'il n'est pas révoqué.
 */
class PosteRetrait extends Model
{
    protected $table = 'postes_retrait';

    protected $primaryKey = 'id_poste';

    protected $fillable = ['libelle', 'jeton_hash', 'valable_du', 'valable_au', 'id_evenement', 'id_admin'];

    protected function casts(): array
    {
        return ['valable_du' => 'date', 'valable_au' => 'date', 'revoque_le' => 'datetime'];
    }

    /** Crée le poste et renvoie le jeton en clair : il n'est montré qu'une fois (lien à partager). */
    public static function ouvrir(array $attributs): array
    {
        $jeton = Str::random(40);
        $poste = self::create($attributs + ['jeton_hash' => hash('sha256', $jeton)]);

        return [$poste, $jeton];
    }

    public static function parJeton(string $jeton): ?self
    {
        return self::with('evenement')->where('jeton_hash', hash('sha256', $jeton))->first();
    }

    public function utilisable(): bool
    {
        return $this->revoque_le === null && today()->betweenIncluded($this->valable_du, $this->valable_au);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }

    public function remises(): HasMany
    {
        return $this->hasMany(RemiseDossard::class, 'id_poste', 'id_poste');
    }
}
