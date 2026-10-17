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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inscription gratuite créée par un admin (cas particuliers, spec) : une commande à 0 €
 * marquée « gratuite », sans paiement. L'inscription est rattachée à un compte (créé au
 * besoin, sans mot de passe : connexion par code email) pour que la famille complète la
 * décharge, et le questionnaire de santé d'un mineur, depuis son espace. Le règlement et la
 * RGPD, recueillis par l'admin, sont enregistrés à son nom.
 *
 * Le participant est choisi explicitement : le titulaire, un participant déjà rattaché au
 * compte, ou une nouvelle personne (refusée si elle ressemble à un participant existant :
 * même prénom et même date de naissance). Mêmes règles que le tunnel pour l'âge, le quota et
 * l'unicité ; pas pour les dates d'ouverture, ni pour le tarif (gratuit).
 */
class InscrireGratuitement
{
    public function __construct(
        private Consentements $consentements,
        private MettreAJourStatut $mettreAJourStatut,
    ) {}

    /**
     * @param  array{email: string, participant: string, prenom?: ?string, nom?: ?string, date_naissance?: ?string, titulaire_prenom?: ?string, titulaire_nom?: ?string}  $donnees
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
                throw ValidationException::withMessages(['participant' => "{$participant->user->name} est déjà inscrit(e) à {$evenement->nom} ({$inscription->libelleStatut()})."]);
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
        $email = Str::lower(trim($donnees['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        if (blank($donnees['titulaire_prenom'] ?? null) || blank($donnees['titulaire_nom'] ?? null)) {
            throw ValidationException::withMessages(['titulaire_prenom' => "Aucun compte n'existe avec cet email : indique le prénom et le nom du titulaire pour le créer."]);
        }

        return User::create(['email' => $email, 'prenom' => trim($donnees['titulaire_prenom']), 'nom' => trim($donnees['titulaire_nom'])]);
    }

    /** Participant choisi : « titulaire », l'identifiant d'un participant du compte, ou « nouveau ». */
    private function participant(User $titulaire, array $donnees): Participant
    {
        $choix = (string) $donnees['participant'];

        if ($choix === 'titulaire') {
            if ($titulaire->participant) {
                return $titulaire->participant;
            }

            $this->exiger($donnees, ['date_naissance_titulaire'], 'la date de naissance du titulaire');

            return Participant::create(['id_user' => $titulaire->id_user, 'date_naissance' => $donnees['date_naissance_titulaire']]);
        }

        if ($choix === 'nouveau') {
            $this->exiger($donnees, ['prenom', 'nom', 'date_naissance'], 'le prénom, le nom et la date de naissance de la nouvelle personne');

            // Garde-fou anti-doublon : même prénom (sans accents ni majuscules) et même date de naissance.
            $prenom = Str::lower(Str::ascii(trim($donnees['prenom'])));
            $doublon = $titulaire->participantsGeres()->with('user')->get()->first(
                fn (Participant $p) => $p->date_naissance->toDateString() === Carbon::parse($donnees['date_naissance'])->toDateString()
                    && Str::lower(Str::ascii($p->user->prenom)) === $prenom
            );
            if ($doublon) {
                throw ValidationException::withMessages(['participant' => "{$doublon->user->name}, né(e) le {$doublon->date_naissance->format('d/m/Y')}, est déjà rattaché(e) à ce compte : choisis-le ou la dans la liste plutôt que de créer une nouvelle personne."]);
            }

            $user = User::create(['prenom' => trim($donnees['prenom']), 'nom' => trim($donnees['nom'])]);

            return Participant::create([
                'id_user' => $user->id_user,
                'date_naissance' => $donnees['date_naissance'],
                'id_responsable' => $titulaire->id_user,
            ]);
        }

        // Participant existant : uniquement s'il appartient bien à ce compte.
        $participant = $titulaire->participantsGeres()->whereKey((int) $choix)->first()
            ?? ($titulaire->participant?->id_user === (int) $choix ? $titulaire->participant : null);

        if (! $participant) {
            throw ValidationException::withMessages(['participant' => 'Ce participant n\'appartient pas à ce compte.']);
        }

        return $participant;
    }

    /** @param list<string> $champs */
    private function exiger(array $donnees, array $champs, string $libelle): void
    {
        foreach ($champs as $champ) {
            if (blank($donnees[$champ] ?? null)) {
                throw ValidationException::withMessages([$champ => "Indique {$libelle}."]);
            }
        }
    }
}
