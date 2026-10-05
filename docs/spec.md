# Run For Lorène · Spécifications V1

Sep 28, 2026 · @tidi998

## Contexte et objectifs

La V1 remplace TimePulse par une plateforme d'inscription propre à Run For Lorène, pour l'édition RFL3 du samedi 3 avril 2027 à Nantes. L'événement est organisé par l'association Effervescence Jeunes, sur le thème de la santé mentale des jeunes.

- **Pourquoi** : TimePulse fonctionne mais reste trop générique. Son parcours manque de guidage et son admin ne colle pas aux usages de l'asso.
- **Volume de référence** : 23 000 € collectés lors de la dernière édition via TimePulse.
- **Objectif V1** : un formulaire d'inscription basique, complet et fiable, avec paiement en ligne et documents légaux obligatoires.
- **Esprit** : événement non compétitif, aucune course chronométrée, pas de licence demandée, un ton jeune et bienveillant.

**Pour Claude Code** : ce document est la source de vérité. Construire lot par lot dans l'ordre de la section « Découpage en lots ». Chaque lot doit être testable seul avant de passer au suivant. En cas de doute, poser la question plutôt que d'inventer une règle métier.

## Périmètre

La V1 couvre l'inscription de bout en bout, du paramétrage de l'événement au suivi admin. Tout ce qui touche aux groupes et invitations est reporté.

| Dans la V1 | Hors V1 (plus tard) |
| --- | --- |
| Page publique de l'événement | Invitations et liens personnalisés (partenaires, écoles, groupes) |
| Compte utilisateur et participants gérés (enfants sans compte) | Tiers payeur global |
| Tunnel d'inscription en panneaux | Gestion des bénévoles (entité gardée dans le modèle, sans écran) |
| Questionnaire de santé intégré au site (reprise du Google Form actuel) et consentements | Éditeur des questions de santé dans l'admin (option) |
| Paiement Stripe (carte + Apple Pay), dons | Paramétrage des types de documents exigés par course |
| Dossards attribués automatiquement |  |
| Espace participant (dashboard) |  |
| Back-office admin : paramétrage, suivi, validation, export |  |
| Emails transactionnels |  |

## Stack et contraintes techniques

Laravel 13 (PHP 8.3 minimum) sur MySQL, développé sous WSL dans `/var/www/html/RFL`, hébergé chez OVH : l'application doit rester souveraine et simple à maintenir par une petite équipe.

| Sujet | Choix | Remarque |
| --- | --- | --- |
| Framework | Laravel (dernière version stable) | Blade + composants, pas de SPA nécessaire |
| Base de données | MySQL, InnoDB, `utf8mb4_unicode_ci` (base locale `rfl`) | Types détaillés dans la section Modèle de données |
| Hébergement | OVH | Type d'offre encore à vérifier (mutualisé, VPS ou cloud) |
| Paiement | Stripe Checkout | Apple Pay activé via Checkout, webhooks obligatoires |
| Emails | Brevo | Brief : gratuit jusqu'à 300 mails, environ 20 €/mois au-delà (limites à revérifier) |
| Fichiers | Stockage privé Laravel (disque local non public) | Jamais d'URL publique sur un justificatif |
| Front | Blade, CSS maison ou Tailwind, JS léger pour les panneaux | Transitions de panneaux sans rechargement |

Contraintes transverses :

- **Clés primaires** : utiliser `increments()` ou `smallIncrements()` pour coller aux types du modèle, et des clés étrangères du même type.
- **Montants** : `DECIMAL(8,2)` en base, conversion en centimes uniquement à l'appel Stripe.
- **Sécurité** : CSRF, validation serveur de chaque champ, fichiers servis via un contrôleur qui vérifie les droits.
- **RGPD** : consentements horodatés avec IP et version du texte, suppression logique des documents, purge planifiable.
- **Environnement de test** : Stripe en mode test et une course fictive privée pour la recette.

## Direction artistique et principes UX

