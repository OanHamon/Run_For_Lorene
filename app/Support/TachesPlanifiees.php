<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;

/**
 * Dernière exécution de chaque tâche planifiée (purge RGPD, relances automatiques), notée à la
 * fin de la tâche par un écouteur (AppServiceProvider) : la page « État technique » montre
 * ainsi qu'un planificateur arrêté sur le serveur ne fait plus rien.
 */
class TachesPlanifiees
{
    public static function nom(Event $event): string
    {
        return preg_match('/(rfl:[\w:-]+)/', (string) $event->command, $m) ? $m[1] : ($event->description ?: (string) $event->command);
    }

    public static function noter(Event $event, bool $reussie): void
    {
        self::noterNom(self::nom($event), $reussie);
    }

    /** Même chose pour une tâche lancée par le réveil (App\Services\Reveil), hors planificateur. */
    public static function noterNom(string $nom, bool $reussie): void
    {
        Cache::forever("tache-planifiee:{$nom}", ['le' => now()->toIso8601String(), 'reussie' => $reussie]);
    }

    /** @return array{le: string, reussie: bool}|null */
    public static function derniere(string $nom): ?array
    {
        return Cache::get("tache-planifiee:{$nom}");
    }
}
