# Run For Lorène · Cadrage juridique de la plateforme d'inscription

Document de préparation de la réunion avec le juriste. Il décrit ce que fait la plateforme
(données collectées, règles, textes affichés) et liste les questions à trancher.
Il ne contient aucun avis juridique : les références citées sont des pistes à faire confirmer.

**Repères** : beta interne mi-décembre 2026 · ouverture des inscriptions le 15 janvier 2027 ·
course le samedi 3 avril 2027 à Nantes.

---

## 1. Les 12 questions prioritaires

Si la réunion est courte, commencer par celles-ci. Les questions 1 à 6 bloquent l'ouverture
des inscriptions : la réponse change le code ou des textes affichés à chaque inscription.

| N° | Question | Section |
| --- | --- | --- |
| 1 | La décharge actuelle (« dégager de toutes responsabilités… sans aucune exception ni réserve ») a-t-elle une valeur ? Que faut-il écrire à la place ? | 4 |
| 2 | La décharge fait cocher « je ne souffre d'aucune maladie ou handicap » pour pouvoir s'inscrire, sur un événement consacré à la santé mentale. Est-ce un risque de discrimination ? | 4 |
| 3 | Questionnaire de santé des mineurs : a-t-on le droit, et besoin, de **conserver les réponses** (chiffrées), ou faut-il ne garder que l'attestation ? | 3 |
| 4 | Mineurs inscrits sans autorisation parentale (décision de l'association) : est-ce tenable ? Qui peut inscrire un enfant, et que doit-il attester ? | 5 |
| 5 | Case « RGPD » obligatoire présentée comme un consentement : quelle base légale pour chaque traitement ? | 6 |
| 6 | CGV : droit de rétractation, annulation par le participant, annulation ou report de l'événement, remboursements. | 7 |
| 7 | Une analyse d'impact (AIPD) est-elle obligatoire (données de santé de mineurs) ? | 6 |
| 8 | Reçu fiscal : l'association peut-elle en délivrer, et pour quoi exactement (don seul, don ajouté à une inscription) ? | 8 |
| 9 | Durées de conservation : comptes, commandes, journal admin, données de santé. | 6 |
| 10 | Droit à l'image : un « oui » unique suffit-il ? Qui consent pour un mineur ? | 9 |
| 11 | Emails : relances d'inscrits et annonce de l'édition suivante, avec ou sans accord préalable ? | 10 |
| 12 | Brief complémentaire (groupes, bénévoles, ateliers) : ce qu'il faut prévoir avant de le développer. | 11 |

---

## 2. Ce que fait la plateforme

### Contexte

- **Organisateur** : association Effervescence Jeunes (Nantes), thème de la santé mentale des jeunes.
- **Événement** : course solidaire **non compétitive**, non chronométrée, sans licence demandée.
- **Volume** : 23 000 € collectés lors de la dernière édition (via TimePulse, que la plateforme remplace).
- **Paiement** : Stripe Checkout (carte, Apple Pay, Google Pay). Inscriptions payantes, options
  (t-shirts, désactivées pour l'instant), dons libres de 0 à 10 000 €.
- **Prestataires** : OVH (hébergement, France), Stripe (paiement), Brevo (emails, France).
  Brief complémentaire : Google Forms pour les candidatures bénévoles.

### Parcours d'inscription

1. Une personne crée un compte (email, prénom, nom, mot de passe ; email vérifié).
2. Elle inscrit elle-même et/ou d'autres personnes : enfants, proches, y compris des adultes.
   Ces « participants gérés » n'ont pas de compte ; ils sont rattachés au titulaire.
3. Avant de payer, pour chaque participant, elle remplit obligatoirement :
   - la **décharge de responsabilité** (6 affirmations à cocher, reprises du Google Form actuel, voir section 4) ;
   - le **questionnaire de santé** si le participant a **moins de 18 ans le jour de la course**
     (24 questions oui/non + attestation, voir section 3) ;
   - l'acceptation du **règlement** (PDF) et une case **« RGPD »**, toutes deux obligatoires ;
   - le **droit à l'image**, facultatif (oui ou non enregistré).
4. Elle paie sur Stripe. L'inscription n'est validée qu'au paiement confirmé par Stripe ; un dossard
   est alors attribué et un billet PDF avec QR code est envoyé par email.
5. Le jour J, des bénévoles remettent les dossards en scannant le billet (postes sans compte,
   ouverts par un lien secret). Ils voient le nom, la course, le dossard et la mention « mineur ».

Pas d'autorisation parentale : décision de l'association, inscrite au spec.

### Données collectées

| Donnée | Qui | Obligatoire | Remarque |
| --- | --- | --- | --- |
| Email, prénom, nom, mot de passe (haché) | Titulaire du compte | Oui | |
| Prénom, nom, date de naissance | Chaque participant | Oui | Sert aux tranches d'âge et à la règle « mineur » |
| Téléphone, email de contact | Participant | Non | |
| Sexe (Femme, Homme, Autre), adresse | Participant | Non | Complétés après coup dans l'espace. Usage réel à justifier |
| Contact d'urgence (nom, téléphone) | Participant | Non | Donnée d'un tiers |
| Décharge : 6 affirmations, nom, date | Tous les participants | Oui | Contient des déclarations de santé (« aucune maladie ou handicap ») |
| Questionnaire de santé : 24 réponses + attestation | Mineurs | Oui | Réponses **chiffrées** en base, consultables par les admins habilités |
| Consentements : texte accepté, version, date, **adresse IP** | Tous | — | Preuve de l'acceptation |
| Commande, paiement (référence Stripe, montant, frais), remboursements | Titulaire | — | Aucune donnée de carte : elles restent chez Stripe |
| Don : montant, reçu fiscal demandé, anonyme | Donateur | — | |
| Notes internes (texte libre des admins sur une inscription) | — | — | Risque d'y écrire des informations de santé |
| Journal des actions admin (qui, quoi, quand) | Admins | — | Jamais modifié ni supprimé |

### Accès

- **Participant** : ses inscriptions et celles des personnes qu'il gère.
- **Back-office**, trois niveaux : 1 lecture seule (peut exporter les inscrits, mineurs et contacts
  d'urgence compris, export tracé), 2 gestion des inscriptions et documents, 3 super admin
  (paramétrage, remboursements). Connexion par mot de passe puis code envoyé par email.
- **Postes de retrait** (bénévoles sans compte) : nom, course, dossard, mineur ou non. Ni contact, ni santé.

### Conservation actuelle

| Données | Durée prévue | Mécanisme |
| --- | --- | --- |
| Décharges, questionnaires et réponses de santé, consentements | 12 mois après l'événement | Purge automatique chaque nuit |
| Inscriptions, commandes, paiements, remboursements | Sans limite | Gardés pour la comptabilité |
| Comptes et participants gérés | Sans limite | Suppression manuelle par un super admin, seulement si le compte n'a jamais servi |
| Journal admin | Sans limite | |
| Journaux techniques du serveur | 14 jours | |
| Cookie « Rester connecté » | 400 jours si coché | Seuls cookies nécessaires, pas de bandeau |

### Textes déjà prévus mais non rédigés

Règlement (PDF), décharge (PDF), politique de protection des données (PDF), droit à l'image (PDF),
CGV et mentions légales (pages du site). La page cookies existe et reste à relire. Le site
affiche « pas encore en ligne » tant qu'un texte manque, et l'inscription est bloquée tant
qu'un texte obligatoire n'est pas publié.

---

## 3. Questionnaire de santé des mineurs

**Constat.** Les 24 questions sont celles du questionnaire officiel pour les mineurs (arrêté du
7 mai 2021), réparties en 4 rubriques dont une « à faire remplir par tes parents ». Une réponse
« oui » ne bloque pas l'inscription : elle affiche « consulte un médecin ». Les réponses sont
stockées chiffrées, par coureur, et lisibles par les admins habilités. Elles sont purgées 12 mois
après l'événement. La personne qui inscrit répond (en pratique, le parent).

Exemples de questions : « Te sens-tu triste ou inquiet(e) ? », « Pleures-tu plus souvent ? »,
« Quelqu'un dans votre famille proche est-il décédé subitement avant 50 ans ? ».

**Questions.**

1. L'événement étant non compétitif et sans licence, le questionnaire est-il **obligatoire** ? Sinon,
   sur quelle base l'exige-t-on (règlement de l'association, assurance) ?
2. Dans le dispositif officiel, les réponses restent dans la famille et seule l'**attestation** est
   remise. A-t-on un motif de **conserver les réponses** ? Si non, on ne garde que l'attestation
   (« j'ai répondu non à toutes les questions » ou « j'ai consulté un médecin »). C'est un petit
   changement de code et cela supprime l'essentiel du risque.
3. Si on garde les réponses : base légale au titre des données sensibles (art. 9 RGPD), qui peut
   les lire, que fait l'association d'un « oui » (contacter la famille ? refuser le départ ?), et
   une réponse « oui » sans action engage-t-elle la responsabilité de l'association ?
4. Un adulte ne remplit pas de questionnaire, mais coche dans la décharge qu'il est en bonne santé.
   Faut-il un certificat médical, une simple déclaration, ou rien pour un adulte ?
5. Faut-il une case d'information spécifique avant le questionnaire (finalité, destinataires, durée) ?

---

## 4. Décharge de responsabilité

**Constat.** Reprise telle quelle du Google Form « Décharge Responsabilité Adulte ». Exigée de
**tous**, mineurs compris, avant de payer. Les 6 affirmations, toutes obligatoires :

1. « Je suis en bonne condition physique, je ne souffre d'aucune blessure, maladie ou handicap, et je
   n'ai jamais eu de problèmes cardiaques ou respiratoires décelés à ce jour. »
2. « Aucun médecin, infirmier ou entraîneur ne m'a déconseillé la pratique de ce sport. »
3. « J'ai une assurance en responsabilité civile. »
4. « Je déclare dégager de toutes responsabilités en cas d'accident de quelque nature que ce soit ou
   de dommages, sans aucune exception ni réserve, l'organisation de la "RUN FOR LORÈNE" ainsi que
   toutes les personnes œuvrant pour l'organisation. »
5. « J'ai lu le présent document et je comprends qu'en complétant ce formulaire je renonce à des
   droits importants. C'est donc en toute connaissance de cause que j'envoie mes réponses. »
6. « J'envoie le présent formulaire volontairement et sans contrainte. »

**Questions.**

1. L'affirmation 4 a-t-elle une valeur ? L'organisateur d'une manifestation sportive a une
   obligation de sécurité, et une clause qui exclut toute responsabilité envers un participant
   (consommateur), surtout pour un dommage corporel, risque d'être réputée non écrite. Que faut-il
   écrire à la place : acceptation des risques, rappel des consignes, engagement du participant ?
2. L'affirmation 1 oblige à déclarer « aucune maladie ou handicap » pour s'inscrire. Une personne
   handicapée, ou suivie pour un trouble psychique, ne peut pas cocher sans mentir. Risque de
   discrimination liée à l'état de santé ou au handicap ? Sur un événement consacré à la santé
   mentale, le risque d'image s'ajoute. Faut-il la remplacer par une déclaration d'aptitude
   (« je m'estime apte à parcourir la distance choisie à mon rythme ») ?
3. Ces affirmations sont-elles des données de santé ? Le site les enregistre (case cochée, date, IP).
4. Une décharge signée **pour un mineur** par la personne qui l'inscrit a-t-elle une valeur ? Faut-il
   une version mineur, signée par le représentant légal (point ouvert du spec) ?
5. L'affirmation 3 suffit-elle ? Assurance de l'organisateur (responsabilité civile obligatoire pour
   une manifestation sportive) et information des participants sur l'intérêt d'une assurance
   individuelle accident : quelles mentions afficher, et où (règlement, tunnel, CGV) ?
6. Une case cochée en ligne avec horodatage, IP et version du texte vaut-elle preuve, ou faut-il
   une signature électronique ?

---

## 5. Mineurs

**Constat.** Est mineur un participant qui a moins de 18 ans **le jour de la course**. N'importe quel
titulaire d'un compte peut créer un « participant géré » (enfant, petit-enfant, proche) et l'inscrire.
Rien ne vérifie le lien avec l'enfant ni l'autorité parentale. Il n'y a pas d'âge minimum pour créer
un compte : un adolescent peut s'inscrire seul et payer.

**Questions.**

1. Sans autorisation parentale, qui est juridiquement engagé quand un grand-parent, un ami ou un
   animateur inscrit un enfant ? Faut-il au minimum une case « je suis le représentant légal ou
   j'ai son accord » ?
2. Un mineur peut-il créer un compte, accepter le règlement et la décharge, et payer seul ? Faut-il
   un âge minimum (15 ans pour consentir seul au traitement de ses données en ligne, en France) ?
3. Mineur venu sans adulte le jour J : à régler dans le règlement ou à la plateforme ?
4. Le droit à l'image d'un mineur : qui l'accorde (voir section 9) ?

---

## 6. Données personnelles (RGPD)

### Bases légales

La case « RGPD » est obligatoire, comme si l'inscription reposait sur un consentement. Or un
consentement doit être libre, donc facultatif.

1. Quelle base légale pour chaque traitement ? Proposition à valider : exécution du contrat
   (inscription, paiement, dossard, emails liés), obligation légale (comptabilité, reçus fiscaux),
   intérêt légitime (sécurité de l'événement, contact d'urgence, journal admin), consentement
   (droit à l'image, communications facultatives), consentement explicite ou autre exception de
   l'article 9 (santé).
2. La case « RGPD » devient-elle une simple information (« j'ai lu la politique ») ou disparaît-elle ?

### Obligations de l'association

3. **AIPD** : données de santé + mineurs, quelques centaines à milliers de personnes. Est-elle
   obligatoire ? Qui la rédige ?
4. **Registre des traitements** : l'association en a-t-elle un ? Faut-il un DPO (a priori non) ?
5. **Contrats de sous-traitance** : OVH, Stripe, Brevo, plus Google pour les bénévoles. Les conditions
   générales de ces services suffisent-elles ? Stripe et Google impliquent-ils des transferts hors UE
   à mentionner ?
6. **Contenu de la politique de protection des données** (PDF à rédiger) : ce qu'elle doit couvrir,
   et qui est responsable de traitement (l'association, son président ?).
7. **Violation de données** : procédure à prévoir (notification CNIL sous 72 h). Qui s'en charge ?

### Minimisation et accès

8. Sexe et adresse postale : sont-ils nécessaires ? L'adresse sert-elle seulement au reçu fiscal ?
   Si oui, la demander seulement à ce moment.
9. Un admin en **lecture seule** peut exporter la liste complète des inscrits (mineurs et contacts
   d'urgence compris). Faut-il réserver l'export à un niveau supérieur ?
10. **Notes internes** en texte libre : faut-il un avertissement « pas d'information de santé » ?
11. Contact d'urgence : la personne désignée doit-elle être informée ?

### Durées de conservation

12. Valider les durées : documents et santé 12 mois après l'événement (prévu) ; comptes inactifs
    (aujourd'hui sans limite : 3 ans après la dernière inscription ?) ; commandes et paiements
    (pièces comptables : 10 ans ?) ; journal admin (aujourd'hui sans limite) ; adresses IP des
    consentements (même durée que le consentement ?).
13. Faut-il anonymiser plutôt que supprimer (garder les statistiques d'âge et de participation) ?

### Droits des personnes

14. Aujourd'hui pas de libre-service : la personne écrit à l'association pour obtenir ses données ou
    supprimer son compte. Est-ce suffisant si c'est indiqué dans la politique, ou faut-il un bouton
    dans « Mon compte » ?
15. Un compte qui a payé ne peut pas être supprimé (historique comptable). Comment répondre à une
    demande d'effacement : anonymiser le compte en gardant la commande ?
16. Un adulte inscrit par un proche n'a pas accès à son inscription. Comment exerce-t-il ses droits ?

---

## 7. Vente d'inscriptions : CGV, rétractation, remboursements

**Constat.** Une commande regroupe une ou plusieurs inscriptions, des options et un don. Le prix
dépend de la date et de l'âge. Les remboursements se font à la main par un super admin, en tout ou
partie, via Stripe. Une inscription réglée puis annulée par l'association ne peut pas être refaite
en ligne. Les CGV n'existent pas encore.

**Questions.**

1. **Droit de rétractation** : l'exception pour les activités de loisirs fournies à une date
   déterminée s'applique-t-elle ? Quelle mention afficher avant le paiement ?
2. **Annulation par le participant** : remboursement oui ou non, jusqu'à quand, avec ou sans frais ?
   Transfert de dossard à une autre personne ?
3. **Annulation, report ou modification de l'événement** (météo, arrêté préfectoral, force majeure) :
   que doit l'association ? Le don inclus dans la commande est-il remboursé aussi ?
4. **Annulation par l'association** (fausse déclaration, comportement) : sur quelle base, avec quel
   remboursement ?
5. **Frais Stripe** : restent-ils à la charge de l'association en cas de remboursement ?
6. **Contenu obligatoire des CGV** et des **mentions légales** : identité de l'association, RNA, siège,
   responsable de publication, hébergeur, médiateur de la consommation (obligatoire pour une
   association qui vend à des particuliers ?).
7. **TVA et fiscalité** : les inscriptions et les t-shirts sont-ils soumis à la TVA, ou couverts par
   l'exonération des organismes sans but lucratif (manifestations de soutien) ? Faut-il une facture
   ou un reçu d'inscription ?
8. Faut-il une case « j'accepte les CGV » distincte du règlement ?

---

## 8. Dons et reçus fiscaux

**Constat.** Don possible seul (sans inscription) ou ajouté à une commande, de 0 à 10 000 €. Le
donateur coche « reçu fiscal » et/ou « anonyme ». Le reçu PDF n'est pas encore développé (bloquant
avant le 15 janvier).

**Questions.**

1. L'association est-elle bien éligible au reçu fiscal (intérêt général, articles 200 et 238 bis du
   CGI) ? A-t-elle un rescrit ? L'objet « santé mentale des jeunes » est-il couvert ?
2. Les fonds collectés sont-ils reversés à une autre structure ? Si oui, qui délivre le reçu ?
3. Un don ajouté à une inscription payante est-il un vrai don (pas de contrepartie) ? Le prix de
   l'inscription ne donne jamais droit à un reçu : faut-il l'écrire ?
4. Mentions obligatoires du reçu (modèle Cerfa n° 11580), numérotation, signataire, date d'émission
   (à chaque don ou reçu annuel récapitulatif), conservation des doubles.
5. Don d'une entreprise (reçu au nom de la société) : à prévoir ?
6. Don « anonyme » avec reçu fiscal : l'anonymat ne vaut que pour l'affichage public ; à préciser
   dans le texte ?
7. Don remboursé après émission du reçu : procédure ?

---

## 9. Droit à l'image

**Constat.** Une case facultative, oui ou non, modifiable dans l'espace jusqu'au jour J et historisée.
Le texte (PDF) reste à rédiger. Le brief complémentaire demande aussi une photo de badge pour les
bénévoles, distincte du droit à l'image.

**Questions.**

1. Contenu du texte : supports (site, réseaux, presse, affiches), durée, territoire, gratuité,
   retrait de l'accord.
2. Pour un mineur : le représentant légal doit-il consentir, et le mineur aussi selon son âge ?
3. Que faire concrètement en cas de refus (photographes, bracelet ou dossard distinct) ?
4. Photos de foule : l'accord individuel est-il nécessaire ?
5. Retrait de l'accord après publication : quelles obligations ?

---

## 10. Emails et communications

**Constat.** Emails liés à la commande (confirmation, billet, dossard), relances de documents
manquants (automatiques, en pause par défaut), et relances ciblées écrites par les admins pour les
inscrits (infos pratiques, retrait des dossards). Pour un mineur, l'email part au titulaire du compte.
Aucune newsletter ni case d'inscription à des communications.

**Questions.**

1. Les relances ciblées (infos pratiques avant le jour J) sont-elles des emails de service, sans
   accord nécessaire ?
2. Peut-on écrire aux participants pour annoncer RFL4, ou pour un appel aux dons ? Avec quelle case,
   et pendant combien de temps après l'événement ?
3. Lien de désinscription obligatoire sur quels emails ?

---

## 11. Brief complémentaire (8 novembre 2026)

À cadrer avant développement, après la mise en production de la V1.

### Groupes (entreprises, associations, écoles)

1. Un responsable de groupe voit la liste de ses membres : quelles données a-t-il le droit de voir
   (nom, épreuve, statut ; jamais santé ni contact d'urgence) ? Base légale ?
2. Paiement centralisé par une entreprise : facture, TVA, CGV propres aux groupes, places non
   utilisées, remboursements.
3. Élèves inscrits via une école : qui consent, qui signe la décharge, qui est responsable le jour J ?

### Bénévoles

4. **Google Forms** : transfert hors UE, information des candidats, durée de conservation, qui y a
   accès. Faut-il préférer un formulaire sur la plateforme ?
5. **Bénévoles mineurs** : âge minimum, autorisation parentale, missions interdites (commissaire de
   course, conduite).
6. **Allergies alimentaires** : données de santé. Base légale et accès restreint.
7. **Photo de badge** : finalité, durée, qui la voit. Distincte du droit à l'image.
8. Statut du bénévole : convention ou charte de bénévolat, assurance de l'association couvrant les
   bénévoles, transport de matériel avec un véhicule personnel.

### Ateliers du Village Santé Mentale

9. La réservation d'un atelier sur un thème de santé mentale révèle-t-elle une information de santé ?
10. Les associations animatrices reçoivent la liste de leurs participants : sont-elles destinataires,
    sous-traitantes ou responsables conjointes ? Faut-il une convention ?
11. Mineurs dans un atelier : accompagnement, autorisation.

---

## 12. Hors plateforme (pour mémoire)

Ces points ne dépendent pas du site, mais leurs réponses alimentent le règlement et les CGV.

- Déclaration ou autorisation de la manifestation sur la voie publique (mairie, préfecture).
- Assurance responsabilité civile de l'organisateur : attestation, plafonds, couverture des bénévoles.
- Dispositif de secours et responsabilité en cas d'accident.
- Usage du nom « Lorène » et des marques (Run For Lorène, logos) : dépôt, accord de la famille.

---

## 13. Ce qu'il faut repartir avec

| Livrable | Qui le rédige | Pour quand | Effet sur le code |
| --- | --- | --- | --- |
| Décharge réécrite (et version mineur si besoin) | Juriste ou association | Avant la beta | Nouvelles affirmations en base (nouvelle version) |
| Décision sur la conservation des réponses de santé | Association, sur avis du juriste | Avant la beta | Garder les réponses ou seulement l'attestation |
| Règle d'inscription des mineurs (case « représentant légal », âge minimum) | Association | Avant la beta | Case ajoutée au tunnel, contrôle d'âge |
| Règlement de la course (PDF) | Association | Avant le 15 janvier | Publication dans l'admin |
| CGV (page) | Juriste ou association | Avant le 15 janvier | Page `cgv` à créer, case éventuelle |
| Mentions légales (page) | Association | Avant la beta | Page `mentions-legales` à créer |
| Politique de protection des données (PDF) | Juriste ou association | Avant le 15 janvier | Publication dans l'admin ; case « RGPD » revue |
| Texte du droit à l'image (PDF) | Juriste ou association | Avant le 15 janvier | Publication dans l'admin |
| Modèle de reçu fiscal et règle de numérotation | Association (trésorier) | Avant le 15 janvier | Génération du reçu PDF |
| Tableau des durées de conservation | Juriste | Avant le 15 janvier | Purges automatiques à ajouter |
| AIPD et registre des traitements | Association, avec le juriste | Avant le 15 janvier | Aucun |

Les textes sont publiés depuis le back-office et versionnés : chaque acceptation garde la version
du texte, la date et l'adresse IP. Une nouvelle version ne modifie jamais les acceptations déjà données.
