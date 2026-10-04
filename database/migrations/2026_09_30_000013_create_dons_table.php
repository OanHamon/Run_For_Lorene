<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dons', function (Blueprint $table) {
            $table->increments('id_don');
            $table->decimal('montant', 8, 2);
            $table->boolean('recu_fiscal_demande')->default(false);
            $table->boolean('anonyme')->default(false)->comment('Masque le nom dans les affichages publics');
            $table->unsignedInteger('id_commande')->unique('dons_id_commande_unique');
            $table->timestamps();

            $table->foreign('id_commande')->references('id_commande')->on('commandes')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE dons ADD CONSTRAINT dons_montant_check CHECK (montant >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dons');
    }
};
