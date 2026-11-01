<?php

namespace App\Support;

use App\Models\Evenement;

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

    /**
     * Destinataires en français simple, et la cible qui leur correspond (RelancesCiblees::cible).
     * « personne » : une seule inscription, choisie par son nom.
     */
    public const QUI = [
        'documents' => ['libelle' => 'Les personnes à qui il manque un document', 'aide' => 'Payé, mais la décharge ou le questionnaire de santé n\'est pas rempli.', 'cible' => ['statut' => 'dossier_incomplet']],
        'valides' => ['libelle' => 'Tous les inscrits validés', 'aide' => 'Dossier complet, dossard attribué.', 'cible' => ['statut' => 'validee']],
        'non_retires' => ['libelle' => 'Ceux qui n\'ont pas encore retiré leur dossard', 'aide' => 'Inscrits validés dont le dossard n\'a pas été remis.', 'cible' => ['statut' => 'validee', 'non_retire' => true]],
        'tous' => ['libelle' => 'Tous les inscrits', 'aide' => 'Validés et dossiers incomplets.', 'cible' => []],
        'mineurs' => ['libelle' => 'Les familles des inscrits mineurs', 'aide' => 'L\'email part à la personne qui a fait l\'inscription.', 'cible' => ['mineur' => true]],
        'personne' => ['libelle' => 'Une seule personne', 'aide' => 'Cherche-la par son nom.', 'cible' => []],
    ];

    /** Boutons « Insérer » au-dessus du message : libellé simple et variable correspondante. */
    public const INSERER = [
        '{prenom}' => 'Prénom',
        '{participant}' => 'Prénom du coureur',
        '{course}' => 'Course',
        '{heure}' => 'Heure de départ',
        '{dossard}' => 'Dossard',
        '{date}' => 'Date de l\'événement',
        '{documents}' => 'Documents qui manquent',
    ];

    /**
     * Texte lisible pour les listes de l'admin : le nom et la date de l'événement à leur place,
     * les informations propres à chaque personne entre crochets (« [prénom] »).
     */
    public static function lisible(string $texte, Evenement $evenement): string
    {
        return strtr($texte, [
            '{evenement}' => $evenement->nom,
            '{date}' => $evenement->date_evenement->translatedFormat('j F Y'),
        ] + collect(self::INSERER)->map(fn ($libelle) => '['.mb_strtolower($libelle).']')->all());
    }

    /** Cible complète depuis le choix « À qui ? », la course et l'inscription choisies. */
    public static function cible(string $qui, ?int $course = null, ?int $inscription = null): array
    {
        if ($qui === 'personne') {
            return ['inscription' => $inscription ?: 0];
        }

        return (self::QUI[$qui]['cible'] ?? []) + array_filter(['course' => $course]);
    }

    /** @return array<string, array{libelle: string, sujet: string, message: string, qui: string}> */
    public static function tous(): array
    {
        return [
            'documents' => [
                'libelle' => 'Documents manquants',
                'sujet' => 'Il manque un document pour prendre le départ',
                'message' => "Bonjour {prenom},\n\nL'inscription de {participant} à la {course} est bien réglée, merci !\n\nPour recevoir le dossard, il reste à remplir : {documents}. Ça prend deux minutes, depuis ton espace.",
                'qui' => 'documents',
            ],
            'infos' => [
                'libelle' => 'Infos pratiques avant le jour J',
                'sujet' => '{evenement} : tout ce qu\'il faut savoir',
                'message' => "Bonjour {prenom},\n\nLe grand jour approche : rendez-vous le {date} pour la {course}, départ à {heure}.\n\nDossard n° {dossard}. Pense à arriver un peu en avance pour le retrait.\n\nOn a hâte de courir avec toi !",
                'qui' => 'valides',
            ],
            'retrait' => [
                'libelle' => 'Retrait des dossards',
                'sujet' => 'Retrait de ton dossard',
                'message' => "Bonjour {prenom},\n\nLe dossard n° {dossard} de {participant} t'attend.\n\nPrésente ton billet (le QR code dans ton espace) au point de retrait, ou donne simplement ton nom.",
                'qui' => 'non_retires',
            ],
            'changement' => [
                'libelle' => 'Changement d\'horaire ou de lieu',
                'sujet' => '{evenement} : un changement pour la {course}',
                'message' => "Bonjour {prenom},\n\nUne information importante pour la {course} du {date} :\n\n[Décris ici le changement]\n\nMerci de ta compréhension, et à très vite !",
                'qui' => 'tous',
            ],
            'libre' => [
                'libelle' => 'Message libre',
                'sujet' => '',
                'message' => "Bonjour {prenom},\n\n",
                'qui' => 'tous',
            ],
        ];
    }

    public static function existe(?string $cle): bool
    {
        return is_string($cle) && array_key_exists($cle, self::tous());
    }
}
