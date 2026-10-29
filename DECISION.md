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
