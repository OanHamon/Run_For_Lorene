<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historique des statuts d'une candidature bénévole : qui, quand, et le message envoyé. */
class CandidatureStatut extends Model
{
    protected $table = 'candidature_statuts';

    protected $primaryKey = 'id_statut';

    public const UPDATED_AT = null;

    protected $fillable = ['id_candidature', 'statut', 'id_admin', 'message', 'notifie'];

    protected function casts(): array
    {
        return ['notifie' => 'boolean'];
    }

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(CandidatureBenevole::class, 'id_candidature', 'id_candidature');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }
}
