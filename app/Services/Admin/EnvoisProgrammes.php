<?php

namespace App\Services\Admin;

use App\Models\RelanceProgrammee;

/**
 * Fait partir les relances programmées arrivées à échéance (commande rfl:envois-programmes,
 * toutes les cinq minutes). Un envoi manqué (serveur arrêté) part au passage suivant, une
 * seule fois. Mêmes règles qu'un envoi immédiat : inscriptions réglées seulement, personnes
 * relancées il y a moins de 48 h écartées.
 */
class EnvoisProgrammes
{
    public function __construct(private RelancesCiblees $relances) {}

    /** @return int nombre d'envois faits */
    public function __invoke(): int
    {
        $faits = 0;

        RelanceProgrammee::with('evenement', 'admin')->where('actif', true)->get()
            ->filter(fn (RelanceProgrammee $p) => $p->estDue())
            ->each(function (RelanceProgrammee $p) use (&$faits) {
                // Marquée d'abord : un plantage pendant l'envoi ne le fait pas repartir en boucle.
                $p->forceFill(['derniere_execution' => now()])->save();
                $this->relances->envoyer($p->evenement, $p->modele, $p->sujet, $p->message, RelancesCiblees::cible($p->cible), false, $p->admin, $p);
                $faits++;
            });

        return $faits;
    }
}
