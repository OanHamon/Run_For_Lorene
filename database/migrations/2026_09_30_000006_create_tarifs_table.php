<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifs', function (Blueprint $table) {
            $table->smallIncrements('id_tarif');
            $table->string('libelle', 100);
            $table->decimal('montant', 8, 2);
            $table->date('valide_du');
            $table->date('valide_au');
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->unsignedSmallInteger('id_course');

            $table->foreign('id_course')->references('id_course')->on('courses')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE tarifs ADD CONSTRAINT tarifs_montant_check CHECK (montant >= 0)');
        DB::statement('ALTER TABLE tarifs ADD CONSTRAINT tarifs_periode_check CHECK (valide_au >= valide_du)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs');
    }
};
