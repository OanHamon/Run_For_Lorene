<?php

namespace Database\Seeders;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Declinaison;
use App\Models\Document;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\InscriptionDeclinaison;
use App\Models\JournalAdmin;
use App\Models\Paiement;
use App\Models\Participant;
use App\Models\QuestionnaireSante;
use App\Models\Remboursement;
use App\Models\Tarif;
use App\Models\TexteLegal;
use App\Models\User;
use App\Services\Inscriptions\Consentements;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Données de démonstration (développement uniquement, jamais en tests) : de quoi parcourir
 * chaque écran de l'admin avec un volume réaliste et un exemple de chaque cas.
 *
 * - RFL2 (avril 2026) : clôturée, environ 80 inscriptions validées et ses dons.
 * - RFL3 (avril 2027) : publiée, plus de 150 inscriptions dans tous les statuts ; tarifs
 *   early puis plein (10 km), jeune et adulte (5 km), enfant et adulte (marche) ; course
 *   enfants complète (quota et plage de dossards pleins) ; mineurs avec questionnaires de
 *   santé (dont des « oui » et des vérifiés) ; dons anonymes ou avec reçu, dons seuls ;
 *   paiements échoués puis réussis, en attente, abandonnés ; remboursements ; inscriptions
 *   gratuites ; nouvelle version du règlement.
 * - Admins des trois niveaux : lecture@, gestion@ et admin@runforlorene.fr (mot de passe « password »).
 *
 * Tirages aléatoires à graine fixe : mêmes données à chaque migrate:fresh --seed.
 */
class DemoSeeder extends Seeder
{
    private \Faker\Generator $faker;

    private Consentements $consentements;

    private Questionnaires $questionnaires;

    private User $admin;

    /** @var array<int, int> prochain dossard libre, par course */
    private array $dossards = [];

    private int $numero = 0;

    /** Part des dossiers laissés incomplets (en %) : aucun pour une édition terminée. */
    private int $tauxIncomplets = 8;

    private ?string $motDePasse = null;

    public function run(): void
    {
        // Une seule transaction : des milliers de petites écritures, bien plus rapides ainsi.
        DB::transaction(fn () => $this->remplir());
    }

    private function remplir(): void
    {
        $this->faker = \Faker\Factory::create('fr_FR');
        $this->faker->seed(2027);
        mt_srand(2027);
        $this->consentements = app(Consentements::class);
        $this->questionnaires = app(Questionnaires::class);

        $this->admins();

        $rfl3 = Evenement::where('nom', 'Run For Lorène 3')->firstOrFail();
        $this->tarifsRfl3($rfl3);
        $rfl2 = $this->rfl2();

        $this->tauxIncomplets = 0;
        $this->peupler($rfl2, familles: 45, remplissage: 'historique');
        $this->tauxIncomplets = 8;
        $this->peupler($rfl3, familles: 80, remplissage: 'complet');
        $this->casParticuliers($rfl3);
        $this->courseEnfantsComplete($rfl3);
        $this->donsSeuls();

        // Nouvelle version du règlement publiée en cours de saison : les consentements déjà
        // donnés restent liés à la version 1.
        TexteLegal::create([
            'type_texte' => 'reglement',
            'version' => 2,
            'date_publication' => today(),
            'fichier' => TexteLegal::chemin($rfl3, 'reglement', 2),
            'id_evenement' => $rfl3->id_evenement,
        ]);
    }

    /** Un admin par niveau, en plus du super admin du DatabaseSeeder. */
    private function admins(): void
    {
        $this->admin = User::where('email', 'admin@runforlorene.fr')->firstOrFail();

        foreach ([['lecture', 'Léa', 'Lecture', 1], ['gestion', 'Gaël', 'Gestion', 2]] as [$email, $prenom, $nom, $niveau]) {
            User::create([
                'email' => "{$email}@runforlorene.fr",
                'prenom' => $prenom,
                'nom' => $nom,
                'mot_de_passe' => Hash::make('password'),
                'email_verifie_le' => now(),
            ])->admin()->create(['niveau_acces' => $niveau]);
        }
    }

