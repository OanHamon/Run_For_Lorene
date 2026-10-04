<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->primary();
            $table->string('sexe', 10)->nullable()
                ->comment('Domaine non figé textuellement (maquette : femme/homme/autre) — à confirmer');
            $table->date('date_naissance')
                ->comment("Âge calculé à la date de l'événement, pas à la date d'inscription");
            $table->string('adresse', 255)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->default('France');
            $table->unsignedInteger('id_responsable')->nullable()
                ->comment('Titulaire du compte responsable, pour un participant géré (enfant)');

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
            $table->foreign('id_responsable')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
