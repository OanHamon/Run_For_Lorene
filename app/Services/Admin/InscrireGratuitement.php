<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\TexteLegal;
use App\Models\User;
use App\Services\Inscriptions\Consentements;
use App\Services\Inscriptions\MettreAJourStatut;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inscription gratuite créée par un admin (cas particuliers, spec) : une commande à 0 €
 * marquée « gratuite », sans paiement. L'inscription est rattachée à un compte (créé au
 * besoin, connexion par code email) pour que le titulaire complète la décharge, et le
 * questionnaire de santé d'un mineur, depuis son espace. Le règlement et la politique RGPD,
 * recueillis par l'admin, sont enregistrés à son nom.
 *
 * Mêmes règles que le tunnel pour l'âge, le quota et l'unicité ; pas pour les dates
 * d'ouverture, ni pour le tarif (gratuit).
 */
class InscrireGratuitement
{
    public function __construct(
        private Consentements $consentements,
        private MettreAJourStatut $mettreAJourStatut,
    ) {}

    /**
     * @param  array{email: string, pour: string, prenom: string, nom: string, date_naissance: string, titulaire_prenom?: ?string, titulaire_nom?: ?string}  $donnees
     *
     * @throws ValidationException
     */
    public function __invoke(Course $course, array $donnees, ?string $ip): Inscription
    {
        return DB::transaction(function () use ($course, $donnees, $ip) {
            $evenement = $course->evenement;
            $titulaire = $this->titulaire($donnees);
            $participant = $this->participant($titulaire, $donnees);
            $age = $participant->ageAu($evenement->date_evenement);

            if (! $course->accepteAge($age)) {
                throw ValidationException::withMessages(['id_course' => "{$course->nom} n'est pas ouverte à l'âge du participant ({$age} ans le jour J)."]);
            }

            Course::whereKey($course->id_course)->lockForUpdate()->first();
            $restantes = $course->placesRestantes();
            if ($restantes !== null && $restantes <= 0) {
                throw ValidationException::withMessages(['id_course' => "{$course->nom} est complète : augmente son quota d'abord."]);
            }

            $tarif = $course->tarifPour($age) ?? $course->tarifs()->orderBy('valide_du')->first();
            if (! $tarif) {
                throw ValidationException::withMessages(['id_course' => "{$course->nom} n'a aucun tarif : ajoute-en un dans le paramétrage."]);
            }

            // Une inscription par participant et par événement : un brouillon ou une inscription
            // annulée est repris, une inscription en cours bloque.
            $inscription = Inscription::where('id_evenement', $evenement->id_evenement)
                ->where('id_participant', $participant->id_user)
                ->lockForUpdate()
                ->first();

            if ($inscription && ! in_array($inscription->statut, [Inscription::BROUILLON, Inscription::ANNULEE], true)) {
                throw ValidationException::withMessages(['email' => "{$participant->user->name} est déjà inscrit(e) à {$evenement->nom} ({$inscription->libelleStatut()})."]);
            }

            $commande = Commande::create([
                'date_commande' => now(),
                'montant_total' => 0,
                'statut' => Commande::GRATUITE,
                'id_user' => $titulaire->id_user,
            ]);

            $inscription ??= new Inscription;
            $inscription->forceFill([
                'id_participant' => $participant->id_user,
                'id_evenement' => $evenement->id_evenement,
                'id_tarif' => $tarif->id_tarif,
                'id_commande' => $commande->id_commande,
                'prix_paye' => 0,
                'statut' => Inscription::BROUILLON,
                'dossard' => null,
            ])->save();
            $inscription->declinaisons()->delete();

            // Règlement et RGPD seulement : le droit à l'image reste à la main du participant.
            foreach (TexteLegal::AVANT_PAIEMENT as $type) {
                $this->consentements->accepter($inscription, $type, Auth::id(), $ip);
            }

            return ($this->mettreAJourStatut)($inscription->fresh());
        });
    }

    /** Compte titulaire : existant (par email) ou créé, sans mot de passe (connexion par code). */
    private function titulaire(array $donnees): User
    {
        $email = mb_strtolower(trim($donnees['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $soiMeme = $donnees['pour'] === 'titulaire';
        $prenom = $soiMeme ? $donnees['prenom'] : ($donnees['titulaire_prenom'] ?? null);
        $nom = $soiMeme ? $donnees['nom'] : ($donnees['titulaire_nom'] ?? null);

        if (! $prenom || ! $nom) {
            throw ValidationException::withMessages(['titulaire_prenom' => "Aucun compte n'existe avec cet email : indique le prénom et le nom du titulaire pour le créer."]);
        }

        return User::create(['email' => $email, 'prenom' => $prenom, 'nom' => $nom]);
    }

    /** Le titulaire lui-même, ou un participant géré (retrouvé par prénom et nom, sinon créé). */
    private function participant(User $titulaire, array $donnees): Participant
    {
        if ($donnees['pour'] === 'titulaire') {
            return $titulaire->participant
                ?? Participant::create(['id_user' => $titulaire->id_user, 'date_naissance' => $donnees['date_naissance']]);
        }

        $existant = $titulaire->participantsGeres()->with('user')->get()->first(
            fn (Participant $p) => mb_strtolower($p->user->prenom) === mb_strtolower(trim($donnees['prenom']))
                && mb_strtolower($p->user->nom) === mb_strtolower(trim($donnees['nom']))
        );

        if ($existant) {
            return $existant;
        }

        $user = User::create(['prenom' => trim($donnees['prenom']), 'nom' => trim($donnees['nom'])]);

        return Participant::create([
            'id_user' => $user->id_user,
            'date_naissance' => $donnees['date_naissance'],
            'id_responsable' => $titulaire->id_user,
        ]);
    }
}