    /** RFL3 : tarifs variés, course enfants à 20 places et 20 dossards (elle sera complète). */
    private function tarifsRfl3(Evenement $rfl3): void
    {
        $courses = $rfl3->courses()->get()->keyBy('nom');
        Tarif::whereIn('id_course', $courses->pluck('id_course'))->delete();

        $grilles = [
            'Course 10 km' => [['Early', 20, '2026-09-01', '2026-12-31', null, null], ['Plein tarif', 25, '2027-01-01', '2027-03-30', null, null]],
            'Course 5 km' => [['Jeune (12-17 ans)', 12, '2026-09-01', '2027-03-30', null, 17], ['Adulte', 18, '2026-09-01', '2027-03-30', 18, null]],
            'Marche 5 km' => [['Enfant (moins de 12 ans)', 6, '2026-09-01', '2027-03-30', null, 11], ['Adulte', 12, '2026-09-01', '2027-03-30', 12, null]],
            'Course enfants' => [['Tarif unique', 5, '2026-09-01', '2027-03-30', null, null]],
        ];

        foreach ($grilles as $nom => $tarifs) {
            foreach ($tarifs as [$libelle, $montant, $du, $au, $min, $max]) {
                Tarif::create(['libelle' => $libelle, 'montant' => $montant, 'valide_du' => $du, 'valide_au' => $au, 'age_min' => $min, 'age_max' => $max, 'id_course' => $courses[$nom]->id_course]);
            }
        }

        $courses['Course enfants']->update(['quota' => 20, 'dossard_debut' => 4000, 'dossard_fin' => 4019]);
    }

    /** RFL2 : l'édition 2026, terminée et clôturée, avec ses textes et ses tarifs. */
    private function rfl2(): Evenement
    {
        $rfl2 = Evenement::create([
            'nom' => 'Run For Lorène 2',
            'date_evenement' => '2026-04-04',
            'ouverture' => '2025-09-01 00:00:00',
            'fermeture' => '2026-04-01 23:59:59',
            'statut' => Evenement::CLOTURE,
        ]);

        foreach ([
            ['Course 10 km', 10, '10:00:00', 16, null, 120, 1000, 1999, 22],
            ['Course 5 km', 5, '10:30:00', 12, null, 100, 2000, 2999, 15],
            ['Marche 5 km', 5, '11:00:00', null, null, null, 3000, 3999, 10],
        ] as [$nom, $distance, $heure, $min, $max, $quota, $debut, $fin, $prix]) {
            $course = Course::create(['nom' => $nom, 'distance' => $distance, 'heure_depart' => $heure, 'age_min' => $min, 'age_max' => $max, 'quota' => $quota, 'dossard_debut' => $debut, 'dossard_fin' => $fin, 'id_evenement' => $rfl2->id_evenement]);
            Tarif::create(['libelle' => 'Plein tarif', 'montant' => $prix, 'valide_du' => '2025-09-01', 'valide_au' => '2026-04-01', 'age_min' => $min, 'age_max' => $max, 'id_course' => $course->id_course]);
        }

        foreach (['reglement', 'decharge', 'rgpd', 'droit_image'] as $type) {
            TexteLegal::create(['type_texte' => $type, 'version' => 1, 'date_publication' => '2025-09-01', 'fichier' => TexteLegal::chemin($rfl2, $type, 1), 'id_evenement' => $rfl2->id_evenement]);
        }

        return $rfl2;
    }

