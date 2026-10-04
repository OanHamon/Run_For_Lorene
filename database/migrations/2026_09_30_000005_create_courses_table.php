<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->smallIncrements('id_course');
            $table->string('nom', 100);
            $table->decimal('distance', 5, 2)->nullable()->comment('En kilomètres');
            $table->time('heure_depart');
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->unsignedSmallInteger('quota')->nullable()->comment('NULL = illimité');
            $table->boolean('questionnaire_requis')->default(false);
            $table->unsignedSmallInteger('dossard_debut')->nullable();
            $table->unsignedSmallInteger('dossard_fin')->nullable();
            $table->unsignedSmallInteger('id_evenement');
            $table->timestamps();

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_distance_check CHECK (distance IS NULL OR distance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
