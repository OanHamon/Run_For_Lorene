<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declinaisons', function (Blueprint $table) {
            $table->smallIncrements('id_declinaison');
            $table->string('libelle', 50)->comment('Ex : taille XS/S/M/L/XL');
            $table->unsignedSmallInteger('stock')->nullable()->comment('NULL = illimité ; 0 = épuisé (taille grisée)');
            $table->unsignedSmallInteger('id_option');

            $table->foreign('id_option')->references('id_option')->on('option_evenements')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declinaisons');
    }
};