    /**
     * Familles inscrites à un événement : un titulaire, parfois des enfants ou des proches,
     * une commande par famille, des statuts tirés selon le remplissage voulu.
     */
    private function peupler(Evenement $evenement, int $familles, string $remplissage): void
    {
        $courses = $evenement->courses()->with('tarifs')->get()->keyBy('nom');
        $debut = $evenement->ouverture->copy();
        $fin = $evenement->fermeture->lt(now()) ? $evenement->fermeture->copy() : now()->subDay();

        for ($f = 0; $f < $familles; $f++) {
            [$titulaire, $membres] = $this->famille($evenement);
            $date = Carbon::createFromTimestamp(mt_rand($debut->getTimestamp(), $fin->getTimestamp()));

            // Statut de la commande : RFL2 est payée en entier ; RFL3 a de tout.
            $tirage = mt_rand(1, 100);
            $statut = $remplissage === 'historique' ? Commande::PAYEE : match (true) {
                $tirage <= 82 => Commande::PAYEE,
                $tirage <= 90 => Commande::EN_COURS,
                $tirage <= 95 => Commande::ABANDONNEE,
                default => Commande::PAYEE,
            };

            $lignes = [];
            foreach ($membres as $participant) {
                $course = $this->courseFor($participant, $evenement, $courses);
                if ($course) {
                    $lignes[] = [$participant, $course];
                }
            }
            if (! $lignes) {
                continue;
            }

            $this->commande($evenement, $titulaire, $lignes, $statut, $date, don: mt_rand(1, 100) <= 30);
        }
    }

    /**
     * Titulaire (compte avec email) et membres inscrits : lui seul (60 %), avec un ou deux
     * enfants (30 %), ou seulement ses enfants (10 %).
     *
     * @return array{0: User, 1: list<Participant>}
     */
    private function famille(Evenement $evenement): array
    {
        $nom = $this->faker->lastName();
        $titulaire = $this->compte($this->faker->firstName(), $nom, $evenement->date_evenement->copy()->subYears(mt_rand(19, 62))->subDays(mt_rand(0, 364)));

        $tirage = mt_rand(1, 100);
        $enfants = $tirage <= 60 ? 0 : mt_rand(1, 2);
        $membres = $tirage > 90 ? [] : [$titulaire->participant];

        for ($e = 0; $e < max($enfants, $tirage > 90 ? 1 : 0); $e++) {
            $age = mt_rand(6, 17);
            $enfant = User::create(['prenom' => $this->faker->firstName(), 'nom' => $nom]);
            $membres[] = Participant::create([
                'id_user' => $enfant->id_user,
                'date_naissance' => $evenement->date_evenement->copy()->subYears($age)->subDays(mt_rand(1, 364)),
                'sexe' => $this->faker->randomElement(['femme', 'homme']),
                'id_responsable' => $titulaire->id_user,
            ]);
        }

        return [$titulaire, $membres];
    }

    private function compte(string $prenom, string $nom, Carbon $naissance): User
    {
        $this->numero++;
        $user = User::create([
            'email' => Str::slug("{$prenom}.{$nom}", '.')."{$this->numero}@exemple.fr",
            'prenom' => $prenom,
            'nom' => $nom,
            'telephone' => '06'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            'mot_de_passe' => $this->motDePasse ??= Hash::make('password'),
            'email_verifie_le' => now(),
        ]);
        $user->participant()->create([
            'date_naissance' => $naissance,
            'sexe' => $this->faker->randomElement(['femme', 'homme']),
            'adresse' => $this->faker->streetAddress(),
            'code_postal' => '44'.str_pad((string) mt_rand(0, 999), 3, '0', STR_PAD_LEFT),
            'ville' => $this->faker->randomElement(['Nantes', 'Rezé', 'Saint-Herblain', 'Orvault', 'Vertou', 'Carquefou']),
        ]);

        return $user->load('participant');
    }

    /** Course selon l'âge le jour J ; la course enfants s'arrête à son quota. */
    private function courseFor(Participant $participant, Evenement $evenement, $courses): ?Course
    {
        $age = $participant->ageAu($evenement->date_evenement);
        $enfants = $courses->get('Course enfants');

        $possibles = match (true) {
            $age <= 11 => $enfants && Inscription::whereIn('id_tarif', $enfants->tarifs->pluck('id_tarif'))->where('statut', '!=', Inscription::BROUILLON)->count() < 20
                ? ['Course enfants', 'Course enfants', 'Marche 5 km'] : ['Marche 5 km'],
            $age <= 15 => ['Course 5 km', 'Marche 5 km'],
            default => ['Course 10 km', 'Course 10 km', 'Course 5 km', 'Course 5 km', 'Marche 5 km'],
        };

        return $courses->get($possibles[array_rand($possibles)]);
    }

