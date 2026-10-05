<?php

namespace Database\Seeders;

use App\Models\QuestionSante;
use Illuminate\Database\Seeder;

/**
 * Questionnaire de santé : questions du Google Form « Questionnaire santé Run for Lorène »
 * (questionnaire pour mineurs de l'arrêté du 7 mai 2021), par rubrique. Décharge : questions
 * provisoires en attendant le texte définitif. Toutes se répondent par oui ou non.
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

    public const DECHARGE = [
        'Reconnais-tu participer à Run For Lorène sous ta propre responsabilité ?',
        'As-tu pris connaissance du parcours et de ses difficultés ?',
        'T\'engages-tu à respecter le code de la route sur les portions ouvertes à la circulation ?',
        'T\'engages-tu à suivre les consignes des signaleurs et des bénévoles ?',
        'Sais-tu que l\'association n\'est pas responsable des vols ou pertes d\'objets personnels ?',
        'Es-tu couvert(e) par une assurance responsabilité civile ?',
        'Acceptes-tu d\'être pris(e) en charge par les secours si ton état le nécessite ?',
        'T\'engages-tu à t\'arrêter en cas de malaise ou de douleur ?',
        'Sais-tu que l\'événement n\'est ni chronométré ni compétitif ?',
        'Renonces-tu à tout recours contre l\'organisateur pour un accident qui ne lui est pas imputable ?',
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
