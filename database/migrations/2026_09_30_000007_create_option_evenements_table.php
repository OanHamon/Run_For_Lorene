<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_evenements', function (Blueprint $table) {
            $table->smallIncrements('id_option');
            $table->string('libelle', 100)->comment('Ex : "T-shirt"');
            $table->decimal('prix', 8, 2);
            $table->unsignedSmallInteger('id_evenement');

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE option_evenements ADD CONSTRAINT option_evenements_prix_check CHECK (prix >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('option_evenements');
    }
};
