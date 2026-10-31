<?php

namespace App\Support;

/**
 * Modèles de relance prêts à l'emploi : objet, message et cible proposés par défaut, tous
 * modifiables au moment de l'envoi. Les variables entre accolades sont remplacées pour chaque
 * destinataire (voir RelancesCiblees::variables()).
 *
 * Uniquement des messages liés à l'inscription ; aucune relance d'une inscription non
 * finalisée (l'inscription se fait d'une traite).
 */
class ModelesRelance
{
    public const VARIABLES = [
        '{prenom}' => 'prénom de la personne qui reçoit l\'email',
        '{participant}' => 'prénom de la personne inscrite',
        '{course}' => 'nom de la course',
        '{heure}' => 'heure de départ',
        '{dossard}' => 'numéro de dossard (ou « bientôt attribué »)',
        '{evenement}' => 'nom de l\'événement',
        '{date}' => 'date de l\'événement',
        '{documents}' => 'documents qui manquent',
    ];

    /** @return array<string, array{libelle: string, sujet: string, message: string, cible: array<string, mixed>}> */
    public static function tous(): array
    {
        return [
            'documents' => [
                'libelle' => 'Documents manquants',
                'sujet' => 'Il manque un document pour prendre le départ',
                'message' => "Bonjour {prenom},\n\nL'inscription de {participant} à la {course} est bien réglée, merci !\n\nPour recevoir le dossard, il reste à remplir : {documents}. Ça prend deux minutes, depuis ton espace.",
                'cible' => ['statut' => 'dossier_incomplet'],
            ],
            'infos' => [
                'libelle' => 'Infos pratiques avant le jour J',
                'sujet' => '{evenement} : tout ce qu\'il faut savoir',
                'message' => "Bonjour {prenom},\n\nLe grand jour approche : rendez-vous le {date} pour la {course}, départ à {heure}.\n\nDossard n° {dossard}. Pense à arriver un peu en avance pour le retrait.\n\nOn a hâte de courir avec toi !",
                'cible' => ['statut' => 'validee'],
            ],
            'retrait' => [
                'libelle' => 'Retrait des dossards',
                'sujet' => 'Retrait de ton dossard',
                'message' => "Bonjour {prenom},\n\nLe dossard n° {dossard} de {participant} t'attend.\n\nPrésente ton billet (le QR code dans ton espace) au point de retrait, ou donne simplement ton nom.",
                'cible' => ['statut' => 'validee', 'non_retire' => true],
            ],
            'changement' => [
                'libelle' => 'Changement d\'horaire ou de lieu',
                'sujet' => '{evenement} : un changement pour la {course}',
                'message' => "Bonjour {prenom},\n\nUne information importante pour la {course} du {date} :\n\n[Décris ici le changement]\n\nMerci de ta compréhension, et à très vite !",
                'cible' => [],
            ],
            'libre' => [
                'libelle' => 'Message libre',
                'sujet' => '',
                'message' => "Bonjour {prenom},\n\n",
                'cible' => [],
            ],
        ];
    }

    public static function existe(?string $cle): bool
    {
        return is_string($cle) && array_key_exists($cle, self::tous());
    }
}