Deux registres : un tunnel d'inscription sobre et frais façon connexion Google, et des pages « concrètes » (page événement, espaces connectés) plus professionnelles. Les maquettes de référence sont exportées en PDF dans `docs/maquettes.pdf` (à consulter avant tout écran) ; source dans le fichier Figma [Run For Lorène · Wireframes parcours participant](https://www.figma.com/design/CBD2k1JIaY0x0qcps3Sx2D).

| Token | Valeur | Usage |
| --- | --- | --- |
| Vert canard | #00695F | Couleur principale : boutons, liens, progression, bandeau héro |
| Vert profond | #0B4F48 | Pied de page, survols |
| Moutarde | #D6AD2B | Accent : CTA de la page événement, surtitres, badges |
| Aqua | #5DBBAA | Bande décorative, jauges |
| Aqua clair | #E3F3EF | Fonds d'état actif, badges « OK » |
| Crème | #F5F3EC | Fond des pages et sections alternées |
| Encre | #16403B | Texte principal |
| Gris texte | #5B6B67 | Texte secondaire |
| Bordure | #E1DED3 | Traits, bordures de champs et tableaux |

- **Typographie** : Poppins (titres, logo « Run » en gras + « for Lorène » en italique moutarde), Inter (texte et formulaires).
- **Tunnel** : un seul panneau blanc centré (460 px), barre de progression fine en haut. « Continuer » fait glisser le panneau suivant vers la gauche, « Retour » le ramène, sans rechargement. Champs de 52 px, grands rayons, tutoiement.
- **Pages concrètes** : rayons de 8 px maximum, pas de grosses cartes arrondies. La page événement est une vitrine avec en-tête, bandeau héro, tableau des épreuves et pied de page légal. Les espaces connectés utilisent un menu latéral, des indicateurs et des tableaux sobres.
- **Formats de saisie cadrés** : sélecteur de date pour la naissance, validation de l'email, téléphone au format français par défaut.
- **Accessibilité** : contrastes AA, focus visible, libellés sur tous les champs, navigation clavier dans le tunnel.

## Modèle de données

