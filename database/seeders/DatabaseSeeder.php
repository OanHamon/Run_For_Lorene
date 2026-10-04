<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Declinaison;
use App\Models\Evenement;
use App\Models\OptionEvenement;
use App\Models\Tarif;
use App\Models\TexteLegal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de développement : RFL3 avec ses 4 épreuves (mêmes données que le flow
     * statique du tunnel), un compte admin et un compte participant pour se connecter.
     */
    public function run(): void
    {
        $evenement = Evenement::create([
            'nom' => 'Run For Lorène 3',
            'date_evenement' => '2027-04-03',
            'ouverture' => '2026-09-01 00:00:00',
            'fermeture' => '2027-03-30 23:59:59',
            'statut' => 'publie',
        ]);

        $courses = [
            [
                'nom' => 'Course 10 km',
                'distance' => 10,
                'heure_depart' => '10:00:00',
                'age_min' => 16,
                'age_max' => null,
                'quota' => 150,
                'dossard_debut' => 1000,
                'dossard_fin' => 1999,
                'prix' => 25,
            ],
            [
                'nom' => 'Course 5 km',
                'distance' => 5,
                'heure_depart' => '10:30:00',
                'age_min' => 12,
                'age_max' => null,
                'quota' => 100,
                'dossard_debut' => 2000,
                'dossard_fin' => 2999,
                'prix' => 18,
            ],
            [
                'nom' => 'Marche 5 km',
                'distance' => 5,
                'heure_depart' => '11:00:00',
                'age_min' => null,
                'age_max' => null,
                'quota' => null,
                'dossard_debut' => 3000,
                'dossard_fin' => 3999,
                'prix' => 12,
            ],
            [
                'nom' => 'Course enfants',
                'distance' => null,
                'heure_depart' => '09:30:00',
                'age_min' => 6,
                'age_max' => 11,
                'quota' => 20,
                'dossard_debut' => 4000,
                'dossard_fin' => 4099,
                'prix' => 5,
            ],
        ];

        foreach ($courses as $data) {
            $course = Course::create([
                'nom' => $data['nom'],
                'distance' => $data['distance'],
                'heure_depart' => $data['heure_depart'],
                'age_min' => $data['age_min'],
                'age_max' => $data['age_max'],
                'quota' => $data['quota'],
                'questionnaire_requis' => true,
                'dossard_debut' => $data['dossard_debut'],
                'dossard_fin' => $data['dossard_fin'],
                'id_evenement' => $evenement->id_evenement,
            ]);

            Tarif::create([
                'libelle' => 'Plein tarif',
                'montant' => $data['prix'],
                'valide_du' => '2026-01-01',
                'valide_au' => '2027-04-03',
                'age_min' => $data['age_min'],
                'age_max' => $data['age_max'],
                'id_course' => $course->id_course,
            ]);
        }

        foreach (['reglement', 'decharge', 'rgpd', 'droit_image'] as $type) {
            TexteLegal::create([
                'type_texte' => $type,
                'version' => 1,
                'date_publication' => now()->toDateString(),
                'fichier' => "textes-legaux/{$type}-rfl3-v1.pdf",
                'id_evenement' => $evenement->id_evenement,
            ]);
        }

        $this->call(QuestionnairesSeeder::class);

        $tshirt = OptionEvenement::create([
            'libelle' => 'T-shirt',
            'prix' => 0,
            'id_evenement' => $evenement->id_evenement,
        ]);

        foreach (['XS', 'S', 'M', 'L', 'XL'] as $taille) {
            Declinaison::create([
                'libelle' => $taille,
                'stock' => 20,
                'id_option' => $tshirt->id_option,
            ]);
        }

        // Comptes de test (lot 1 : "un admin et un participant se connectent").
        $admin = User::create([
            'prenom' => 'Alex',
            'nom' => 'Admin',
            'email' => 'admin@runforlorene.fr',
            'mot_de_passe' => Hash::make('password'),
            'email_verifie_le' => now(),
        ]);
        $admin->admin()->create(['niveau_acces' => 3]);

        $participant = User::create([
            'prenom' => 'Camille',
            'nom' => 'Martin',
            'email' => 'camille@exemple.fr',
            'mot_de_passe' => Hash::make('password'),
            'telephone' => '0612345678',
            'email_verifie_le' => now(),
        ]);
        $participant->participant()->create([
            'sexe' => 'femme',
            'date_naissance' => '1998-05-14',
            'code_postal' => '44000',
            'ville' => 'Nantes',
            'pays' => 'France',
        ]);
    }
}
