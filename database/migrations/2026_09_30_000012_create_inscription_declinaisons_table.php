<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscription_declinaisons', function (Blueprint $table) {
            $table->unsignedInteger('id_inscription');
            $table->unsignedSmallInteger('id_declinaison');
            $table->unsignedSmallInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 8, 2)->comment("Historisé au moment de l'achat");

            $table->primary(['id_inscription', 'id_declinaison']);
            $table->foreign('id_inscription')->references('id_inscription')->on('inscriptions')->onDelete('cascade');
            $table->foreign('id_declinaison')->references('id_declinaison')->on('declinaisons')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE inscription_declinaisons ADD CONSTRAINT inscription_declinaisons_prix_unitaire_check CHECK (prix_unitaire >= 0)');
        DB::statement('ALTER TABLE inscription_declinaisons ADD CONSTRAINT inscription_declinaisons_quantite_check CHECK (quantite > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inscription_declinaisons');
    }
};
