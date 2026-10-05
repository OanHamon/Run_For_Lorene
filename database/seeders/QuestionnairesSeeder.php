<?php

namespace Database\Seeders;

use App\Models\QuestionSante;
use Illuminate\Database\Seeder;

/**
 * Santé : questions provisoires (version 1), en attendant celles du Google Form, à répondre par
 * oui ou non. Décharge : affirmations du Google Form, à cocher, toutes obligatoires.
 */
class QuestionnairesSeeder extends Seeder
{
    public const SANTE = [
        "Un membre de ta famille est-il décédé subitement d'une cause cardiaque ou inexpliquée ?",
        'As-tu déjà ressenti une douleur dans la poitrine, des palpitations ou un essoufflement inhabituel à l\'effort ?',
        'As-tu déjà eu un épisode de respiration sifflante (asthme) ?',
        'As-tu déjà perdu connaissance, à l\'effort ou au repos ?',
        'Au cours des 12 derniers mois, as-tu arrêté le sport 30 jours ou plus pour un problème de santé ?',
        'Suis-tu un traitement médical de longue durée (hors contraception et désensibilisation aux allergies) ?',
        'Ressens-tu une douleur, un manque de force ou une raideur suite à un problème osseux, articulaire ou musculaire ?',
        'As-tu été hospitalisé(e) au cours des 12 derniers mois ?',
        'Un médecin t\'a-t-il déjà déconseillé une activité physique ?',
        'Penses-tu avoir besoin d\'un avis médical avant de participer ?',
    ];

    /** Décharge : affirmations à cocher du Google Form « Décharge Responsabilité Adulte ». */
    public const DECHARGE = [
        'Je suis en bonne condition physique, je ne souffre d\'aucune blessure, maladie ou handicap, et je n\'ai jamais eu de problèmes cardiaques ou respiratoires décelés à ce jour.',
        'Aucun médecin, infirmier ou entraîneur ne m\'a déconseillé la pratique de ce sport.',
        'J\'ai une assurance en responsabilité civile.',
        'Je déclare dégager de toutes responsabilités en cas d\'accident de quelque nature que ce soit ou de dommages, sans aucune exception ni réserve, l\'organisation de la « RUN FOR LORÈNE » ainsi que toutes les personnes œuvrant pour l\'organisation.',
        'J\'ai lu le présent document et je comprends qu\'en complétant ce formulaire je renonce à des droits importants. C\'est donc en toute connaissance de cause que j\'envoie mes réponses.',
        'J\'envoie le présent formulaire volontairement et sans contrainte.',
    ];

    public function run(): void
    {
        foreach ([QuestionSante::SANTE => self::SANTE, QuestionSante::DECHARGE => self::DECHARGE] as $type => $libelles) {
            foreach ($libelles as $index => $libelle) {
                QuestionSante::create([
                    'type_questionnaire' => $type,
                    'libelle' => $libelle,
                    'ordre' => $index + 1,
                    'version_questionnaire' => 1,
                    'active' => true,
                ]);
            }
        }
    }
}
