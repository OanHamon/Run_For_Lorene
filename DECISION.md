# Journal des décisions

Corrections issues de l'audit du 29 octobre 2026, traitées de la plus grave à la moins grave.
Chaque entrée donne le problème constaté, les solutions envisagées, le choix fait et pourquoi.
Si une décision ne te convient pas, la section « Pour revenir en arrière » dit quoi changer.

Légende : 🔴 élevé · 🟠 moyen · 🟡 faible · ❓ question à trancher par l'association

---

## 1. 🔴 Commande payée bloquée « en attente » après un webhook rejoué

**Problème.** `EnregistrerPaiement::reussi()` marquait le paiement « réussi » dans une première
transaction, puis confirmait la commande (statut, dossards, email) à part. Si la confirmation
plantait (base bloquée pendant le rush de l'ouverture des inscriptions, par exemple), le webhook
répondait 500 et Stripe le renvoyait. Au renvoi, le paiement était déjà « réussi » : le code
s'arrêtait là. La commande restait « en cours » pour toujours, l'argent était encaissé, sans
dossard, et le participant voyait « paiement déjà en cours » à chaque essai. Le tableau de bord
la signalait, mais avec un motif faux (« montant reçu inférieur ») et aucun moyen de la débloquer.

**Solutions envisagées.**
1. Tout faire dans une seule transaction (paiement + confirmation). Si la confirmation plante,
   tout est annulé et le renvoi de Stripe recommence proprement.
   *Écarté :* l'email de confirmation et l'appel au verrou des courses rendent la transaction
   longue ; et si l'erreur se répète, on perd la trace que l'argent est arrivé.
2. Au renvoi du webhook, si le paiement est déjà « réussi » mais la commande encore « en cours »
   et couverte par le montant, confirmer la commande. **Retenu.**
3. Une tâche planifiée qui cherche ces commandes et les confirme.
   *Écarté pour l'instant :* plus lourd, et le renvoi de Stripe (jusqu'à 3 jours) suffit dans
   presque tous les cas.

**Choix.** Solution 2, complétée d'un filet de sécurité manuel :
- le motif du tableau de bord distingue maintenant « paiement encaissé mais commande restée en
  attente : confirme-la depuis sa fiche » du vrai cas « montant insuffisant » ;
- la fiche de la commande affiche un encadré et un bouton « Confirmer la commande » (super admin,
  niveau 3, comme les remboursements), tracé dans le journal (`commande.confirmation`).
  Le bouton refuse si aucun paiement réussi ne couvre le total.

**Pourquoi.** C'est le plus petit changement qui rend le traitement réellement rejouable, sans
toucher au chemin normal. Le bouton couvre le cas où Stripe a cessé de renvoyer l'événement.

**Fichiers.** `app/Services/Paiement/EnregistrerPaiement.php`, `app/Services/Admin/Statistiques.php`
(motif + `Statistiques::couvre()`), `app/Http/Controllers/Admin/CommandeController.php` (`confirmer`),
`routes/admin.php`, `resources/views/admin/commandes/show.blade.php`, `app/Models/JournalAdmin.php`.

**Tests.** `tests/Feature/Inscription/PaiementTest.php` : webhook rejoué après une confirmation
interrompue ; commande signalée puis confirmée par un super admin (refusé au niveau 2) ; refus
sans paiement couvrant.

**Pour revenir en arrière.** Supprimer le bloc `if ($dejaEnregistre)` dans `reussi()` et la route
`admin.commandes.confirmer`.

---

## 2. 🔴 Double-clic sur « Payer » : fausse erreur « paiement déjà en cours »

**Problème.** Le premier clic crée une tentative avec une référence provisoire `tmp_…`, puis
appelle Stripe pour créer la session (hors transaction). Le second clic, arrivé entre-temps,
voulait fermer cette tentative chez Stripe : Stripe ne connaît pas `tmp_…`, l'appel échouait, et
le code refusait par prudence (« Un paiement est déjà en cours de validation »). Le navigateur
affichant la réponse du dernier clic, la personne tombait sur la page d'échec au lieu de Stripe.
Les formulaires « Payer » (espace, page 09b, reprise dans le tunnel) n'étaient pas protégés
contre le double envoi ; seul le bouton Livewire du récapitulatif l'était.

