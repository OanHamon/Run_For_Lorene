<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consentement extends Model
{
    protected $table = 'consentements';

    protected $primaryKey = 'id_document';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_document',
        'accepte',
        'ip',
        'id_texte',
    ];

    protected function casts(): array
    {
        return [
            'accepte' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id_document', 'id_document');
    }

    public function texteLegal(): BelongsTo
    {
        return $this->belongsTo(TexteLegal::class, 'id_texte', 'id_texte');
    }
}
