<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Remboursements Stripe : la référence du remboursement (re_…) rend le webhook
 * charge.refunded idempotent, et id_admin trace qui l'a demandé (NULL s'il vient du
 * tableau de bord Stripe). Hors MLD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remboursements', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->unique()->after('id_remboursement')->comment('Identifiant Stripe du remboursement');
            $table->unsignedInteger('id_admin')->nullable()->after('id_paiement')->comment("FK users : admin à l'origine du remboursement");

            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('remboursements', function (Blueprint $table) {
            $table->dropForeign(['id_admin']);
            $table->dropColumn(['reference', 'id_admin']);
        });
    }
};
