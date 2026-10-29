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
