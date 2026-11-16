<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Candidatures bénévoles (brief complémentaire du 8 novembre 2026), formulaire natif.
 *
 * Remplace la table `benevoles` du spec V1 (entité gardée sans écran, jamais utilisée) :
 * une candidature est liée à un événement, un bénévole peut revenir chaque année.
 * Contacts d'urgence, responsable légal et allergies sont chiffrés (cast `encrypted`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('benevoles');

        Schema::create('candidature_benevoles', function (Blueprint $table) {
            $table->increments('id_candidature');
            $table->unsignedInteger('id_user');
            $table->unsignedSmallInteger('id_evenement');
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedTinyInteger('etape')->default(1)->comment('Dernier panneau atteint (reprise du brouillon)');

            // Toi
            $table->string('telephone', 10)->nullable();
            $table->string('age', 10)->nullable()->comment('moins_16 | 16_17 | majeur, le jour de l\'événement');
            $table->text('responsable_nom')->nullable();
            $table->text('responsable_tel')->nullable();
            $table->text('urgence_nom')->nullable();
            $table->text('urgence_tel')->nullable();
            $table->text('urgence_lien')->nullable();

            // Missions
            $table->json('missions_preferees')->nullable()->comment('Codes, par ordre de priorité');
            $table->json('missions_impossibles')->nullable();
            $table->json('competences')->nullable()->comment('Communication : photo, vidéo, réseaux, médias');
            $table->text('experience')->nullable();
            $table->text('competences_detail')->nullable();
            $table->text('commentaire')->nullable();

            // Disponibilités
            $table->json('disponibilites')->nullable()->comment('Jour J : toute la journée ou tranches ; veille, soir et lendemain');
            $table->boolean('vehicule')->nullable();
            $table->boolean('transport')->nullable();
            $table->text('transport_detail')->nullable();
            $table->string('binome', 150)->nullable();
            $table->boolean('court')->nullable();
            $table->unsignedSmallInteger('id_course')->nullable();

            // Pratique
            $table->string('taille_tshirt', 10)->nullable();
            $table->boolean('repas')->nullable();
            $table->text('allergies')->nullable();
            $table->string('soiree', 12)->nullable()->comment('oui | non | a_confirmer');

            // Photo, image, autorisation parentale
            $table->boolean('droit_image')->nullable();
            $table->dateTime('droit_image_le')->nullable();
            $table->unsignedSmallInteger('id_texte_droit_image')->nullable();
            $table->string('photo')->nullable()->comment('Chemin sur le disque privé');
            $table->dateTime('photo_le')->nullable();
            $table->string('autorisation_parentale')->nullable()->comment('Chemin sur le disque privé (moins de 16 ans)');
            $table->dateTime('autorisation_le')->nullable();

            // Suivi par l'organisation
            $table->string('mission_affectee', 30)->nullable();
            $table->dateTime('envoyee_le')->nullable();
            $table->timestamps();

            $table->unique(['id_user', 'id_evenement']);
            $table->index(['id_evenement', 'statut']);
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');
            $table->foreign('id_course')->references('id_course')->on('courses')->onDelete('set null');
            $table->foreign('id_texte_droit_image')->references('id_texte')->on('texte_legals')->onDelete('set null');
        });

        Schema::create('candidature_statuts', function (Blueprint $table) {
            $table->increments('id_statut');
            $table->unsignedInteger('id_candidature');
            $table->string('statut', 20);
            $table->unsignedInteger('id_admin')->nullable()->comment('NULL : action du bénévole');
            $table->text('message')->nullable()->comment('Message ajouté à l\'email envoyé');
            $table->boolean('notifie')->default(false);
            $table->dateTime('created_at');

            $table->foreign('id_candidature')->references('id_candidature')->on('candidature_benevoles')->onDelete('cascade');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_statuts');
        Schema::dropIfExists('candidature_benevoles');

        Schema::create('benevoles', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->primary();
            $table->string('poste', 100)->nullable();
            $table->text('disponibilites')->nullable();
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }
};
