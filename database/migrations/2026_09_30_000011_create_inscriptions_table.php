<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->increments('id_inscription');
            $table->decimal('prix_paye', 8, 2)->default(0)->comment("Prix historisé au moment de l'achat");
            $table->string('statut', 30)->default('brouillon');
            $table->unsignedSmallInteger('dossard')->nullable()->comment('Rempli à la validation, dans la plage de la course');
            $table->string('urgence_nom', 150)->nullable();
            $table->string('urgence_tel', 20)->nullable();
            $table->date('date_retrait')->nullable()
                ->comment('Signification exacte non détaillée dans le texte du spec — à confirmer');
            $table->unsignedInteger('id_participant')->comment('FK vers users.id_user (partagée avec la table participants)');
            $table->unsignedSmallInteger('id_tarif');
            $table->unsignedInteger('id_commande')->nullable()
                ->comment('NULL tant que statut = brouillon (commande créée au récapitulatif)');
            $table->unsignedSmallInteger('id_evenement')->comment('Dénormalisation assumée (se déduit du tarif)');
            $table->timestamps();

            $table->foreign('id_participant')->references('id_user')->on('users')->onDelete('restrict');
            $table->foreign('id_tarif')->references('id_tarif')->on('tarifs')->onDelete('restrict');
            $table->foreign('id_commande')->references('id_commande')->on('commandes')->onDelete('restrict');
            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');

            $table->unique(['id_evenement', 'id_participant'], 'inscriptions_evenement_participant_unique');
            $table->unique(['id_evenement', 'dossard'], 'inscriptions_evenement_dossard_unique');
            $table->index('statut', 'inscriptions_statut_index');
        });

        DB::statement("ALTER TABLE inscriptions ADD CONSTRAINT inscriptions_statut_check CHECK (statut IN ('brouillon', 'en_attente_paiement', 'validee', 'dossier_incomplet', 'annulee'))");
        DB::statement('ALTER TABLE inscriptions ADD CONSTRAINT inscriptions_prix_paye_check CHECK (prix_paye >= 0)');
        // Le MCD marque id_commande comme obligatoire (cardinalité 11), mais le texte du spec dit que la
        // commande n'est créée qu'au récapitulatif alors que "Brouillon" est le tout premier statut.
        // Ce CHECK encode la règle réelle : commande obligatoire dès qu'on quitte le brouillon.
        DB::statement("ALTER TABLE inscriptions ADD CONSTRAINT inscriptions_commande_requise_check CHECK (statut = 'brouillon' OR id_commande IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
