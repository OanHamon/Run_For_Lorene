<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Date de la purge RGPD des documents d'un événement (décharges, consentements, réponses de
 * santé), 12 mois après l'événement. Nulle tant que la purge n'a pas eu lieu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->timestamp('documents_purges_le')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->dropColumn('documents_purges_le');
        });
    }
};
