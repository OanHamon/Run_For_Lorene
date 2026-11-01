<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Note interne d'un admin sur une inscription (« appelé le 12/03, certificat en attente »). */
class NoteInterne extends Model
{
    protected $table = 'notes_internes';

    protected $primaryKey = 'id_note';

    public const UPDATED_AT = null;

    protected $fillable = ['texte', 'id_inscription', 'id_admin'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }
}
