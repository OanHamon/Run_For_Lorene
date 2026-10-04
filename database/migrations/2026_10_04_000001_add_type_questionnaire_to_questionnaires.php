<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La décharge devient un questionnaire oui/non, comme la santé : mêmes tables, distinguées
 * par le type. Les réponses restent chiffrées et rattachées au document de l'inscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_santes', function (Blueprint $table) {
            $table->enum('type_questionnaire', ['sante', 'decharge'])->default('sante')->after('id_question');
            $table->index(['type_questionnaire', 'version_questionnaire', 'active'], 'question_santes_type_version_index');
        });

        Schema::table('questionnaire_santes', function (Blueprint $table) {
            $table->enum('type_questionnaire', ['sante', 'decharge'])->default('sante')->after('id_document');
        });
    }

    public function down(): void
    {
        Schema::table('question_santes', function (Blueprint $table) {
            $table->dropIndex('question_santes_type_version_index');
            $table->dropColumn('type_questionnaire');
        });

        Schema::table('questionnaire_santes', function (Blueprint $table) {
            $table->dropColumn('type_questionnaire');
        });
    }
};
