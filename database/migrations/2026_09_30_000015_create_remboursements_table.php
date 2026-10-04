<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remboursements', function (Blueprint $table) {
            $table->increments('id_remboursement');
            $table->decimal('montant', 8, 2);
            $table->string('motif', 255)->nullable();
            $table->dateTime('date_remboursement');
            $table->unsignedInteger('id_paiement');

            $table->foreign('id_paiement')->references('id_paiement')->on('paiements')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE remboursements ADD CONSTRAINT remboursements_montant_check CHECK (montant >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('remboursements');
    }
};