**Solutions envisagées.**
1. Désactiver le bouton au clic, côté navigateur. Nécessaire, mais insuffisant seul (JS désactivé,
   deux onglets, réseau lent qui fait recliquer après rechargement).
2. Côté serveur, considérer une tentative `tmp_` comme « rien à fermer chez Stripe ». **Retenu.**
3. Mettre la création de la session Stripe dans la transaction (verrou sur la commande jusqu'à
   la réponse de Stripe). *Écarté :* garde un verrou de base pendant un appel réseau.
4. Si une tentative `tmp_` existe, attendre ou renvoyer vers elle. *Écarté :* on n'a pas encore
   son URL Stripe.

**Choix.** Solutions 1 et 2 ensemble.
- `PasserelleStripe::annuler()` répond « fermable » pour une référence provisoire (constante
  `DemarrerPaiement::REFERENCE_PROVISOIRE`). La tentative est marquée échouée et une nouvelle part.
- Les 4 formulaires « Payer » portent `data-envoi-unique` ; un petit script commun
  (`resources/js/app.js`) bloque le second envoi, désactive le bouton et affiche « Un instant… ».
  Si on revient de Stripe par « Précédent », le bouton redevient utilisable.

**Risque accepté.** Si les deux sessions Stripe finissent par exister et que la personne paie
les deux (deux onglets), le second paiement est enregistré et signalé au tableau de bord
(« commande payée plusieurs fois : rembourse le doublon »), comme avant.

**Fichiers.** `app/Services/Paiement/PasserelleStripe.php`, `app/Services/Paiement/DemarrerPaiement.php`,
`resources/js/app.js`, `resources/views/inscription/paiement-echec.blade.php`,
`resources/views/site/espace.blade.php`, `resources/views/livewire/espace/inscription.blade.php`,
`resources/views/livewire/inscription/tunnel.blade.php`.

**Tests.** `PaiementTest` : second clic pendant la création de la première session → redirection
vers Stripe, première tentative fermée ; présence de `data-envoi-unique` sur les pages.

---

## 3. 🔴 Réinscription après annulation : l'historique de la commande payée était effacé

**Problème.** Une seule inscription par participant et par événement (contrainte UNIQUE voulue
par le spec). Quand une inscription annulée était refaite (tunnel ou inscription gratuite admin),
la même ligne était réutilisée : son lien vers la commande (`id_commande`) et son prix payé
étaient remis à zéro. La commande payée, avec ses paiements et ses remboursements, ne disait plus
pour qui elle avait été payée. Et si la personne « retirait » ensuite ce brouillon du panier, la
ligne était supprimée avec tous ses documents (suppression en cascade).

**Solutions envisagées.**
1. Supprimer la contrainte UNIQUE et créer une nouvelle ligne à chaque réinscription.
   *Écarté :* contraire au spec et à CLAUDE.md (unicité garantie en base).
2. Ajouter une table ou une colonne d'historique (`commande précédente`).
   *Écarté pour l'instant :* change le modèle de données du spec, à décider avec l'association.
3. Ne plus permettre en ligne la réinscription d'une inscription **réglée** (payée ou offerte)
   puis annulée ; la laisser possible quand l'annulation a eu lieu avant tout paiement (rien
   d'encaissé, rien à perdre). Côté admin, garder la reprise mais noter l'ancienne commande au
   journal avant de la détacher. **Retenu.**

**Choix.** Solution 3.
- `Eligibilite::annuleeApresReglement()` : inscription annulée dont la commande est payée ou
  gratuite.