    /**
     * Commande d'une famille : inscriptions, documents, paiement selon le statut, don éventuel.
     *
     * @param  list<array{0: Participant, 1: Course}>  $lignes
     */
    private function commande(Evenement $evenement, User $titulaire, array $lignes, string $statut, Carbon $date, bool $don = false, int $tentativesEchouees = 0): Commande
    {
        $abandonnee = $statut === Commande::ABANDONNEE;
        $gratuite = $statut === Commande::GRATUITE;

        $commande = Commande::create(['date_commande' => $date, 'montant_total' => 0, 'statut' => $statut, 'id_user' => $titulaire->id_user]);
        $commande->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        $total = 0;
        $inscriptions = [];

        foreach ($lignes as [$participant, $course]) {
            $tarif = $course->tarifPour($participant->ageAu($evenement->date_evenement), $date) ?? $course->tarifs->first();
            $prix = $gratuite ? 0 : (float) $tarif->montant;
            $total += $prix;

            $inscription = Inscription::create([
                'id_participant' => $participant->id_user,
                'id_tarif' => $tarif->id_tarif,
                'id_evenement' => $evenement->id_evenement,
                'id_commande' => $abandonnee ? null : $commande->id_commande,
                'statut' => $abandonnee ? Inscription::BROUILLON : Inscription::EN_ATTENTE_PAIEMENT,
                'prix_paye' => $abandonnee ? 0 : $prix,
                'urgence_nom' => mt_rand(0, 1) ? $this->faker->name() : null,
                'urgence_tel' => '06'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            ]);
            $inscription->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
            $inscription->setRelation('participant', $participant)->setRelation('evenement', $evenement);

            // Documents remplis dans le tunnel avant de payer (sauf brouillon abandonné en route).
            if (! $abandonnee) {
                $this->documents($inscription, $titulaire, $date);
                $this->tshirt($inscription, $evenement);
            }

            $inscriptions[] = $inscription;
        }

        if ($don && ! $abandonnee && ! $gratuite) {
            $montant = $this->faker->randomElement([10, 15, 20, 20, 30, 50, 50, 100, 150]);
            Don::create([
                'montant' => $montant,
                'anonyme' => mt_rand(1, 100) <= 25,
                'recu_fiscal_demande' => mt_rand(1, 100) <= 40,
                'id_commande' => $commande->id_commande,
            ]);
            $total += $montant;
        }

        $commande->forceFill(['montant_total' => $total])->save();

        match ($statut) {
            Commande::PAYEE => $this->payer($commande, $inscriptions, $date, $tentativesEchouees),
            Commande::GRATUITE => $this->valider($inscriptions),
            // Tentative ancienne (échouée ou jamais aboutie) : elle ne réserve plus de place.
            Commande::EN_COURS => Paiement::create([
                'prestataire' => 'simulation',
                'reference' => 'sim_demo_'.Str::lower(Str::random(16)),
                'montant' => $total,
                'statut' => mt_rand(0, 1) ? Paiement::EN_ATTENTE : Paiement::ECHOUE,
                'id_commande' => $commande->id_commande,
            ])->forceFill(['created_at' => $date, 'updated_at' => $date])->save(),
            default => null,
        };

        return $commande;
    }

