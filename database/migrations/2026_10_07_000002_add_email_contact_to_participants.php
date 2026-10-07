<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Email de contact facultatif d'un participant, saisi à l'inscription. Ce n'est pas un
 * identifiant de connexion : un participant géré reste sans compte (users.email nul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('email_contact', 254)->nullable()->after('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('email_contact');
        });
    }
};