- Tunnel : la personne apparaît grisée avec le badge « Inscription annulée » dans « Pour qui ? » ;
  si on force, message « L'inscription de X a été annulée par l'association. Pour la refaire,
  contacte l'association. » (vérifié aussi à l'étape Épreuve). La ligne n'est donc jamais
  réutilisée ni supprimée par le tunnel dans ce cas.
- Inscription gratuite admin : la reprise reste possible ; avant de détacher l'ancienne commande,
  une entrée `inscription.reprise` est écrite au journal (commande précédente, son statut, prix
  payé), visible sur la fiche de l'inscription.

**Pourquoi.** C'est la règle la plus prudente sans toucher au modèle de données : aucun lien
avec de l'argent encaissé ne disparaît sans trace, et la personne n'est jamais bloquée sans
explication (elle sait qui contacter).

**❓ Question pour l'association.** Une personne dont l'inscription a été annulée par
l'association (après paiement) a-t-elle le droit de se réinscrire seule en ligne ? Si oui, il
faudra la solution 2 (historique en base) pour ne pas perdre le lien avec la commande payée.

**Fichiers.** `app/Services/Inscriptions/Eligibilite.php`, `resources/views/livewire/inscription/tunnel.blade.php`,
`app/Services/Admin/InscrireGratuitement.php`, `app/Models/JournalAdmin.php`.

**Tests.** `TunnelTest` : réglée puis annulée → bloquée, commande intacte ; annulée avant
paiement → réinscription possible. `InscriptionGratuiteTest` : reprise admin notée au journal.

**Pour revenir en arrière.** Faire renvoyer `false` à `annuleeApresReglement()` : on retrouve
l'ancien comportement (le journal admin reste).

---

## 7. 🟠 Règlement « accepté » alors que son PDF n'existe pas

*(Traité avant les points 4 à 6 : 4 attend des textes de l'association et 5-6 sont des
fonctionnalités, voir plus bas.)*

**Problème.** Le tunnel vérifiait seulement qu'une ligne « texte légal » existait en base, pas que
le PDF était sur le disque. On pouvait donc faire accepter un règlement impossible à ouvrir (lien
« Lire le règlement » en 404). C'était le cas en local : les seeders créaient les lignes sans
fichier (5 PDF sur 6 absents).

**Solutions envisagées.**
1. Vérifier le fichier seulement à l'affichage du lien. *Écarté :* on continuerait d'enregistrer
   un consentement à un texte illisible.
2. Vérifier le fichier avant d'enregistrer l'acceptation, et prévenir l'admin. **Retenu.**
3. Stocker le PDF en base. *Écarté :* hors spec (fichiers privés sur disque).

