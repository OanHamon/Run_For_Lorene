<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponse_santes', function (Blueprint $table) {
            $table->unsignedInteger('id_document');
            $table->unsignedSmallInteger('id_question');
            $table->text('reponse')->comment("Chiffré côté application (cast Laravel 'encrypted')");

            $table->primary(['id_document', 'id_question']);
            $table->foreign('id_document')->references('id_document')->on('questionnaire_santes')->onDelete('cascade');
            $table->foreign('id_question')->references('id_question')->on('question_santes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponse_santes');
    }
};
