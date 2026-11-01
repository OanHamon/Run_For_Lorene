<?php

namespace App\Support;

use App\Models\Reglage;

/**
 * Réglages des deux relances automatiques du site, modifiables dans l'admin (page Relances).
 * Par défaut, les valeurs du spec : documents manquants 15 jours avant l'événement à 10 h,
 * paiement non abouti 24 h après la tentative.
 */
class ReglagesRelances
{
    public const DOCUMENTS = ['actif' => true, 'jours_avant' => 15, 'heure' => '10:00'];

    public const PAIEMENT = ['actif' => true, 'heures_apres' => 24];

    /** @return array{actif: bool, jours_avant: int, heure: string} */
    public static function documents(): array
    {
        $r = Reglage::lire('relance.documents', self::DOCUMENTS);

        return ['actif' => (bool) $r['actif'], 'jours_avant' => (int) $r['jours_avant'], 'heure' => (string) $r['heure']];
    }

    /** @return array{actif: bool, heures_apres: int} */
    public static function paiement(): array
    {
        $r = Reglage::lire('relance.paiement', self::PAIEMENT);

        return ['actif' => (bool) $r['actif'], 'heures_apres' => (int) $r['heures_apres']];
    }
}
