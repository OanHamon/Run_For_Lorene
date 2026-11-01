<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Notes internes sur une inscription (hors MLD du spec, outil du back-office) : visibles des
 * seuls admins, jamais du participant. Pas de données de santé : elles restent dans le
 * questionnaire chiffré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes_internes', function (Blueprint $table) {
            $table->increments('id_note');
            $table->text('texte');
            $table->unsignedInteger('id_inscription');
            $table->unsignedInteger('id_admin');
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_inscription');
            $table->foreign('id_inscription')->references('id_inscription')->on('inscriptions')->onDelete('cascade');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_internes');
    }
};
