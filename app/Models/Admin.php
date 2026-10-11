<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Admin extends Model
{
    protected $table = 'admins';

    protected $primaryKey = 'id_user';

    public $incrementing = false;

    public $timestamps = false;

    /** Niveaux d'accès (spec) : chacun inclut les droits des niveaux inférieurs. */
    public const LECTURE = 1;

    public const GESTION = 2;

    public const SUPER = 3;

    public const LIBELLES = [
        self::LECTURE => 'Lecture seule',
        self::GESTION => 'Inscriptions et documents',
        self::SUPER => 'Super admin',
    ];

    protected $fillable = [
        'id_user',
        'niveau_acces',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
