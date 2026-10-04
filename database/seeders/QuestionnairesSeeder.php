<?php

namespace Database\Seeders;

use App\Models\QuestionSante;
use Illuminate\Database\Seeder;

/**
 * Questions provisoires (version 1), en attendant celles du Google Form de santé et le texte
 * définitif de la décharge. Toutes se répondent par oui ou non.
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