Le modèle est normalisé en 3FN, avec une seule dénormalisation assumée (`id_evenement` sur l'inscription). Le MCD ci-dessous est la référence : il passe la vérification de disposition de Mocodo sans chevauchement.

### MCD (source Mocodo)

```
QUESTION_SANTE: id_question, libelle_question, ordre, version_questionnaire, active
TEXTE_LEGAL: id_texte, type_texte, version, date_publication, fichier_texte
PUBLIER, 0N EVENEMENT, 11 TEXTE_LEGAL
EVENEMENT: id_evenement, nom_evenement, date_evenement, ouverture, fermeture, statut_evenement
OFFRIR, 0N EVENEMENT, 11 OPTION_EVENEMENT
OPTION_EVENEMENT: id_option, libelle_option, prix_option
:

REPONDRE, 0N QUESTIONNAIRE_SANTE, 0N QUESTION_SANTE: reponse
ACCEPTER, 0N TEXTE_LEGAL, 11 CONSENTEMENT
:
PROPOSER, 1N EVENEMENT, 11 COURSE
:
DECLINER, 1N OPTION_EVENEMENT, 11 DECLINAISON
:

QUESTIONNAIRE_SANTE: attestation
CONSENTEMENT: accepte, ip
:
COURSE: id_course, nom_course, distance, heure_depart, age_min_course, age_max_course, quota, questionnaire_requis, dossard_debut, dossard_fin
DEFINIR, 1N COURSE, 11 TARIF
TARIF: id_tarif, libelle_tarif, montant_tarif, valide_du, valide_au, age_min_tarif, age_max_tarif
DECLINAISON: id_declinaison, libelle_declinaison, stock

:
/XT\ DOCUMENT -> QUESTIONNAIRE_SANTE, CONSENTEMENT
:
:
:
APPLIQUER, 0N TARIF, 11 INSCRIPTION
CHOISIR, 0N INSCRIPTION, 0N DECLINAISON: quantite, prix_unitaire

:
DOCUMENT: id_document, date_depot, statut_document, motif_refus, date_validation
:
JOINDRE, 0N INSCRIPTION, 01 DOCUMENT
:
INSCRIPTION: id_inscription, prix_paye, statut_inscription, dossard, urgence_nom, urgence_tel, date_retrait
:

VALIDER, 0N ADMIN, 01 DOCUMENT
DEPOSER, 0N USER, 11 DOCUMENT
:
:
CONCERNER, 0N PARTICIPANT, 11 INSCRIPTION
CONTENIR, 0N COMMANDE, 11 INSCRIPTION
:

:
USER: id_user, email, mot_de_passe, nom, prenom, telephone
:
PASSER, 0N USER, 11 COMMANDE
:
COMMANDE: id_commande, date_commande, montant_total, statut_commande
INCLURE, 0N COMMANDE, 11 DON

:
/\ USER -> PARTICIPANT, ADMIN, BENEVOLE
RESPONSABLE, 0N USER, 01 PARTICIPANT
:
:
REGLER, 0N COMMANDE, 11 PAIEMENT
DON: id_don, montant_don, recu_fiscal_demande, anonyme

ADMIN: niveau_acces
BENEVOLE: poste, disponibilites
PARTICIPANT: sexe, date_naissance, adresse, code_postal, ville, pays
:
:
PAIEMENT: id_paiement, prestataire, reference, montant_paiement, frais, statut_paiement, date_paiement
REMBOURSER, 0N PAIEMENT, 11 REMBOURSEMENT

:
:
:
:
:
:
REMBOURSEMENT: id_remboursement, montant_rembourse, motif, date_remboursement
```

### MLD

Héritages traduits en une table par entité, la table fille partageant la clé de la mère. Côté Laravel, les tables prennent le pluriel en snake\_case (convention Eloquent).

- `user (#id_user, email, mot_de_passe, nom, prenom, telephone)`
- `participant (#id_user*, sexe, date_naissance, adresse, code_postal, ville, pays, id_responsable*)`
- `admin (#id_user*, niveau_acces)`
- `benevole (#id_user*, poste, disponibilites)`
- `evenement (#id_evenement, nom, date_evenement, ouverture, fermeture, statut)`
- `course (#id_course, nom, distance, heure_depart, age_min, age_max, quota, questionnaire_requis, dossard_debut, dossard_fin, id_evenement*)`
- `tarif (#id_tarif, libelle, montant, valide_du, valide_au, age_min, age_max, id_course*)`
- `option_evenement (#id_option, libelle, prix, id_evenement*)`
- `declinaison (#id_declinaison, libelle, stock, id_option*)`
- `commande (#id_commande, date_commande, montant_total, statut, id_user*)`
- `inscription (#id_inscription, prix_paye, statut, dossard, urgence_nom, urgence_tel, date_retrait, id_participant*, id_tarif*, id_commande*, id_evenement*)`
- `inscription_declinaison (#id_inscription*, #id_declinaison*, quantite, prix_unitaire)`
- `don (#id_don, montant, recu_fiscal_demande, anonyme, id_commande*)`
- `paiement (#id_paiement, prestataire, reference, montant, frais, statut, date_paiement, id_commande*)`
- `remboursement (#id_remboursement, montant, motif, date_remboursement, id_paiement*)`
- `texte_legal (#id_texte, type_texte, version, date_publication, fichier, id_evenement*)`
- `document (#id_document, date_depot, statut, motif_refus, date_validation, id_deposant*, id_validateur*, id_inscription*)`
- `questionnaire_sante (#id_document*, type_questionnaire, attestation)` : `type_questionnaire` vaut `sante` ou `decharge`
- `consentement (#id_document*, accepte, ip, id_texte*)`
- `question_sante (#id_question, type_questionnaire, libelle, rubrique, ordre, version_questionnaire, active)` : `rubrique` (santé uniquement) parmi `annee`, `semaines`, `aujourdhui`, `parents`
- `reponse_sante (#id_document*, #id_question*, reponse)`

### Contraintes et types clés

| Élément | Règle |
| --- | --- |
| `user.email` | `VARCHAR(254) NULL UNIQUE` : nul pour un participant géré sans compte, qui doit alors avoir un `id_responsable` |
| `user.mot_de_passe` | `VARCHAR(255) NULL` (hash Laravel) |
| `participant.id_responsable` | FK vers `user`, nullable, `ON DELETE SET NULL` |
| `inscription.id_evenement` | Dénormalisation assumée (l'événement se déduit du tarif) : sert aux contraintes `UNIQUE(id_evenement, id_participant)` et `UNIQUE(id_evenement, dossard)`. L'application vérifie que le tarif appartient à cet événement |
| `inscription.dossard` | `SMALLINT UNSIGNED NULL`, rempli à la validation, dans la plage de la course |
| `inscription.prix_paye`, `inscription_declinaison.prix_unitaire` | Prix historisés au moment de l'achat (pas une redondance) |
| `commande.montant_total` | Calculé une seule fois à la création, figé ensuite (montant envoyé à Stripe) |
| Montants | `DECIMAL(8,2)`, `CHECK >= 0` |
| Identifiants | `INT UNSIGNED` (user, inscription, commande, paiement, document), `SMALLINT UNSIGNED` (événement, course, tarif, option, déclinaison, texte légal) |
| `consentement.ip` | `VARCHAR(45)` (IPv6) |
| `paiement.reference` | `VARCHAR(100) UNIQUE` (identifiant Stripe) |
| `texte_legal` | `UNIQUE(id_evenement, type_texte, version)`, `type_texte` parmi règlement, décharge, autorisation parentale, RGPD, droit à l'image |
| Suppressions | Documents, consentements et réponses de santé conservés jusqu'à l'édition suivante (12 mois après l'événement), puis purgés par une tâche planifiée ; `reponse_sante.reponse` chiffrée (cast `encrypted`) |

## Règles métier et statuts

Une inscription n'est validée qu'avec un paiement réussi (ou une gratuité admin), les consentements obligatoires, la décharge et le questionnaire de santé. La décharge et le questionnaire peuvent être remplis après le paiement, mais sans eux le participant ne prend pas le départ.

&#91;embedded content: cycle de vie d'une inscription · 5 états\]

Un paiement échoué ou abandonné laisse l'inscription « en attente de paiement » : le participant peut relancer le paiement depuis son espace. Le dossard n'est attribué qu'au passage en « validée ».

Transitions (version texte du schéma) :

- Brouillon → En attente de paiement : récapitulatif confirmé.
- En attente de paiement → Validée : paiement réussi, consentements, décharge et questionnaire complets.
- En attente de paiement → Dossier incomplet : paiement réussi mais décharge ou questionnaire de santé manquant.
- Dossier incomplet → Validée : décharge et questionnaire complétés depuis l'espace (et vérifiés si besoin).
- Brouillon → Validée ou Dossier incomplet : inscription gratuite créée par un admin, sans paiement.
- En attente de paiement → Annulée : abandon définitif ou annulation par un admin.
- Le dossard est attribué à l'entrée dans l'état Validée.

### Comptes et participants

- **Un compte (USER avec email)** peut inscrire lui-même et d'autres personnes : enfants, petits-enfants. Ces participants gérés n'ont ni email ni mot de passe et sont rattachés via `id_responsable`.
- **Mineur** : l'âge se calcule à la date de l'événement, pas à la date d'inscription.
- **Unicité** : une seule inscription par participant et par événement, garantie en base par `UNIQUE(id_evenement, id_participant)`.

### Documents exigés

| Document | Qui | Quand il est exigé |
| --- | --- | --- |
| Acceptation du règlement (consentement) | Tous | Toujours, avant paiement |
| Décharge de responsabilité (questionnaire oui/non + consentement au texte PDF) | Tous | Toujours ; dans le tunnel ou plus tard depuis l'espace, obligatoire pour prendre le départ |
| RGPD (consentement) | Tous | Toujours, avant paiement |
| Droit à l'image (consentement) | Tous | Facultatif, oui ou non enregistré |
| Questionnaire de santé (24 questions oui/non du Google Form, en 4 rubriques sur 3 diapos, avertissement « consulte un médecin si un OUI » + attestation finale) | Tous | Si `questionnaire_requis` sur la course ; peut être complété après paiement |

Pas d'autorisation parentale pour les mineurs : décision de l'association.

- Chaque consentement enregistre la version du texte accepté, la date et l'IP.
- Le questionnaire est saisi en ligne dans le tunnel, plus de formulaire externe. Les questions sont en base, versionnées ; chaque réponse est liée à la version posée.
- Un admin habilité peut consulter les questionnaires et les marquer « vérifié » ; une décharge ou un questionnaire manquant laisse l'inscription en « dossier incomplet », sans dossard.
- **Conservation** : documents, consentements et réponses de santé sont gardés jusqu'à l'édition suivante (12 mois après l'événement), puis purgés automatiquement.

### Tarifs, options et dossards

- **Tarif applicable** : celui de la course dont la période `valide_du` à `valide_au` contient la date du jour, et dont la tranche d'âge (si renseignée) correspond au participant.
- **Capacité** : `quota` compte les inscriptions validées et en attente de paiement de moins de 30 minutes (proposition : place réservée 30 minutes pendant le paiement) ; vide = illimité.
- **Options** : le t-shirt est une option avec une déclinaison par taille et un stock par taille ; stock épuisé = taille grisée.
- **Dossards** : attribués automatiquement au passage en « validée », premier numéro libre dans la plage `dossard_debut` à `dossard_fin` de la course. Plage pleine = alerte admin. Un admin peut modifier un dossard à la main, l'unicité par événement reste vérifiée.

### Dons et commandes

- Une commande regroupe une ou plusieurs inscriptions et au plus un don ; le donateur est celui qui passe la commande.
- Un don peut aussi être fait seul, sans inscription (commande contenant uniquement le don).
- `anonyme` masque le nom dans les éventuels affichages publics ; `recu_fiscal_demande` déclenche la génération du reçu fiscal (association éligible).

## Parcours participant

Le parcours tient en 11 écrans, maquettés dans Figma : une page publique, un tunnel de 7 étapes en panneaux, puis l'espace participant. Les numéros renvoient aux cadres Figma.

| N° | Écran | Contenu | Règles |
| --- | --- | --- | --- |
| 01 | Page de l'événement | En-tête avec navigation, héro (RFL3, 3 avril 2027, CTA « S'inscrire » et « Faire un don »), chiffres clés, tableau des épreuves (départ, public, tarif, places restantes), section cause, pied de page légal (RNA, mentions, CGV) | Épreuves, tarifs et places viennent de l'admin ; une épreuve complète reste visible, bouton grisé ; règlement PDF téléchargeable |
| 02 | Choix de l'épreuve (étape 1/7) | Cartes d'épreuves sélectionnables avec tarif et badge de places | Filtrage par âge une fois le participant connu |
| 03 | Identification (étape 2/7) | Email seul, puis mot de passe ou lien magique | Comme Google : l'email détermine connexion ou création |
| 04 | Création du compte (étape 2/7) | Pastille de l'email saisi, prénom, nom, mot de passe | Vérification de l'email par lien |
| 05 | Participant (étape 3/7) | « Pour qui ? » : moi-même, un participant déjà enregistré, ou un nouveau participant (enfant). Identité, date de naissance (calendrier), sexe, téléphone, adresse, contact d'urgence | Âge à la date de l'événement vérifié par rapport aux âges min et max de la course |
| 06 | Documents et consentements (étape 4/7) | Cases règlement (lien PDF), RGPD, droit à l'image ; choix « maintenant » ou « plus tard » pour la décharge (questions oui/non + case, lien PDF) et le questionnaire de santé (oui/non + attestation) | Règlement, RGPD et le choix bloquent la suite ; « plus tard » affiche qu'il n'y a pas de départ sans ces documents |
| 07 | Options et don (étape 5/7) | Taille de t-shirt (tailles épuisées grisées), montants de don suggérés et montant libre | Tout est facultatif, bouton « Passer » |
| 08 | Récapitulatif (étape 6/7) | Blocs épreuve, participant, documents, options, don, avec lien « Modifier » ; total | « Modifier » refait glisser le bon panneau ; possibilité d'ajouter un autre participant à la même commande |
| 09 | Paiement (étape 7/7) | Redirection vers Stripe Checkout (carte, Apple Pay) | Place réservée pendant le paiement |
| 09b | Paiement non abouti | Message rassurant, boutons « Réessayer » et « Payer plus tard » | L'inscription reste « en attente de paiement » |
| 10 | Confirmation | Récapitulatif, rappel des documents manquants avec date limite, bouton vers l'espace | Email de confirmation envoyé en parallèle |
| 11 | Espace participant | Menu latéral (tableau de bord, inscriptions, documents, dons, profil, participants gérés) ; alerte « action requise », 4 indicateurs (épreuve, statut, paiement, J-x), suivi en étapes, billet QR, tableaux documents et dons | Billet téléchargeable seulement si « validée » ; t-shirt modifiable jusqu'à une date limite |

- **Panneaux** : chaque étape est un panneau ; les données saisies sont gardées en brouillon côté serveur à chaque « Continuer », pour reprendre plus tard.
- **Retour arrière** : « Retour » ne perd jamais une saisie.
- **Mobile d'abord** : le panneau occupe toute la largeur sur téléphone ; les boutons restent visibles.

## Back-office admin

L'admin reprend ce qui marchait dans TimePulse (tableau de bord, liste des inscrits) en le collant aux usages de l'asso. Il n'est pas maquetté : même DA et même structure que l'espace participant (menu latéral, indicateurs, tableaux sobres, rayons de 8 px).

| Écran | Contenu | Actions |
| --- | --- | --- |
| Tableau de bord | Nombre d'inscrits (validés, incomplets, en attente), recettes (inscriptions, options, dons), t-shirts vendus par taille, répartition des inscrits par course, jauge de remplissage par course | Filtre par événement |
| Inscriptions | Tableau : participant, course, statut, dossard, paiement, documents, date ; recherche et filtres (course, statut, mineur, documents manquants) | Fiche détaillée, modifier le dossard, annuler, relancer par email, export CSV |
| Inscription manuelle | Formulaire admin pour créer une inscription gratuite (cas particuliers) | Crée une commande à 0 € sans paiement, marquée `gratuite` |
| Questionnaires et consentements | Questionnaires de santé (réponses déchiffrées à l'affichage, accès réservé aux niveaux habilités), consentements, historique | Marquer vérifié, contacter le participant ; option : éditer les questions (crée une nouvelle version) |
| Commandes et paiements | Commandes, tentatives de paiement, frais Stripe, remboursements | Rembourser totalement ou en partie (via l'API Stripe), lien vers le paiement Stripe |
| Dons | Liste des dons, anonymes ou non, reçus demandés | Générer ou renvoyer un reçu fiscal, export CSV |
| Événements | Événement (dates, ouverture et fermeture des inscriptions, statut) et ses sous-onglets : courses (quota, âges, plage de dossards, justificatif requis), tarifs (périodes, âges), options et tailles (stock), textes légaux versionnés (PDF) | Créer, dupliquer un événement d'une année sur l'autre, publier une nouvelle version d'un texte |
| Utilisateurs | Comptes, participants gérés, admins et niveaux d'accès | Promouvoir un compte en admin, réinitialiser un accès |

- **Niveaux d'accès** (`niveau_acces`) : 1 = lecture seule, 2 = gestion des inscriptions et documents, 3 = super admin (paramétrage, remboursements, admins).
- **Traçabilité** : toute action admin sensible (validation, refus, remboursement, dossard modifié, inscription gratuite) est horodatée avec son auteur.
- **Exports** : CSV compatibles tableur (séparateur point-virgule, UTF-8 avec BOM) pour les inscrits, la liste des dossards et les dons.

## Paiement, emails et documents

Stripe Checkout encaisse, les webhooks font foi, Brevo envoie les emails et les fichiers restent privés sur le serveur.

### Paiement Stripe

1. Au récapitulatif, l'application crée la commande (montant figé) et une session Stripe Checkout en mode paiement, avec une ligne par inscription, option et don.
2. Apple Pay et Google Pay sont activés dans le tableau de bord Stripe ; aucun code spécifique, Checkout les affiche automatiquement.
3. Le retour sur la page de succès n'est qu'un affichage : seul le webhook `checkout.session.completed` passe le paiement en « réussi ».
4. Le webhook vérifie la signature, est idempotent (un même événement reçu deux fois ne change rien) et enregistre `reference`, `montant` et `frais`.
5. `checkout.session.expired` et les échecs laissent l'inscription « en attente de paiement » et libèrent la place réservée.
6. Les remboursements passent par l'API Stripe depuis l'admin ; le webhook `charge.refunded` crée la ligne REMBOURSEMENT.

### Emails (Brevo)

| Déclencheur | Destinataire | Contenu |
| --- | --- | --- |
| Création de compte | Titulaire | Lien de vérification de l'email |
| Connexion par lien | Titulaire | Lien magique, valable 15 minutes |
| Paiement réussi | Titulaire | Récapitulatif de la commande, documents manquants et date limite |
| Inscription validée | Titulaire | Numéro de dossard, billet QR, infos pratiques |
| Document refusé | Titulaire | Motif et lien pour redéposer |
| Relance documents | Titulaire | Rappel automatique 15 jours avant l'événement si la décharge ou le questionnaire de santé manque |
| Paiement en attente | Titulaire | Rappel 24 h après l'abandon, lien pour payer |
| Reçu de don | Titulaire | Reçu fiscal en PDF, si demandé |

- Emails en file d'attente (queue Laravel) pour ne pas ralentir le parcours.
- Gabarits aux couleurs de la DA, en texte simple lisible sur mobile.

### Documents et fichiers

- Stockage sur un disque privé ; téléchargement uniquement via un contrôleur qui vérifie que l'utilisateur est le déposant, le responsable du participant ou un admin.
- Aucun fichier déposé par les participants en V1 : le questionnaire est saisi en ligne, ses réponses sont chiffrées en base et un PDF peut être généré à la volée pour un admin habilité.
- Billet et reçu fiscal générés en PDF à la volée, jamais stockés.
- Les textes légaux (règlement, décharge…) sont des PDF publiés par l'admin, versionnés : une nouvelle version ne modifie jamais les consentements déjà donnés.

## Découpage en lots

Sept lots, dans cet ordre : chacun s'appuie sur le précédent et se termine par un critère vérifiable avant de passer au suivant.

| Lot | Contenu | Terminé quand |
| --- | --- | --- |
| 1. Socle | Projet Laravel, migrations complètes du modèle, seeders (un événement de test, deux courses, tarifs, textes légaux), authentification (email + mot de passe, lien magique, vérification), rôles et niveaux d'accès, layout et tokens de la DA, configuration Brevo et files d'attente | Les migrations passent, un admin et un participant se connectent, un email de test part |
| 2. Paramétrage admin | Écran Événements et ses sous-onglets : courses et plages de dossards, tarifs, options et tailles, textes légaux versionnés ; duplication d'un événement | Un admin crée de zéro une course fictive complète |
| 3. Tunnel d'inscription | Page de l'événement (01), panneaux 02 à 08, participants gérés, brouillon enregistré à chaque étape, règles d'âge, questionnaire de santé, consentements | Un parent inscrit un enfant et lui-même jusqu'au récapitulatif |
| 4. Paiement | Stripe Checkout (mode test), webhooks idempotents, statuts d'inscription, réservation de place, attribution des dossards, emails de paiement et de validation, écrans 09, 09b, 10 | Paiement test réussi, échoué et abandonné donnent chacun le bon statut ; dossards uniques |
| 5. Espace participant | Dashboard (11), inscriptions, documents (dépôt ultérieur), dons, profil, participants gérés, billet QR et reçu de don en PDF | Un participant complète son dossier après coup et télécharge son billet |
| 6. Suivi admin | Tableau de bord, liste et fiche des inscrits, validation des documents, inscription gratuite manuelle, remboursements, dons, exports CSV, traçabilité | Les chiffres du tableau de bord correspondent aux données de test |
| 7. Recette | Course fictive privée de bout en bout, tests automatisés des règles métier (statuts, dossards, unicité, âges, webhooks), revue accessibilité et mobile, checklist de mise en production OVH | Parcours complet validé par l'asso sur la course fictive |

Consignes pour Claude Code :

- Écrire les tests des règles métier en même temps que le code (Pest ou PHPUnit), en priorité sur les statuts, les dossards et les webhooks.
- Ne jamais mettre de clé Stripe ou Brevo dans le code : fichier `.env`, et `.env.example` documenté.
- Signaler toute règle absente de ce document plutôt que de l'inventer.

## Points ouverts

Ces sujets sont volontairement reportés ; aucun ne bloque la V1.

**Fonctionnalités V2**

- [ ] Invitations et liens personnalisés pour partenaires, écoles et groupes, avec suivi dédié. Trois cas : partenaires gratuits, tiers payeur global, élèves payant individuellement. Prévoir une table « invitation » reliée à l'inscription.
- [ ] Éditeur des questions de santé dans l'admin, si non fait en V1 (option).
- [ ] Types de documents exigés paramétrables par course, à la place du booléen `questionnaire_requis`.
- [ ] Gestion des bénévoles : poste par événement, disponibilités dans une table dédiée.

**À vérifier avec l'association**

- [ ] Hébergement : offre OVH exacte (mutualisé, VPS ou cloud) et compatibilité avec les files d'attente et tâches planifiées de Laravel.
- [x] Questions du Google Form de santé reprises (version 1). Même questionnaire pour tous ; si le coureur est mineur le jour de l'événement, la rubrique famille est titrée « à faire remplir par tes parents ». Sexe et âge non redemandés (profil). La personne qui inscrit répond.
- [ ] Texte définitif de la décharge (questions provisoires en base, version 1 ; toutes les réponses sont oui/non).
- [x] Usage des réponses de santé : stockées (chiffrées) par coureur, une réponse « oui » ne bloque pas l'inscription. Qui les consulte : admins habilités (lot admin).
- [ ] Limites réelles de l'offre Brevo par rapport au volume d'emails attendu.
- [ ] Audit TimePulse : champs et fonctionnalités réellement utilisés.
- [ ] Contenu RFL3 : lieu, épreuves, horaires, âges, tarifs, quotas, plages de dossards, options et tailles, textes légaux en PDF, texte « La cause » et photos.

Décisions déjà prises avec l'association : événement non compétitif ; association éligible aux reçus fiscaux ; décharge de responsabilité exigée ; pas d'autorisation parentale pour les mineurs ; questionnaire de santé repris du Google Form et intégré au site ; conservation jusqu'à l'édition suivante (12 mois) ; relance 15 jours avant l'événement.

**Maquettes**

- [ ] Mettre à jour les écrans Figma 06, 08, 10 (questionnaire de santé intégré) et 09 (Stripe, Apple Pay).
- [ ] Maquetter le back-office admin si besoin avant le lot 6.
