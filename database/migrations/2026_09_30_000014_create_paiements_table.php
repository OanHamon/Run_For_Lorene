<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->increments('id_paiement');
            $table->string('prestataire', 30)->default('stripe');
            $table->string('reference', 100)->unique()->comment('Identifiant Stripe');
            $table->decimal('montant', 8, 2);
            $table->decimal('frais', 8, 2)->nullable();
            $table->string('statut', 30);
            $table->dateTime('date_paiement')->nullable();
            $table->unsignedInteger('id_commande');
            $table->timestamps();

            $table->foreign('id_commande')->references('id_commande')->on('commandes')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_statut_check CHECK (statut IN ('en_attente', 'reussi', 'echoue'))");
        DB::statement('ALTER TABLE paiements ADD CONSTRAINT paiements_montant_check CHECK (montant >= 0)');
        DB::statement('ALTER TABLE paiements ADD CONSTRAINT paiements_frais_check CHECK (frais IS NULL OR frais >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
