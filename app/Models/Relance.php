<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Relance envoyée depuis l'admin à une ou plusieurs inscriptions (voir Services\Admin\Relances). */
class Relance extends Model
{
    protected $primaryKey = 'id_relance';

    public const UPDATED_AT = null;

    protected $fillable = ['modele', 'sujet', 'message', 'cible', 'nb_destinataires', 'id_evenement', 'id_admin'];

    protected function casts(): array
    {
        return ['cible' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function inscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Inscription::class, 'relance_destinataires', 'id_relance', 'id_inscription')
            ->withPivot('id_user', 'email');
    }
}
