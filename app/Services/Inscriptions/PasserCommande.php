<?php

namespace App\Services\Inscriptions;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Récapitulatif confirmé : Brouillon → En attente de paiement.
 *
 * Crée la commande (montant figé, envoyé tel quel à Stripe), historise le prix de chaque
 * inscription et le don. Toutes les règles sont revérifiées ici, sous verrou des courses
 * concernées, pour que deux commandes simultanées ne dépassent pas un quota.
 */
class PasserCommande
{
    public function __construct(
        private Eligibilite $eligibilite,
        private ConfirmerCommande $confirmerCommande,
    ) {}

    /**
     * @param  Collection<int, Inscription>  $brouillons
     *
     * @throws ValidationException (clé "commande") si une règle n'est plus respectée
     */
    public function __invoke(User $payeur, Evenement $evenement, Collection $brouillons, float $don, bool $recuFiscal = false, bool $anonyme = false): Commande
    {
        if ($brouillons->isEmpty() && $don <= 0) {
            throw ValidationException::withMessages(['commande' => 'Ta commande est vide.']);
        }

        return DB::transaction(function () use ($payeur, $evenement, $brouillons, $don, $recuFiscal, $anonyme) {
            // Verrou des courses dans un ordre stable (évite les interblocages entre commandes).
            $idsCourses = $brouillons->map(fn (Inscription $i) => $i->tarif->id_course)->unique()->sort()->values();
            $courses = Course::whereIn('id_course', $idsCourses)->orderBy('id_course')->lockForUpdate()->get()->keyBy('id_course');

            $lignes = [];
            $demandesParCourse = [];

            foreach ($brouillons as $inscription) {
                $inscription = Inscription::whereKey($inscription->id_inscription)->lockForUpdate()->first();

                if (! $inscription || $inscription->statut !== Inscription::BROUILLON || $inscription->id_commande !== null) {
                    throw ValidationException::withMessages(['commande' => 'Ta commande a changé entre-temps. Vérifie ton récapitulatif.']);
                }

                $participant = $inscription->participant;
                $course = $courses[$inscription->tarif->id_course];
                $nom = $participant->user->prenom;

                if ($this->eligibilite->dejaInscrit($participant, $evenement)) {
                    throw ValidationException::withMessages(['commande' => "{$nom} est déjà inscrit(e) à cet événement."]);
                }

                if ($erreur = $this->eligibilite->erreur($participant, $course, $evenement)) {
                    throw ValidationException::withMessages(['commande' => "{$nom} : {$erreur}"]);
                }

                if (! $inscription->consentementsComplets()) {
                    throw ValidationException::withMessages(['commande' => "{$nom} : il manque des consentements obligatoires."]);
                }

                // Plusieurs participants du panier sur la même épreuve : il faut assez de places pour tous.
                $demandesParCourse[$course->id_course] = ($demandesParCourse[$course->id_course] ?? 0) + 1;
                $restantes = $course->placesRestantes();
                if ($restantes !== null && $demandesParCourse[$course->id_course] > $restantes) {
                    throw ValidationException::withMessages(['commande' => "Il ne reste pas assez de places sur {$course->nom} pour tout le monde."]);
                }

                $lignes[] = [$inscription, $this->eligibilite->tarif($participant, $course, $evenement)];
            }

            $don = round(max($don, 0), 2);
            $total = round(collect($lignes)->sum(fn ($l) => (float) $l[1]->montant) + $don, 2);

            $commande = Commande::create([
                'date_commande' => now(),
                'montant_total' => $total,
                'statut' => Commande::EN_COURS,
                'id_user' => $payeur->id_user,
            ]);

            if ($don > 0) {
                Don::create([
                    'montant' => $don,
                    'recu_fiscal_demande' => $recuFiscal,
                    'anonyme' => $anonyme,
                    'id_commande' => $commande->id_commande,
                ]);
            }

            foreach ($lignes as [$inscription, $tarif]) {
                $inscription->forceFill([
                    'id_tarif' => $tarif->id_tarif,
                    'prix_paye' => $tarif->montant,
                    'id_commande' => $commande->id_commande,
                    'statut' => Inscription::EN_ATTENTE_PAIEMENT,
                ])->save();
            }

            // Rien à encaisser (épreuves gratuites, sans don) : pas de passage par Stripe.
            if ($total <= 0) {
                ($this->confirmerCommande)($commande);
            }

            return $commande;
        });
    }
}
