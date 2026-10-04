<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('texte_legals', function (Blueprint $table) {
            $table->smallIncrements('id_texte');
            $table->string('type_texte', 30);
            $table->unsignedSmallInteger('version');
            $table->date('date_publication');
            $table->string('fichier', 255)->comment("Chemin sur le disque privé Laravel (jamais d'URL publique)");
            $table->unsignedSmallInteger('id_evenement');

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');
            $table->unique(['id_evenement', 'type_texte', 'version'], 'texte_legals_evenement_type_version_unique');
        });

        DB::statement("ALTER TABLE texte_legals ADD CONSTRAINT texte_legals_type_texte_check CHECK (type_texte IN ('reglement', 'decharge', 'autorisation_parentale', 'rgpd', 'droit_image'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('texte_legals');
    }
};
