<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Le questionnaire de santé ne dépend plus de la course mais de l'âge : il est demandé aux
 * seuls participants mineurs le jour de l'événement (Inscription::santeRequise()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('questionnaire_requis');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('questionnaire_requis')->default(false)->after('quota');
        });
    }
};
