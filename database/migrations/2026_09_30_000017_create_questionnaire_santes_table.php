<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_santes', function (Blueprint $table) {
            $table->unsignedInteger('id_document')->primary();
            $table->boolean('attestation')->default(false)->comment('Attestation finale du questionnaire');

            $table->foreign('id_document')->references('id_document')->on('documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_santes');
    }
};
