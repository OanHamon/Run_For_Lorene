<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benevoles', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->primary();
            $table->string('poste', 100)->nullable();
            $table->text('disponibilites')->nullable()
                ->comment('Domaine non détaillé dans le spec V1 (entité gardée sans écran ; V2 : table dédiée)');

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benevoles');
    }
};
