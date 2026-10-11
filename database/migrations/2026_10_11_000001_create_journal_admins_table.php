<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Traçabilité du back-office (spec) : toute action admin sensible (validation, refus,
 * remboursement, dossard modifié, inscription gratuite…) est horodatée avec son auteur.
 * Hors MLD : le spec exige la traçabilité sans lui donner de table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_admins', function (Blueprint $table) {
            $table->increments('id_journal');
            $table->unsignedInteger('id_admin')->comment('FK users : auteur de l\'action');
            $table->string('action', 50);
            $table->string('cible_type', 30)->nullable()->comment('inscription, commande, evenement…');
            $table->unsignedInteger('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->dateTime('created_at');

            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('restrict');
            $table->index(['cible_type', 'cible_id'], 'journal_admins_cible_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_admins');
    }
};
