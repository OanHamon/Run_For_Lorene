<?php

namespace App\Support;

use App\Models\Reglage;

/**
 * Listes du formulaire bénévole (brief complémentaire du 8 novembre 2026). Les intitulés et
 * descriptions sont ceux proposés par le brief, encore « à confirmer » par l'association :
 * c'est ici qu'on les corrige. Les codes sont enregistrés en base : ne pas les renommer une
 * fois des candidatures reçues, seulement les libellés.
 */
class Benevolat
{
    /** Adresse de l'organisation (pied de page du site) : réponses aux demandes de précisions. */
    public const CONTACT = 'contact@effervescence-jeunes.fr';

    /** @var array<string, array{libelle: string, description: string}> */
    public const MISSIONS = [
        'restauration' => [
            'libelle' => 'Restauration',
            'description' => 'Préparer et tenir le stand, accueillir le public et aider au service.',
        ],
        'stands' => [
            'libelle' => 'Stands du village',
            'description' => 'Aider à l\'installation, à la tenue et à l\'orientation sur les différents stands, selon les besoins des équipes et des associations.',
        ],
        'commissaires' => [
            'libelle' => 'Commissaire de course',
            'description' => 'Te positionner sur le parcours pour orienter les coureurs et contribuer à leur sécurité, avec les consignes de l\'organisation.',
        ],
        'accueil_associations' => [
            'libelle' => 'Accueil des associations et du public',
            'description' => 'Accueillir les intervenants, aider au stand d\'accueil du village et orienter les visiteurs.',
        ],
        'accueil_coureurs' => [
            'libelle' => 'Accueil des coureurs',
            'description' => 'Distribuer les dossards et les tee-shirts, selon les modalités de contrôle de l\'organisation.',
        ],
        'ravitaillement' => [
            'libelle' => 'Ravitaillement',
            'description' => 'Préparer les postes, distribuer le ravitaillement et aider au rangement.',
        ],
        'animations_parcours' => [
            'libelle' => 'Animations du parcours',
            'description' => 'Accompagner les animations et les personnes mobilisées le long de la course.',
        ],
        'animations_site' => [
            'libelle' => 'Animations du site et concerts',
            'description' => 'Aider à la coordination pratique, à l\'accueil des artistes et des intervenants, avec les équipes organisatrices.',
        ],
        'communication' => [
            'libelle' => 'Communication et création de contenu',
            'description' => 'Aider à produire les contenus du jour J : photo, vidéo, réseaux sociaux, relais médias.',
        ],
        'polyvalence' => [
            'libelle' => 'Polyvalence',
            'description' => 'Accepter des missions ponctuelles selon les besoins, dans le respect de tes disponibilités.',
        ],
    ];

    /** Compétences demandées à qui choisit la mission Communication. */
    public const COMPETENCES = [
        'photo' => 'Photo',
        'video' => 'Vidéo',
        'reseaux' => 'Réseaux sociaux',
        'medias' => 'Relais médias',
    ];

    /**
     * Tranches horaires du jour J. La dernière a été dictée « 16 h à 7 h » : son heure de fin
     * reste à confirmer, on ne la devine pas.
     */
    public const TRANCHES = [
        '7_10' => '7 h – 10 h',
        '10_12' => '10 h – 12 h',
        '12_14' => '12 h – 14 h',
        '14_16' => '14 h – 16 h',
        '16_fin' => '16 h – fin (horaire à confirmer)',
    ];

    /** Périodes autour du jour J (installation, désinstallation, rangement), décalage en jours. */
    public const PERIODES = [
        'veille' => ['libelle' => 'La veille', 'detail' => 'installation', 'decalage' => -1],
        'soir' => ['libelle' => 'Le soir même', 'detail' => 'désinstallation', 'decalage' => 0],
        'lendemain' => ['libelle' => 'Le lendemain', 'detail' => 'rangement, désinstallation', 'decalage' => 1],
    ];

    public const ETATS_PERIODE = [
        'disponible' => 'Disponible',
        'indisponible' => 'Indisponible',
        'a_preciser' => 'À préciser',
    ];

    /** Liste provisoire, à confirmer par l'association. */
    public const TAILLES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    /** Âge le jour de l'événement : pas d'âge minimum ; moins de 16 ans, autorisation parentale. */
    public const AGES = [
        'majeur' => '18 ans ou plus',
        '16_17' => '16 ou 17 ans',
        'moins_16' => 'Moins de 16 ans',
    ];

    public const SOIREE = [
        'oui' => 'Oui',
        'non' => 'Non',
        'a_confirmer' => 'À confirmer',
    ];

    /** Ouverture des candidatures, réglée dans l'admin (bénévoles attendus en février). */
    public static function ouvert(): bool
    {
        return (bool) Reglage::lire('benevoles', ['ouvert' => false])['ouvert'];
    }

    public static function ouvrir(bool $ouvert): void
    {
        Reglage::ecrire('benevoles', ['ouvert' => $ouvert]);
    }

    public static function mission(?string $code): ?string
    {
        return $code ? (self::MISSIONS[$code]['libelle'] ?? $code) : null;
    }
}