    /**
     * Consentements, décharge et (mineurs) questionnaire de santé. Environ 8 % des dossiers
     * restent incomplets : décharge ou santé manquante.
     */
    private function documents(Inscription $inscription, User $titulaire, Carbon $date): void
    {
        $deposant = $titulaire->id_user;
        $incomplet = mt_rand(1, 100) <= $this->tauxIncomplets;

        Carbon::setTestNow($date);
        $this->consentements->enregistrer($inscription, mt_rand(1, 100) <= 85, $deposant, $this->faker->ipv4());

        $mineur = $inscription->santeRequise();
        // Document manquant d'un dossier incomplet : la décharge, ou pour un mineur parfois la santé.
        $manque = $incomplet ? ($mineur && mt_rand(0, 1) ? 'sante' : 'decharge') : null;

        if ($manque !== 'decharge') {
            $this->questionnaires->remplirDecharge($inscription, array_fill_keys($this->questionnaires->questions('decharge')->pluck('id_question')->all(), true), $deposant, $this->faker->ipv4());
        }

        if ($mineur && $manque !== 'sante') {
            // Environ un questionnaire sur trois avec au moins un « oui ».
            $reponses = $this->questionnaires->questions('sante')->mapWithKeys(fn ($q) => [$q->id_question => mt_rand(1, 100) <= 2 ? 'oui' : 'non'])->all();
            $sante = $this->questionnaires->enregistrer($inscription, 'sante', $reponses, true, $deposant);

            // Une partie des questionnaires déjà vérifiés par un admin.
            if (mt_rand(1, 100) <= 35) {
                $sante->document->forceFill(['statut' => 'verifie', 'id_validateur' => $this->admin->id_user, 'date_validation' => $date->copy()->addDays(mt_rand(1, 10))])->save();
            }
        }
        Carbon::setTestNow();
    }

    /** Un t-shirt pour environ 40 % des inscrits (options masquées tant que OPTIONS_ACTIVES=false). */
    private function tshirt(Inscription $inscription, Evenement $evenement): void
    {
        $tailles = Declinaison::whereHas('optionEvenement', fn ($q) => $q->where('id_evenement', $evenement->id_evenement))->get();

        if ($tailles->isNotEmpty() && mt_rand(1, 100) <= 40) {
            InscriptionDeclinaison::create(['id_inscription' => $inscription->id_inscription, 'id_declinaison' => $tailles->random()->id_declinaison, 'quantite' => 1, 'prix_unitaire' => 0]);
        }
    }

    /** Paiement Stripe réussi (après d'éventuels échecs), puis validation et dossards. */
    private function payer(Commande $commande, array $inscriptions, Carbon $date, int $tentativesEchouees): void
    {
        $total = (float) $commande->montant_total;

        for ($t = 0; $t < $tentativesEchouees; $t++) {
            Paiement::create(['prestataire' => 'stripe', 'reference' => 'cs_test_demo_'.Str::lower(Str::random(20)), 'montant' => $total, 'statut' => Paiement::ECHOUE, 'id_commande' => $commande->id_commande]);
        }

        if ($total > 0) {
            Paiement::create([
                'prestataire' => 'stripe',
                'reference' => 'cs_test_demo_'.Str::lower(Str::random(20)),
                'montant' => $total,
                'frais' => round($total * 0.015 + 0.25, 2),
                'statut' => Paiement::REUSSI,
                'date_paiement' => $date->copy()->addMinutes(mt_rand(1, 20)),
                'id_commande' => $commande->id_commande,
            ]);
        }

        $this->valider($inscriptions);
    }

    /** Validée avec dossard si le dossier est complet, sinon dossier incomplet. */
    private function valider(array $inscriptions): void
    {
        foreach ($inscriptions as $inscription) {
            $complet = $inscription->documentsManquants() === [];
            $inscription->forceFill([
                'statut' => $complet ? Inscription::VALIDEE : Inscription::DOSSIER_INCOMPLET,
                'dossard' => $complet ? $this->dossard($inscription->tarif->course) : null,
            ])->save();
        }
    }

    private function dossard(Course $course): ?int
    {
        if ($course->dossard_debut === null) {
            return null;
        }

        $this->dossards[$course->id_course] ??= $course->dossard_debut;
        $numero = $this->dossards[$course->id_course]++;

        return $numero <= $course->dossard_fin ? $numero : null;
    }

