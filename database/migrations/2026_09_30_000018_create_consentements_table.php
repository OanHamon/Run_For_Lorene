<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentements', function (Blueprint $table) {
            $table->unsignedInteger('id_document')->primary();
            $table->boolean('accepte');
            $table->string('ip', 45)->comment('IPv4 ou IPv6');
            $table->unsignedSmallInteger('id_texte');

            $table->foreign('id_document')->references('id_document')->on('documents')->onDelete('cascade');
            $table->foreign('id_texte')->references('id_texte')->on('texte_legals')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentements');
    }
};
