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
