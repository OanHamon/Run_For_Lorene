<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Couleur de la course (réunion du 01/11/2026) : l'élément distinctif de chaque course au
 * scan du jour J, sur le billet et dans les listes. Choix libre de l'admin (bracelet, dossard,
 * balisage), donc hors palette de la charte. Code hexadécimal « #RRGGBB ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->char('couleur', 7)->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('couleur');
        });
    }
};
