<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benevole extends Model
{
    protected $table = 'benevoles';

    protected $primaryKey = 'id_user';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'poste',
        'disponibilites',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
