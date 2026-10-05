<?php

namespace Database\Seeders;

use App\Models\QuestionSante;
use Illuminate\Database\Seeder;

/**
 * Questionnaire de santé : questions du Google Form « Questionnaire santé Run for Lorène »
 * (questionnaire pour mineurs de l'arrêté du 7 mai 2021), par rubrique, à répondre par oui ou non.
 * Décharge : affirmations du Google Form « Décharge Responsabilité Adulte », à cocher, toutes
 * obligatoires.
 */
class QuestionnairesSeeder extends Seeder
{
    public const SANTE = [
        'annee' => [
            'Es-tu allé(e) à l\'hôpital pendant toute une journée ou plusieurs jours ?',
            'As-tu été opéré(e) ?',
            'As-tu beaucoup plus grandi que les autres années ?',
            'As-tu beaucoup maigri ou grossi ?',
            'As-tu eu la tête qui tourne pendant un effort ?',
            'As-tu perdu connaissance ou es-tu tombé(e) sans te souvenir de ce qui s\'était passé ?',
            'As-tu reçu un ou plusieurs chocs violents qui t\'ont obligé(e) à interrompre un moment une séance de sport ?',
            'As-tu eu beaucoup de mal à respirer pendant un effort par rapport à d\'habitude ?',
            'As-tu eu beaucoup de mal à respirer après un effort ?',
            'As-tu eu mal dans la poitrine ou des palpitations (le cœur qui bat très vite) ?',
            'As-tu commencé à prendre un nouveau médicament tous les jours et pour longtemps ?',
            'As-tu arrêté le sport à cause d\'un problème de santé pendant un mois ou plus ?',
        ],
        'semaines' => [
            'Te sens-tu très fatigué(e) ?',
            'As-tu du mal à t\'endormir ou te réveilles-tu souvent dans la nuit ?',
            'Sens-tu que tu as moins faim ? que tu manges moins ?',
            'Te sens-tu triste ou inquiet(e) ?',
            'Pleures-tu plus souvent ?',
            'Ressens-tu une douleur ou un manque de force à cause d\'une blessure que tu t\'es faite cette année ?',
        ],
        'aujourdhui' => [
            'Penses-tu quelquefois à arrêter de faire du sport ou à changer de sport ?',
            'Penses-tu avoir besoin de voir ton médecin pour continuer le sport ?',
            'Souhaites-tu signaler quelque chose de plus concernant ta santé ?',
        ],
        'parents' => [
            'Quelqu\'un dans votre famille proche a-t-il eu une maladie grave du cœur ou du cerveau, ou est-il décédé subitement avant l\'âge de 50 ans ?',
            'Êtes-vous inquiet pour son poids ? Trouvez-vous qu\'il se nourrit trop ou pas assez ?',
            'Avez-vous manqué l\'examen de santé prévu à l\'âge de votre enfant chez le médecin ? (Cet examen est prévu à 2, 3, 4 et 5 ans, entre 8 et 9 ans, entre 11 et 13 ans et entre 15 et 16 ans.)',
        ],
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
        $ordre = 0;
        foreach (self::SANTE as $rubrique => $libelles) {
            foreach ($libelles as $libelle) {
                QuestionSante::create([
                    'type_questionnaire' => QuestionSante::SANTE,
                    'libelle' => $libelle,
                    'rubrique' => $rubrique,
                    'ordre' => ++$ordre,
                    'version_questionnaire' => 1,
                    'active' => true,
                ]);
            }
        }

        foreach (self::DECHARGE as $index => $libelle) {
            QuestionSante::create([
                'type_questionnaire' => QuestionSante::DECHARGE,
                'libelle' => $libelle,
                'ordre' => $index + 1,
                'version_questionnaire' => 1,
                'active' => true,
            ]);
        }
    }
}
