<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->increments('id_document');
            $table->dateTime('date_depot');
            $table->string('statut', 30)->default('en_attente')
                ->comment('Domaine non figé textuellement (implicite via motif_refus/date_validation) — à confirmer');
            $table->string('motif_refus', 255)->nullable();
            $table->dateTime('date_validation')->nullable();
            $table->unsignedInteger('id_deposant')->comment('FK users : le déposant (titulaire ou responsable)');
            $table->unsignedInteger('id_validateur')->nullable()->comment('FK users (admin) : qui a validé/refusé');
            $table->unsignedInteger('id_inscription');
            $table->softDeletes()->comment("Purge planifiée 12 mois après l'événement");
            $table->timestamps();

            $table->foreign('id_deposant')->references('id_user')->on('users')->onDelete('restrict');
            $table->foreign('id_validateur')->references('id_user')->on('users')->onDelete('set null');
            $table->foreign('id_inscription')->references('id_inscription')->on('inscriptions')->onDelete('cascade');
            $table->index('statut', 'documents_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
