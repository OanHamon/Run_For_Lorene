<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Retrait des dossards le jour J (hors MLD du spec, outil du back-office) :
 * - postes_retrait : lien donné aux bénévoles, sans compte, valable sur des dates choisies,
 *   révocable ; seule l'empreinte du jeton est gardée ;
 * - remises_dossard : chaque remise (ou annulation) avec qui l'a faite, en plus de
 *   inscriptions.date_retrait (spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postes_retrait', function (Blueprint $table) {
            $table->increments('id_poste');
            $table->string('libelle', 60);
            $table->char('jeton_hash', 64)->unique();
            $table->date('valable_du');
            $table->date('valable_au');
            $table->timestamp('revoque_le')->nullable();
            $table->unsignedSmallInteger('id_evenement');
            $table->unsignedInteger('id_admin');
            $table->timestamps();

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('cascade');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('restrict');
        });

        Schema::create('remises_dossard', function (Blueprint $table) {
            $table->increments('id_remise');
            $table->string('action', 12)->comment('remise ou annulation');
            $table->string('par', 100)->comment('Prénom du bénévole, ou nom de l\'admin');
            $table->unsignedInteger('id_inscription');
            $table->unsignedInteger('id_poste')->nullable();
            $table->unsignedInteger('id_admin')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_inscription');
            $table->foreign('id_inscription')->references('id_inscription')->on('inscriptions')->onDelete('cascade');
            $table->foreign('id_poste')->references('id_poste')->on('postes_retrait')->onDelete('set null');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remises_dossard');
        Schema::dropIfExists('postes_retrait');
    }
};
