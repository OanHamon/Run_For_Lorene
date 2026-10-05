<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Rubrique d'une question de santé (« Depuis l'année dernière », « Aujourd'hui »…), reprise du
 * Google Form : elle titre les questions et découpe le questionnaire en diapos. Vide pour la décharge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_santes', function (Blueprint $table) {
            $table->string('rubrique', 20)->nullable()->after('libelle');
        });
    }

    public function down(): void
    {
        Schema::table('question_santes', function (Blueprint $table) {
            $table->dropColumn('rubrique');
        });
    }
};
