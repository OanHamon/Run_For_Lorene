<?php

namespace App\Services\Admin;

use App\Models\JournalAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Note une action admin sensible dans le journal, avec son auteur et l'heure (spec : traçabilité). */
class Journal
{
    /** @param  array<string, mixed>  $details */
    public function noter(string $action, ?Model $cible = null, array $details = []): JournalAdmin
    {
        return JournalAdmin::create([
            'id_admin' => Auth::id(),
            'action' => $action,
            'cible_type' => $cible ? self::type($cible) : null,
            'cible_id' => $cible?->getKey(),
            'details' => $details ?: null,
        ]);
    }

    /** Table au singulier : « inscriptions » → « inscription », « option_evenements » → « option ». */
    public static function type(Model $cible): string
    {
        return $cible->getTable() === 'option_evenements' ? 'option' : rtrim($cible->getTable(), 's');
    }
}
