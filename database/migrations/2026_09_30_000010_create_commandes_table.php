<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->increments('id_commande');
            $table->dateTime('date_commande');
            $table->decimal('montant_total', 8, 2)
                ->comment('Calculé une seule fois à la création, figé ensuite (montant envoyé à Stripe)');
            $table->string('statut', 30)->default('en_cours')
                ->comment('Valeur connue du spec : "gratuite" (inscription manuelle admin) ; reste du domaine à confirmer');
            $table->unsignedInteger('id_user')->comment('Le donateur/payeur : celui qui passe la commande');
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE commandes ADD CONSTRAINT commandes_montant_total_check CHECK (montant_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