    /**
     * Course enfants complète : on ajoute des enfants jusqu'au quota (20), puis la plage de
     * dossards s'arrête au dernier numéro attribué. Le tableau de bord affiche alors l'alerte
     * « plage pleine » et la page publique le bouton grisé.
     */
    private function courseEnfantsComplete(Evenement $rfl3): void
    {
        $course = $rfl3->courses()->where('nom', 'Course enfants')->with('tarifs')->firstOrFail();
        $taux = $this->tauxIncomplets;
        $this->tauxIncomplets = 0;

        while ($course->placesRestantes() > 0) {
            $parent = $this->compte($this->faker->firstName(), $nom = $this->faker->lastName(), $rfl3->date_evenement->copy()->subYears(mt_rand(30, 45)));
            $enfant = User::create(['prenom' => $this->faker->firstName(), 'nom' => $nom]);
            $participant = Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => $rfl3->date_evenement->copy()->subYears(mt_rand(6, 11))->subDays(mt_rand(1, 300)), 'sexe' => $this->faker->randomElement(['femme', 'homme']), 'id_responsable' => $parent->id_user]);
            $this->commande($rfl3, $parent, [[$participant, $course]], Commande::PAYEE, now()->subDays(mt_rand(2, 30)));
        }

        $this->tauxIncomplets = $taux;
        $dernier = Inscription::whereIn('id_tarif', $course->tarifs->pluck('id_tarif'))->max('dossard');
        if ($dernier) {
            $course->update(['dossard_fin' => $dernier]);
        }
    }

    /** Les cas à voir au moins une fois, sur RFL3. */
    private function casParticuliers(Evenement $rfl3): void
    {
        $courses = $rfl3->courses()->with('tarifs')->get()->keyBy('nom');
        $date = fn (string $d) => Carbon::parse($d);

        // Compte de démo du DatabaseSeeder : Camille, inscrite et validée, avec un don.
        $camille = User::where('email', 'camille@exemple.fr')->firstOrFail()->load('participant');
        $this->commande($rfl3, $camille, [[$camille->participant, $courses['Course 10 km']]], Commande::PAYEE, $date('2026-09-14 20:31'), don: true);

        // Paiement refusé deux fois, puis réussi.
        [$t, $m] = $this->famille($rfl3);
        $this->commande($rfl3, $t, [[$t->participant, $courses['Course 5 km']]], Commande::PAYEE, $date('2026-10-02 12:10'), tentativesEchouees: 2);

        // Paiement en cours à l'instant : place réservée 30 minutes.
        [$t] = $this->famille($rfl3);
        $commande = $this->commande($rfl3, $t, [[$t->participant, $courses['Course 10 km']]], Commande::EN_COURS, now()->subMinutes(5));
        $commande->paiements()->update(['statut' => Paiement::EN_ATTENTE, 'created_at' => now()->subMinutes(5)]);

        // Inscriptions gratuites (partenaires), dont une créée par un admin et tracée.
        foreach (['Partenaire école', 'Bénévole de l\'association'] as $motif) {
            [$t] = $this->famille($rfl3);
            $commande = $this->commande($rfl3, $t, [[$t->participant, $courses['Marche 5 km']]], Commande::GRATUITE, $date('2026-10-05 09:00'));
            JournalAdmin::create(['id_admin' => $this->admin->id_user, 'action' => 'inscription.gratuite', 'cible_type' => 'inscription', 'cible_id' => $commande->inscriptions()->value('id_inscription'), 'details' => ['participant' => $t->name, 'course' => 'Marche 5 km', 'motif' => $motif]]);
        }

        // Inscription annulée par un admin et remboursée en totalité.
        [$t] = $this->famille($rfl3);
        $commande = $this->commande($rfl3, $t, [[$t->participant, $courses['Course 10 km']]], Commande::PAYEE, $date('2026-09-20 18:00'));
        $inscription = $commande->inscriptions()->first();
        $inscription->forceFill(['statut' => Inscription::ANNULEE, 'dossard' => null])->save();
        $paiement = $commande->paiements()->where('statut', Paiement::REUSSI)->first();
        Remboursement::create(['reference' => 're_demo_'.Str::lower(Str::random(16)), 'montant' => $paiement->montant, 'motif' => 'Blessure, ne pourra pas courir', 'date_remboursement' => $date('2026-10-01 10:00'), 'id_paiement' => $paiement->id_paiement, 'id_admin' => $this->admin->id_user]);
        JournalAdmin::create(['id_admin' => $this->admin->id_user, 'action' => 'inscription.annulation', 'cible_type' => 'inscription', 'cible_id' => $inscription->id_inscription, 'details' => ['statut précédent' => 'Validée']]);
        JournalAdmin::create(['id_admin' => $this->admin->id_user, 'action' => 'paiement.remboursement', 'cible_type' => 'commande', 'cible_id' => $commande->id_commande, 'details' => ['montant' => (string) $paiement->montant, 'motif' => 'Blessure, ne pourra pas courir']]);

        // Remboursement partiel d'un don trop élevé (erreur de saisie).
        [$t] = $this->famille($rfl3);
        $commande = $this->commande($rfl3, $t, [[$t->participant, $courses['Course 5 km']]], Commande::PAYEE, $date('2026-09-25 21:45'));
        Don::create(['montant' => 500, 'recu_fiscal_demande' => true, 'id_commande' => $commande->id_commande]);
        $commande->forceFill(['montant_total' => (float) $commande->montant_total + 500])->save();
        $paiement = $commande->paiements()->where('statut', Paiement::REUSSI)->first();
        $paiement->forceFill(['montant' => $commande->montant_total, 'frais' => round($commande->montant_total * 0.015 + 0.25, 2)])->save();
        Remboursement::create(['reference' => 're_demo_'.Str::lower(Str::random(16)), 'montant' => 450, 'motif' => 'Don de 50 € saisi 500 €', 'date_remboursement' => $date('2026-09-26 09:30'), 'id_paiement' => $paiement->id_paiement, 'id_admin' => $this->admin->id_user]);

        // Dossard changé à la main.
        $validee = Inscription::where('id_evenement', $rfl3->id_evenement)->where('statut', Inscription::VALIDEE)->whereIn('id_tarif', $courses['Course 10 km']->tarifs->pluck('id_tarif'))->first();
        if ($validee) {
            $avant = $validee->dossard;
            $validee->forceFill(['dossard' => 1500])->save();
            JournalAdmin::create(['id_admin' => $this->admin->id_user, 'action' => 'inscription.dossard', 'cible_type' => 'inscription', 'cible_id' => $validee->id_inscription, 'details' => ['avant' => $avant, 'après' => 1500]]);
        }
    }

    /** Dons faits sans inscription (bouton « Faire un don » de la page publique), sur les deux éditions. */
    private function donsSeuls(): void
    {
        foreach (['2025-12-10', '2026-02-14', '2026-03-28', '2026-09-30', '2026-10-08', '2026-10-12'] as $i => $jour) {
            $donateur = $this->compte($this->faker->firstName(), $this->faker->lastName(), now()->subYears(mt_rand(25, 70)));
            $montant = [20, 50, 100, 30, 250, 15][$i];
            $commande = Commande::create(['date_commande' => $jour.' 19:00', 'montant_total' => $montant, 'statut' => Commande::PAYEE, 'id_user' => $donateur->id_user]);
            Don::create(['montant' => $montant, 'anonyme' => $i === 2, 'recu_fiscal_demande' => $i % 2 === 0, 'id_commande' => $commande->id_commande]);
            Paiement::create(['prestataire' => 'stripe', 'reference' => 'cs_test_demo_'.Str::lower(Str::random(20)), 'montant' => $montant, 'frais' => round($montant * 0.015 + 0.25, 2), 'statut' => Paiement::REUSSI, 'date_paiement' => $jour.' 19:05', 'id_commande' => $commande->id_commande]);
        }
    }
}