**Choix.**
- `TexteLegal::fichierPresent()`.
- Tunnel (`textesPublies`) : un texte obligatoire sans PDF bloque le volet comme un texte non
  publié (« Un document légal n'est pas encore publié… »), et l'erreur part dans les logs.
- Admin : la checklist « Prêt à publier ? » ne compte plus un texte sans fichier ; la fiche de
  l'événement affiche une alerte « PDF introuvable sur le serveur » (même publié) et le tableau
  des versions montre « PDF introuvable » au lieu du lien.
- Seeders : un PDF provisoire d'une page (« Document provisoire de développement ») est écrit pour
  chaque texte (`database/seeders/PdfProvisoire.php`). Les tests utilisent un disque factice
  (`tests/TestCase.php`), jamais le vrai dossier `storage`.

**À faire de ton côté.** Ta base locale garde des lignes sans fichier : `php artisan migrate:fresh --seed`
(efface les données locales) ou republie les PDF depuis l'admin.

**Fichiers.** `app/Models/TexteLegal.php`, `resources/views/livewire/inscription/tunnel.blade.php`,
`app/Services/Admin/PreparationEvenement.php`, `resources/views/admin/evenements/show.blade.php`,
`database/seeders/*`, `tests/TestCase.php`.

**Tests.** `TunnelTest` : PDF supprimé → volet Conditions bloqué, alerte sur la fiche admin.
`TarifsTest` mis à jour (les textes créés ont maintenant un fichier).

---

## 8. 🟠 Page de confirmation qui se recharge sans fin

**Problème.** Tant que le webhook Stripe n'était pas arrivé, la page de confirmation se
rechargeait toutes les 3 secondes, sans limite (webhook mal configuré, commande bloquée…). Le
lecteur d'écran relisait le message à chaque fois.

**Solutions envisagées.**
1. Interroger Stripe depuis la page de retour (`session_id`) pour confirmer sans attendre le
   webhook. *Écarté :* le spec dit que le retour sur la page de succès n'est qu'un affichage,
   seul le webhook fait foi.
2. Remplacer le rechargement par un appel JavaScript périodique. *Écarté :* plus de code pour le
   même résultat ; le rechargement simple marche sans JS.
3. Limiter les rechargements (compteur dans l'adresse), puis afficher un message clair. **Retenu.**

**Choix.** 20 rechargements de 3 s (une minute), compteur `?essai=` dans l'URL
(`PaiementController::RECHARGEMENTS_MAX`). Ensuite : « Ton paiement est en cours de vérification…
Ne repaie pas : ta commande est gardée dans ton espace », avec « Vérifier à nouveau » et « Aller à
mon espace ». Le message d'attente n'est annoncé au lecteur d'écran qu'au premier affichage.
Si la personne clique malgré tout sur « Payer » dans son espace, le paiement déjà abouti chez
Stripe bloque la nouvelle tentative (comportement existant).

**Fichiers.** `app/Http/Controllers/PaiementController.php`, `resources/views/inscription/confirmation.blade.php`.

**Tests.** `PaiementTest` : rechargement au début, plus de rechargement et message au 20e essai.

---

## 9. 🟠 Tunnel : panneau suivant hors écran, focus perdu, « précédent » qui fait sortir

**Problème.** Au changement de panneau (« Continuer »), la page restait à la même hauteur : sur
mobile, après le long questionnaire de santé, le panneau suivant s'affichait hors écran. Le focus
clavier restait sur un bouton disparu (le lecteur d'écran ne savait pas qu'une nouvelle étape
s'affichait). Et le tunnel tenant sur une seule adresse, le bouton « précédent » du navigateur
faisait quitter l'inscription au lieu de revenir au panneau précédent.

**Solutions envisagées.**
1. Une adresse par étape (`?etape=…` synchronisée avec Livewire). *Écarté :* l'étape deviendrait
   modifiable depuis l'adresse, contournant l'ordre du tunnel (décharge avant paiement…).
2. Script léger : au nouveau panneau, remonter en haut et donner le focus au titre ; « précédent »
   du navigateur branché sur le bouton « Retour » du tunnel. **Retenu.**

**Choix.**
- Chaque panneau (étape **et** volet de l'étape Documents, la clé inclut maintenant le volet) remonte
  en haut de page (sans animation si « réduire les animations » est activé) et place le focus sur
  son titre. Rien au premier affichage, pour ne pas voler l'autofocus des champs.
- Une entrée d'historique est ajoutée à l'ouverture du tunnel (connecté) ; « précédent » appelle
  `retour()`. `retour()` renvoie maintenant vrai/faux : faux quand il n'y a pas de panneau
  précédent (paiement en attente, inscriptions fermées), et le navigateur quitte alors la page
  normalement. Au premier panneau sans commande, « Retour » ramène à la page de l'événement.

**Limite.** Pas de test automatique du comportement navigateur (pas d'outil de test navigateur
dans le projet) : à vérifier à la main sur téléphone pendant la recette (lot 7).

**Fichiers.** `resources/views/livewire/inscription/tunnel.blade.php`.

---

## 10. 🟠 Remboursement admin en même temps que le webhook `charge.refunded`

**Problème.** L'admin rembourse : on appelle Stripe, puis on enregistre la ligne de remboursement
(référence unique). Si le webhook `charge.refunded` arrivait entre les deux, il enregistrait la
même référence ; l'insertion de l'admin tombait sur la contrainte UNIQUE et l'admin voyait une
erreur 500, alors que le remboursement était bien parti chez Stripe (risque de le refaire).

**Solutions envisagées.**
1. Ignorer `charge.refunded` pour les remboursements faits depuis l'admin (métadonnée).
   *Écarté :* si la réponse de l'API se perd, c'est justement le webhook qui rattrape la ligne.
2. Rendre l'enregistrement sûr en concurrence : lecture verrouillante, insertion, et en cas de
   doublon, relire la ligne de l'autre chemin. **Retenu.**

**Choix.** `Rembourser::enregistrer()` lit avec verrou (elle voit la dernière version validée,
même dans la transaction de l'admin), insère, et si la contrainte UNIQUE se déclenche, relit la
ligne existante. Si la ligne vient du webhook (sans auteur), l'admin la complète avec son nom et
son motif. Le premier `firstOrCreate` de Laravel n'était pas suffisant : dans une transaction,
sa relecture ne voit pas la ligne insérée par l'autre requête.

**Fichiers.** `app/Services/Paiement/Rembourser.php`.

**Tests.** `CommandesTest` : webhook enregistré pendant l'appel admin → une seule ligne, auteur et
motif de l'admin, montant remboursable juste.

---

## 11. 🟠 Frais Stripe souvent vides

**Problème.** Les frais étaient lus une seule fois, à `checkout.session.completed`. À ce moment la
transaction de solde Stripe n'existe pas toujours : les frais restaient vides pour toujours et le
tableau de bord sous-estimait les frais.

**Solutions envisagées.**
1. Relire les frais à l'affichage de la commande dans l'admin. *Écarté :* appel Stripe à chaque
   affichage, et le tableau de bord (somme des frais) resterait faux.
2. Tâche planifiée qui complète les frais manquants. *Possible plus tard*, plus lourd.
3. Écouter `charge.succeeded` et `charge.updated`, que Stripe envoie quand la transaction de solde
   est prête, et compléter les frais manquants. **Retenu.**

**Choix.** Solution 3 (`EnregistrerPaiement::completerFrais`, `PasserelleStripe::fraisTransaction`).
On ne touche qu'à un paiement dont les frais sont vides ; et `checkout.session.completed`
n'efface plus des frais déjà complétés si l'ordre d'arrivée est inversé.

**⚠️ À faire dans le tableau de bord Stripe.** Ajouter les événements `charge.succeeded` et
`charge.updated` au point de terminaison du webhook (en plus de `checkout.session.*` et
`charge.refunded`). Sans ça, rien ne change, mais rien ne casse.

**Fichiers.** `app/Http/Controllers/StripeWebhookController.php`, `app/Services/Paiement/EnregistrerPaiement.php`,
`app/Services/Paiement/PasserelleStripe.php`.

**Tests.** `CommandesTest` : `charge.updated` complète les frais, reçu deux fois → un seul appel Stripe.

---

## 5. 🟠 « Faire un don » ne menait nulle part, pas de don seul

**Problème.** Le spec prévoit qu'un don peut être fait seul, sans inscription. Le service de
commande savait le traiter, mais le tunnel désactivait « Payer » quand il n'y avait aucune
inscription, et le bouton « Faire un don » de la section « La cause » pointait sur `#`.

**Solutions envisagées.**
1. Un formulaire de don à part (page et contrôleur dédiés). *Écarté :* doublon du panneau Don,
   du récapitulatif et du paiement qui existent déjà.
2. Don sans compte (email seul). *Écarté :* une commande appartient à un compte (MLD), et le
   reçu fiscal comme l'historique des dons passent par l'espace.
3. Entrée « don seul » dans le tunnel existant : `/inscription?don=1`. **Retenu.**

**Choix.**
- « Faire un don » (section « La cause ») mène à `/inscription?don=1`. Les autres liens « Faire
  un don » (héro, pied de page) gardent l'ancre `#cause`, qui présente la cause avant le bouton.
- Non connecté : identification, avec un mot d'explication (« ton don est rattaché à ton compte,
  pour ton reçu fiscal »), puis retour au don.
- Connecté : directement au panneau Don (titre « Faire un don », pas de « Passer », pas de barre
  « Étape x sur 7 »), puis récapitulatif et paiement Stripe.
- **Le don reste possible quand les inscriptions sont fermées** : la page « Inscriptions fermées »
  le proposait déjà (« Tu peux toujours soutenir la cause par un don »). Le lien « Inscrire
  quelqu'un à la course » n'apparaît que si les inscriptions sont ouvertes.
- Avec des inscriptions déjà dans le panier, `?don=1` reprend le parcours normal : le don rejoint
  leur commande.
- Une commande « don seul » en attente de paiement est reprise par le tunnel comme les autres ;
  « Modifier ma commande » ramène au panneau Don.
- Confirmation et email adaptés (« Merci pour ton don ! », objet « Merci pour ton don à Run For
  Lorène »).

**Hors périmètre.** Le reçu fiscal PDF n'existe pas encore (voir n° 6).

**Fichiers.** `resources/views/livewire/inscription/tunnel.blade.php`, `app/Http/Controllers/PaiementController.php`,
`resources/views/inscription/confirmation.blade.php`, `app/Notifications/CommandeConfirmee.php`,
`resources/views/site/evenement.blade.php`.

**Tests.** `TunnelTest` : don seul inscriptions fermées → payé, confirmé, email et page adaptés ;
commande don seul en attente reprise et « Modifier » → don ; lien de la page de l'événement.

---

## 4. 🟠 Liens légaux morts dans le pied de page

**Problème.** Mentions légales, CGV, Confidentialité, Cookies et Questions fréquentes pointaient
sur `#`. Les mentions légales et des CGV sont obligatoires pour un site qui encaisse des paiements :
bloquant pour la mise en production.

**Ce que je n'ai pas fait.** Rédiger les mentions légales et les CGV. Elles contiennent des
informations que seule l'association a (responsable de publication, adresse du siège, hébergeur
exact, statut fiscal) et des règles métier (conditions d'annulation et de remboursement, droit de
rétractation) : CLAUDE.md interdit de les inventer.

**Solutions envisagées.**
1. Pages avec des « [à compléter] » visibles. *Écarté :* risque de partir en ligne tel quel.
2. Retirer les liens jusqu'à ce que les textes existent. *Écarté pour les mentions légales et les
   CGV :* leur absence se remarquerait moins, alors qu'elles bloquent la mise en ligne.
3. Une page par texte, qui affiche « pas encore en ligne » (comme les PDF légaux manquants) tant
   que l'association n'a pas fourni le contenu ; relier ce qui existe déjà. **Retenu.**

**Choix.**
- Route `/infos/{page}` (`PageController`) : `mentions-legales`, `cgv`, `cookies`. Le texte est une
  vue `resources/views/site/pages/{page}.blade.php` ; sans vue, page « Ce document n'est pas encore
  en ligne… » (statut 404, comme un texte légal absent).
- **Confidentialité** → le PDF « Politique de protection des données » déjà publié par l'admin.
- **Cookies** → page rédigée, uniquement factuelle : le site ne pose que des cookies nécessaires
  (session 2 h, jeton anti-falsification, « Rester connecté » 400 jours si coché), donc pas de
  bandeau de consentement ; Stripe pose les siens sur sa page de paiement. *À relire par
  l'association.*
- **Questions fréquentes** : lien retiré tant qu'il n'y a pas de contenu.

**❓ À fournir par l'association avant la mise en ligne.** Texte des mentions légales (éditeur,
responsable de publication, siège, hébergeur OVH exact) et des CGV (prix, paiement, annulation,
remboursement, rétractation), et le contenu d'une FAQ si elle est voulue. Il suffira de créer
`site/pages/mentions-legales.blade.php` et `site/pages/cgv.blade.php`.

**Fichiers.** `app/Http/Controllers/PageController.php`, `routes/web.php`,
`resources/views/site/pages/cookies.blade.php`, `resources/views/site/evenement.blade.php`.

**Tests.** `SecuriteTest` : plus aucun `href="#"` sur la page de l'événement, page cookies servie,
page manquante → « pas encore en ligne ».

---

## 6. 🟠 Fonctions du spec absentes (emails, relances, billet, reçu fiscal)

**Problème.** Plusieurs éléments du spec n'existaient pas : email « inscription validée » (avec
dossard), relance des documents 15 jours avant l'événement, rappel 24 h après un paiement non
abouti, billet QR, reçu fiscal PDF, email « document refusé ».

**Tri fait.** Je n'ai construit que ce dont la règle est déjà fixée par le spec ; le reste
demande des décisions de l'association.

### Fait

- **Email « Inscription validée »** (`InscriptionValidee`) quand une inscription passe de
  « dossier incomplet » à « validée » (documents complétés depuis l'espace) : dossard dans
  l'objet et le message. Pas d'email en double à la commande : le récapitulatif
  `CommandeConfirmee` donne déjà les dossards des inscriptions validées d'emblée.
- **Relance documents à J-15** (`rfl:relancer-documents`, chaque jour à 10 h) : chaque inscription
  en « dossier incomplet » d'un événement publié qui a lieu dans 15 jours ou moins. Une seule
  relance par inscription. *Choix :* « dans 15 jours ou moins » plutôt que « exactement à J-15 »,
  pour ne pas rater la relance si le planificateur était arrêté ce jour-là.
- **Rappel paiement 24 h après l'abandon** (`rfl:relancer-paiements`, toutes les heures) :
  commande toujours en attente, dernière tentative fermée depuis 24 h (ou commande sans
  tentative), une seule fois. *Choix :* pas de rappel si une tentative est en cours ; pas de
  rappel au-delà de 7 jours (commande laissée de côté) ; **pas de rappel pour un don seul**
  (un don reste un geste libre, ton de la charte : pas de culpabilisation).
- Les deux relances réutilisent l'email existant `RelanceInscription` (celui du bouton « Relancer »
  de l'admin). « Une seule fois » est gardé en cache (base de données), 60 et 30 jours.

**Prérequis production.** Le cron du planificateur (`* * * * * php artisan schedule:run`) et un
worker de file, déjà notés dans `.env.example`.

### Pas fait : ❓ à décider avec l'association

- **Billet QR** : que contient le QR et qui le scanne le jour J (retrait des dossards, outil de
  pointage) ? Sans ça, impossible de choisir le contenu ni la sécurité du code.
- **Reçu fiscal PDF** : il faut les mentions légales du reçu (Cerfa n° 11580, numéro et objet de
  l'association, article du CGI, signataire) et la règle de numérotation. Ajoute aussi une
  dépendance PDF (dompdf).
- **Email « document refusé »** : le back-office ne sait que marquer un questionnaire « vérifié » ;
  le spec ne dit pas qui peut refuser, pour quels motifs, ni ce que devient l'inscription.

**Fichiers.** `app/Notifications/InscriptionValidee.php`, `app/Services/Inscriptions/MettreAJourStatut.php`,
`app/Services/Inscriptions/Relances.php`, `routes/console.php`.

**Tests.** `tests/Feature/Relances/RelancesTest.php` : J-20 rien, J-15 une relance, seulement les
dossiers incomplets ; paiement : rien avant 24 h ni pendant une tentative ouverte, une seule
relance ensuite ; email de validation avec le dossard.

---

## 12. 🟡 Petits points de sécurité

| Point | Choix | Pourquoi |
|---|---|---|
| `etape` du tunnel modifiable depuis le navigateur (pouvait provoquer une erreur 500) | `#[Locked]` : seules les actions du serveur la changent | Aucun champ ne la lie ; on ferme la porte au lieu de valider chaque valeur. |
| Code de connexion et code d'entrée dans l'admin partageaient la même clé : l'un valait l'autre | Usages séparés dans `CodeEmail` (`CONNEXION`, `ADMIN`) : clés, essais et délai de renvoi distincts ; quota horaire commun ; l'email dit à quoi sert le code | La confirmation admin doit être une étape à part entière. Le quota commun protège la boîte mail. |
| `/activation/{id}` révélait quels identifiants de compte existent (404 ou page « lien invalide ») | Compte cherché à la main : même page « lien invalide » pour un identifiant inconnu | Rien à gagner à distinguer les cas pour un lien d'activation. |
| « Mot de passe actuel » de « Mon compte » sans limite d'essais | 5 essais par minute et par compte, comme à la connexion | Une session volée ne doit pas permettre de deviner le mot de passe. |

Écartés : faire disparaître la différence entre « compte existant » et « nouveau compte » à
l'identification. C'est le principe « façon Google » voulu par le spec (écran 03), déjà limité à
30 essais par minute et par adresse IP.

**Fichiers.** `resources/views/livewire/inscription/tunnel.blade.php`, `app/Services/Auth/CodeEmail.php`,
`app/Http/Controllers/Admin/ConfirmationController.php`, `app/Notifications/CodeEmailNotification.php`,
`app/Http/Controllers/ActivationController.php`, `resources/views/livewire/espace/compte.blade.php`.

**Tests.** `SecuriteTest` (étape verrouillée, activation, limite du mot de passe actuel),
`ConfirmationAdminTest` (un code de connexion est refusé à l'entrée de l'admin).

---

## 13. 🟡 Couleurs hors charte et restes du starter kit

**Problème.** Les messages d'erreur du tunnel et de la page 09b utilisaient `bg-rose-50 text-rose-700`
en dur (au lieu de la classe partagée `rfl-alerte-erreur`), l'espace participant un hex en dur
`text-[#8A6A0B]`, et les boutons « Supprimer » des teintes `rose-*` variées. Le starter kit
avait laissé des vues mortes (layouts Flux, logo, en-têtes d'authentification…), des tokens
`zinc`, un mode sombre jamais utilisé, et la bibliothèque Flux chargeait 290 kB de CSS pour
rien (aucune page n'utilise ses composants).

**Solutions envisagées.**
1. Erreurs en couleurs de la charte (moutarde ou vert sombre). *Écarté :* une erreur doit se
   distinguer d'une information ou d'un « à faire » (la moutarde est déjà l'accent « à faire »).
2. Garder un rouge, mais une seule teinte, en tokens nommés, et des classes partagées. **Retenu.**

**Choix.**
- Tokens dérivés ajoutés dans `app.css` : `erreur` (#9F1239), `erreur-fond`, `erreur-bord` pour
  les erreurs et les actions destructrices, `moutarde-texte` (#8A6A0B, moutarde lisible en texte
  sur blanc, AA). `rfl-alerte-erreur` et `rfl-erreur` passent par eux ; toutes les vues aussi.
  Plus aucun `rose-*` ni hex en dur dans les vues. CLAUDE.md mis à jour.
- Suppression de Flux (`composer remove livewire/flux`), de `@fluxAppearance` / `@fluxScripts`, des
  vues mortes du starter kit, des tokens `zinc`, du mode sombre et des règles `data-flux-*`.
  Le CSS passe de 346 kB à 57 kB (10 kB compressé). Le layout Livewire par défaut (filet de
  sécurité) devient l'espace participant.

**❓ À valider avec Pop Com / l'association.** La teinte d'erreur (#9F1239) n'est pas dans la
charte : à confirmer, ou à remplacer en changeant les trois tokens `erreur*`.

**Fichiers.** `resources/css/app.css`, vues du tunnel, de l'espace et de l'admin, `config/livewire.php`,
`composer.json`, `CLAUDE.md` ; vues supprimées sous `resources/views/components/` et `resources/views/flux/`.

**Vérification.** Suite de tests complète, et rendu des pages publiques, du tunnel et de la
connexion contrôlé sur un serveur local.
