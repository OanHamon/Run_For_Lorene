<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réglage modifiable depuis l'admin (clé, valeur JSON), lu avec une valeur par défaut. */
class Reglage extends Model
{
    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur'];

    protected function casts(): array
    {
        return ['valeur' => 'array'];
    }

    /** Valeur enregistrée, complétée par les valeurs par défaut (clé par clé). */
    public static function lire(string $cle, array $defaut): array
    {
        return array_replace($defaut, self::find($cle)?->valeur ?? []);
    }

    public static function ecrire(string $cle, array $valeur): void
    {
        self::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}
