<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Relances envoyées depuis l'admin (hors MLD du spec, outil du back-office) : un envoi
 * (modèle, objet, message tel qu'envoyé, cible) et ses destinataires, pour l'historique de
 * chaque inscription et l'avertissement « déjà relancé il y a moins de 48 h ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances', function (Blueprint $table) {
            $table->increments('id_relance');
            $table->string('modele', 30);
            $table->string('sujet', 150);
            $table->text('message');
            $table->json('cible');
            $table->unsignedSmallInteger('nb_destinataires');
            $table->unsignedSmallInteger('id_evenement');
            $table->unsignedInteger('id_admin');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('restrict');
        });

        Schema::create('relance_destinataires', function (Blueprint $table) {
            $table->unsignedInteger('id_relance');
            $table->unsignedInteger('id_inscription');
            $table->unsignedInteger('id_user')->comment('Titulaire qui a reçu l\'email');
            $table->string('email', 254);

            $table->primary(['id_relance', 'id_inscription']);
            $table->index('id_inscription');
            $table->foreign('id_relance')->references('id_relance')->on('relances')->onDelete('cascade');
            $table->foreign('id_inscription')->references('id_inscription')->on('inscriptions')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relance_destinataires');
        Schema::dropIfExists('relances');
    }
};
