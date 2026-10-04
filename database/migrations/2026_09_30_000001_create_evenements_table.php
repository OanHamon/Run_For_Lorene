<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->smallIncrements('id_evenement');
            $table->string('nom', 150);
            $table->date('date_evenement');
            $table->dateTime('ouverture')->comment('Ouverture des inscriptions');
            $table->dateTime('fermeture')->comment('Fermeture des inscriptions');
            $table->string('statut', 30)->default('brouillon')
                ->comment("Domaine non défini dans le spec — à confirmer avec l'association");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
