<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_santes', function (Blueprint $table) {
            $table->smallIncrements('id_question');
            $table->string('libelle', 255);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->unsignedSmallInteger('version_questionnaire');
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_santes');
    }
};
