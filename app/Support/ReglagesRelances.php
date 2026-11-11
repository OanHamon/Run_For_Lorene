<?php

namespace App\Support;

use App\Models\Reglage;

/**
 * Réglage de la relance automatique du site (documents manquants), modifiable dans l'admin
 * (page Relances) : 15 jours avant l'événement à 10 h, en pause par défaut. La relance de
 * paiement a disparu avec l'inscription d'une traite.
 */
class ReglagesRelances
{
    public const DOCUMENTS = ['actif' => false, 'jours_avant' => 15, 'heure' => '10:00'];

    /** @return array{actif: bool, jours_avant: int, heure: string} */
    public static function documents(): array
    {
        $r = Reglage::lire('relance.documents', self::DOCUMENTS);

        return ['actif' => (bool) $r['actif'], 'jours_avant' => (int) $r['jours_avant'], 'heure' => (string) $r['heure']];
    }
}
