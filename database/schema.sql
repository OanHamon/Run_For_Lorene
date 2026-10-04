-- Run For Lorène — schéma de base de données V1
-- Généré à partir de docs/spec.md (sections "Modèle de données" et "Règles métier et statuts")
-- MySQL 8+, InnoDB, utf8mb4_unicode_ci — base locale de dev : `rfl`
--
-- Ne couvre que le modèle métier (MCD/MLD du spec). Les tables d'infrastructure Laravel
-- (users... non, ici `users` EST une table métier — voir plus bas ; cache, jobs, sessions,
-- password_reset_tokens) existent déjà via les migrations du starter kit et ne sont pas
-- reprises ici.
--
-- Décisions de modélisation à signaler (spec ambigu, résolu ici plutôt qu'inventé en silence) :
--
-- 1. `inscriptions.id_tarif` et `inscriptions.id_commande` sont tous les deux marqués comme
--    obligatoires (cardinalité 11 côté INSCRIPTION) dans le MCD. Mais le texte du spec dit
--    explicitement que la commande n'est créée qu'au récapitulatif (étape 6/7), alors que le
--    statut "Brouillon" est le tout premier état d'une inscription (tunnel étapes 1 à 5).
--    Une inscription en brouillon ne peut donc pas avoir de commande obligatoire : contradiction
--    directe avec le MCD strict. Résolu ici en rendant `id_commande` NULLABLE avec un CHECK qui
--    encode la règle réelle telle que décrite dans le texte :
--        CHECK (statut = 'brouillon' OR id_commande IS NOT NULL)
--    `id_tarif` reste NOT NULL (conforme au MCD) : l'application ne doit créer la ligne
--    `inscriptions` qu'une fois le tarif calculable (étape "Toi", après saisie de la date de
--    naissance) ; les données des étapes 1-2 restent en session tant qu'aucun tarif n'est connu.
--
-- 2. Domaines non définis textuellement dans le spec (`evenements.statut`, `commandes.statut`,
--    `documents.statut`, `participants.sexe`, `option_evenements`/`declinaisons` sans borne
--    de stock...) : colonnes VARCHAR sans CHECK rigide, avec un commentaire "à confirmer".
--    Là où le texte du spec donne explicitement le vocabulaire (statuts d'inscription, types de
--    texte légal, niveaux d'accès admin, statuts de paiement 'reussi'/'echoue'), un vrai CHECK
--    est posé.
--
-- 3. `email_verifie_le` et `remember_token` sur `users` : absents du MCD/MLD (qui ne modélise
--    pas la mécanique d'auth Laravel), mais nécessaires pour la vérification d'email et le
--    "remember me" déjà fournis par le starter kit (lot 1 du spec : "authentification... vérification").
--
-- 4. Conservation 12 mois + purge (documents, consentements, réponses santé) : colonne
--    `deleted_at` (soft delete) sur `documents` conformément à CLAUDE.md. La tâche planifiée de
--    purge elle-même n'est pas dans ce script (hors sujet "schéma").

CREATE DATABASE IF NOT EXISTS `rfl` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rfl`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ÉVÉNEMENT
-- ============================================================================

DROP TABLE IF EXISTS `evenements`;
CREATE TABLE `evenements` (
    `id_evenement`   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`            VARCHAR(150) NOT NULL,
    `date_evenement` DATE NOT NULL,
    `ouverture`      DATETIME NOT NULL COMMENT 'Ouverture des inscriptions',
    `fermeture`      DATETIME NOT NULL COMMENT 'Fermeture des inscriptions',
    `statut`         VARCHAR(30) NOT NULL DEFAULT 'brouillon' COMMENT 'Domaine non défini dans le spec — à confirmer avec l''association',
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- USERS / PARTICIPANTS / ADMINS / BÉNÉVOLES
-- Héritage /XT\ traduit en une table par entité, la table fille partageant la clé de USER.
-- ============================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id_user`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`            VARCHAR(254) NULL COMMENT 'NULL pour un participant géré sans compte (doit alors avoir id_responsable)',
    `mot_de_passe`     VARCHAR(255) NULL COMMENT 'Hash Laravel ; NULL pour un participant géré sans compte',
    `nom`              VARCHAR(100) NOT NULL,
    `prenom`           VARCHAR(100) NOT NULL,
    `telephone`        VARCHAR(20) NULL,
    `email_verifie_le` DATETIME NULL COMMENT 'Hors MCD/MLD — mécanique Laravel (vérification email, écran 04)',
    `remember_token`   VARCHAR(100) NULL COMMENT 'Hors MCD/MLD — mécanique Laravel (remember me)',
    `created_at`       TIMESTAMP NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_user`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id_user`      INT UNSIGNED NOT NULL,
    `niveau_acces` TINYINT UNSIGNED NOT NULL COMMENT '1=lecture seule, 2=gestion inscriptions/documents, 3=super admin',
    PRIMARY KEY (`id_user`),
    CONSTRAINT `admins_id_user_foreign`
        FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
    CONSTRAINT `admins_niveau_acces_check` CHECK (`niveau_acces` IN (1, 2, 3))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `benevoles`;
CREATE TABLE `benevoles` (
    `id_user`        INT UNSIGNED NOT NULL,
    `poste`          VARCHAR(100) NULL,
    `disponibilites` TEXT NULL COMMENT 'Domaine non détaillé dans le spec V1 (entité gardée sans écran ; V2 : table dédiée)',
    PRIMARY KEY (`id_user`),
    CONSTRAINT `benevoles_id_user_foreign`
        FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
    `id_user`        INT UNSIGNED NOT NULL,
    `sexe`           VARCHAR(10) NULL COMMENT 'Domaine non figé textuellement (maquette : femme/homme/autre) — à confirmer',
    `date_naissance` DATE NOT NULL COMMENT 'Âge calculé à la date de l''événement, pas à la date d''inscription',
    `adresse`        VARCHAR(255) NULL,
    `code_postal`    VARCHAR(10) NULL,
    `ville`          VARCHAR(100) NULL,
    `pays`           VARCHAR(100) NOT NULL DEFAULT 'France',
    `id_responsable` INT UNSIGNED NULL COMMENT 'Titulaire du compte responsable, pour un participant géré (enfant)',
    PRIMARY KEY (`id_user`),
    CONSTRAINT `participants_id_user_foreign`
        FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
    CONSTRAINT `participants_id_responsable_foreign`
        FOREIGN KEY (`id_responsable`) REFERENCES `users` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COURSES / TARIFS / OPTIONS
-- ============================================================================

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
    `id_course`            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nom`                  VARCHAR(100) NOT NULL,
    `distance`             DECIMAL(5,2) UNSIGNED NULL COMMENT 'En kilomètres',
    `heure_depart`         TIME NOT NULL,
    `age_min`              TINYINT UNSIGNED NULL,
    `age_max`              TINYINT UNSIGNED NULL,
    `quota`                SMALLINT UNSIGNED NULL COMMENT 'NULL = illimité',
    `questionnaire_requis` TINYINT(1) NOT NULL DEFAULT 0,
    `dossard_debut`        SMALLINT UNSIGNED NULL,
    `dossard_fin`          SMALLINT UNSIGNED NULL,
    `id_evenement`         SMALLINT UNSIGNED NOT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_course`),
    CONSTRAINT `courses_id_evenement_foreign`
        FOREIGN KEY (`id_evenement`) REFERENCES `evenements` (`id_evenement`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tarifs`;
CREATE TABLE `tarifs` (
    `id_tarif`  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `libelle`   VARCHAR(100) NOT NULL,
    `montant`   DECIMAL(8,2) NOT NULL,
    `valide_du` DATE NOT NULL,
    `valide_au` DATE NOT NULL,
    `age_min`   TINYINT UNSIGNED NULL,
    `age_max`   TINYINT UNSIGNED NULL,
    `id_course` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_tarif`),
    CONSTRAINT `tarifs_id_course_foreign`
        FOREIGN KEY (`id_course`) REFERENCES `courses` (`id_course`) ON DELETE RESTRICT,
    CONSTRAINT `tarifs_montant_check` CHECK (`montant` >= 0),
    CONSTRAINT `tarifs_periode_check` CHECK (`valide_au` >= `valide_du`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `option_evenements`;
CREATE TABLE `option_evenements` (
    `id_option`    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `libelle`      VARCHAR(100) NOT NULL COMMENT 'Ex : "T-shirt"',
    `prix`         DECIMAL(8,2) NOT NULL,
    `id_evenement` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_option`),
    CONSTRAINT `option_evenements_id_evenement_foreign`
        FOREIGN KEY (`id_evenement`) REFERENCES `evenements` (`id_evenement`) ON DELETE RESTRICT,
    CONSTRAINT `option_evenements_prix_check` CHECK (`prix` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `declinaisons`;
CREATE TABLE `declinaisons` (
    `id_declinaison` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `libelle`        VARCHAR(50) NOT NULL COMMENT 'Ex : taille XS/S/M/L/XL',
    `stock`          SMALLINT UNSIGNED NULL COMMENT 'NULL = illimité ; 0 = épuisé (taille grisée)',
    `id_option`      SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_declinaison`),
    CONSTRAINT `declinaisons_id_option_foreign`
        FOREIGN KEY (`id_option`) REFERENCES `option_evenements` (`id_option`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TEXTES LÉGAUX
-- ============================================================================

DROP TABLE IF EXISTS `texte_legals`;
CREATE TABLE `texte_legals` (
    `id_texte`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type_texte`       VARCHAR(30) NOT NULL,
    `version`          SMALLINT UNSIGNED NOT NULL,
    `date_publication` DATE NOT NULL,
    `fichier`          VARCHAR(255) NOT NULL COMMENT 'Chemin sur le disque privé Laravel (jamais d''URL publique)',
    `id_evenement`     SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_texte`),
    CONSTRAINT `texte_legals_id_evenement_foreign`
        FOREIGN KEY (`id_evenement`) REFERENCES `evenements` (`id_evenement`) ON DELETE RESTRICT,
    CONSTRAINT `texte_legals_type_texte_check`
        CHECK (`type_texte` IN ('reglement', 'decharge', 'autorisation_parentale', 'rgpd', 'droit_image')),
    UNIQUE KEY `texte_legals_evenement_type_version_unique` (`id_evenement`, `type_texte`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COMMANDES / INSCRIPTIONS
-- ============================================================================

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE `commandes` (
    `id_commande`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date_commande` DATETIME NOT NULL,
    `montant_total` DECIMAL(8,2) NOT NULL COMMENT 'Calculé une seule fois à la création, figé ensuite (montant envoyé à Stripe)',
    `statut`        VARCHAR(30) NOT NULL DEFAULT 'en_cours' COMMENT 'Valeur connue du spec : "gratuite" (inscription manuelle admin) ; reste du domaine à confirmer',
    `id_user`       INT UNSIGNED NOT NULL COMMENT 'Le donateur/payeur : celui qui passe la commande',
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_commande`),
    CONSTRAINT `commandes_id_user_foreign`
        FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT,
    CONSTRAINT `commandes_montant_total_check` CHECK (`montant_total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inscriptions`;
CREATE TABLE `inscriptions` (
    `id_inscription` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prix_paye`      DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'Prix historisé au moment de l''achat',
    `statut`         VARCHAR(30) NOT NULL DEFAULT 'brouillon',
    `dossard`        SMALLINT UNSIGNED NULL COMMENT 'Rempli à la validation, dans la plage de la course',
    `urgence_nom`    VARCHAR(150) NULL,
    `urgence_tel`    VARCHAR(20) NULL,
    `date_retrait`   DATE NULL COMMENT 'Signification exacte non détaillée dans le texte du spec — à confirmer',
    `id_participant` INT UNSIGNED NOT NULL COMMENT 'FK vers users.id_user (partagée avec la table participants)',
    `id_tarif`       SMALLINT UNSIGNED NOT NULL,
    `id_commande`    INT UNSIGNED NULL COMMENT 'NULL tant que statut = brouillon (commande créée au récapitulatif) — voir note en tête de fichier',
    `id_evenement`   SMALLINT UNSIGNED NOT NULL COMMENT 'Dénormalisation assumée (se déduit du tarif) : sert aux UNIQUE ci-dessous',
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_inscription`),
    CONSTRAINT `inscriptions_id_participant_foreign`
        FOREIGN KEY (`id_participant`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT,
    CONSTRAINT `inscriptions_id_tarif_foreign`
        FOREIGN KEY (`id_tarif`) REFERENCES `tarifs` (`id_tarif`) ON DELETE RESTRICT,
    CONSTRAINT `inscriptions_id_commande_foreign`
        FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`) ON DELETE RESTRICT,
    CONSTRAINT `inscriptions_id_evenement_foreign`
        FOREIGN KEY (`id_evenement`) REFERENCES `evenements` (`id_evenement`) ON DELETE RESTRICT,
    CONSTRAINT `inscriptions_statut_check`
        CHECK (`statut` IN ('brouillon', 'en_attente_paiement', 'validee', 'dossier_incomplet', 'annulee')),
    CONSTRAINT `inscriptions_prix_paye_check` CHECK (`prix_paye` >= 0),
    CONSTRAINT `inscriptions_commande_requise_check`
        CHECK (`statut` = 'brouillon' OR `id_commande` IS NOT NULL),
    UNIQUE KEY `inscriptions_evenement_participant_unique` (`id_evenement`, `id_participant`),
    UNIQUE KEY `inscriptions_evenement_dossard_unique` (`id_evenement`, `dossard`),
    KEY `inscriptions_statut_index` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inscription_declinaisons`;
CREATE TABLE `inscription_declinaisons` (
    `id_inscription` INT UNSIGNED NOT NULL,
    `id_declinaison` SMALLINT UNSIGNED NOT NULL,
    `quantite`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `prix_unitaire`  DECIMAL(8,2) NOT NULL COMMENT 'Historisé au moment de l''achat',
    PRIMARY KEY (`id_inscription`, `id_declinaison`),
    CONSTRAINT `inscription_declinaisons_id_inscription_foreign`
        FOREIGN KEY (`id_inscription`) REFERENCES `inscriptions` (`id_inscription`) ON DELETE CASCADE,
    CONSTRAINT `inscription_declinaisons_id_declinaison_foreign`
        FOREIGN KEY (`id_declinaison`) REFERENCES `declinaisons` (`id_declinaison`) ON DELETE RESTRICT,
    CONSTRAINT `inscription_declinaisons_prix_unitaire_check` CHECK (`prix_unitaire` >= 0),
    CONSTRAINT `inscription_declinaisons_quantite_check` CHECK (`quantite` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DONS / PAIEMENTS / REMBOURSEMENTS
-- ============================================================================

DROP TABLE IF EXISTS `dons`;
CREATE TABLE `dons` (
    `id_don`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `montant`              DECIMAL(8,2) NOT NULL,
    `recu_fiscal_demande`  TINYINT(1) NOT NULL DEFAULT 0,
    `anonyme`              TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Masque le nom dans les affichages publics',
    `id_commande`          INT UNSIGNED NOT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_don`),
    CONSTRAINT `dons_id_commande_foreign`
        FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`) ON DELETE CASCADE,
    CONSTRAINT `dons_montant_check` CHECK (`montant` >= 0),
    UNIQUE KEY `dons_id_commande_unique` (`id_commande`) COMMENT 'Une commande contient au plus un don'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `paiements`;
CREATE TABLE `paiements` (
    `id_paiement`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `prestataire`   VARCHAR(30) NOT NULL DEFAULT 'stripe',
    `reference`     VARCHAR(100) NOT NULL COMMENT 'Identifiant Stripe',
    `montant`       DECIMAL(8,2) NOT NULL,
    `frais`         DECIMAL(8,2) NULL,
    `statut`        VARCHAR(30) NOT NULL,
    `date_paiement` DATETIME NULL,
    `id_commande`   INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_paiement`),
    CONSTRAINT `paiements_id_commande_foreign`
        FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`) ON DELETE RESTRICT,
    CONSTRAINT `paiements_statut_check` CHECK (`statut` IN ('en_attente', 'reussi', 'echoue')),
    CONSTRAINT `paiements_montant_check` CHECK (`montant` >= 0),
    CONSTRAINT `paiements_frais_check` CHECK (`frais` IS NULL OR `frais` >= 0),
    UNIQUE KEY `paiements_reference_unique` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `remboursements`;
CREATE TABLE `remboursements` (
    `id_remboursement`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `montant`            DECIMAL(8,2) NOT NULL,
    `motif`              VARCHAR(255) NULL,
    `date_remboursement` DATETIME NOT NULL,
    `id_paiement`        INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_remboursement`),
    CONSTRAINT `remboursements_id_paiement_foreign`
        FOREIGN KEY (`id_paiement`) REFERENCES `paiements` (`id_paiement`) ON DELETE RESTRICT,
    CONSTRAINT `remboursements_montant_check` CHECK (`montant` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DOCUMENTS / QUESTIONNAIRE SANTÉ / CONSENTEMENTS
-- /XT\ DOCUMENT -> QUESTIONNAIRE_SANTE, CONSENTEMENT (héritage exclusif)
-- ============================================================================

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id_document`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date_depot`      DATETIME NOT NULL,
    `statut`          VARCHAR(30) NOT NULL DEFAULT 'en_attente' COMMENT 'Domaine non figé textuellement (implicite via motif_refus/date_validation) — à confirmer',
    `motif_refus`     VARCHAR(255) NULL,
    `date_validation` DATETIME NULL,
    `id_deposant`     INT UNSIGNED NOT NULL COMMENT 'FK users : le déposant (titulaire ou responsable)',
    `id_validateur`   INT UNSIGNED NULL COMMENT 'FK users (admin) : qui a validé/refusé',
    `id_inscription`  INT UNSIGNED NOT NULL,
    `deleted_at`      TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete — purge planifiée 12 mois après l''événement',
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id_document`),
    CONSTRAINT `documents_id_deposant_foreign`
        FOREIGN KEY (`id_deposant`) REFERENCES `users` (`id_user`) ON DELETE RESTRICT,
    CONSTRAINT `documents_id_validateur_foreign`
        FOREIGN KEY (`id_validateur`) REFERENCES `users` (`id_user`) ON DELETE SET NULL,
    CONSTRAINT `documents_id_inscription_foreign`
        FOREIGN KEY (`id_inscription`) REFERENCES `inscriptions` (`id_inscription`) ON DELETE CASCADE,
    KEY `documents_statut_index` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `questionnaire_santes`;
CREATE TABLE `questionnaire_santes` (
    `id_document`        INT UNSIGNED NOT NULL,
    `type_questionnaire` ENUM('sante','decharge') NOT NULL DEFAULT 'sante',
    `attestation`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Attestation finale du questionnaire',
    PRIMARY KEY (`id_document`),
    CONSTRAINT `questionnaire_santes_id_document_foreign`
        FOREIGN KEY (`id_document`) REFERENCES `documents` (`id_document`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `consentements`;
CREATE TABLE `consentements` (
    `id_document` INT UNSIGNED NOT NULL,
    `accepte`     TINYINT(1) NOT NULL,
    `ip`          VARCHAR(45) NOT NULL COMMENT 'IPv4 ou IPv6',
    `id_texte`    SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_document`),
    CONSTRAINT `consentements_id_document_foreign`
        FOREIGN KEY (`id_document`) REFERENCES `documents` (`id_document`) ON DELETE CASCADE,
    CONSTRAINT `consentements_id_texte_foreign`
        FOREIGN KEY (`id_texte`) REFERENCES `texte_legals` (`id_texte`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `question_santes`;
CREATE TABLE `question_santes` (
    `id_question`           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type_questionnaire`    ENUM('sante','decharge') NOT NULL DEFAULT 'sante',
    `libelle`               VARCHAR(255) NOT NULL,
    `ordre`                 SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `version_questionnaire` SMALLINT UNSIGNED NOT NULL,
    `active`                TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_question`),
    KEY `question_santes_type_version_index` (`type_questionnaire`, `version_questionnaire`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reponse_santes`;
CREATE TABLE `reponse_santes` (
    `id_document` INT UNSIGNED NOT NULL,
    `id_question` SMALLINT UNSIGNED NOT NULL,
    `reponse`     TEXT NOT NULL COMMENT 'Chiffré côté application (cast Laravel ''encrypted'') — jamais exposé sans droit d''accès',
    PRIMARY KEY (`id_document`, `id_question`),
    CONSTRAINT `reponse_santes_id_document_foreign`
        FOREIGN KEY (`id_document`) REFERENCES `questionnaire_santes` (`id_document`) ON DELETE CASCADE,
    CONSTRAINT `reponse_santes_id_question_foreign`
        FOREIGN KEY (`id_question`) REFERENCES `question_santes` (`id_question`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
