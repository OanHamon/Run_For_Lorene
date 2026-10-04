<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->primary();
            $table->unsignedTinyInteger('niveau_acces')
                ->comment('1=lecture seule, 2=gestion inscriptions/documents, 3=super admin');

            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE admins ADD CONSTRAINT admins_niveau_acces_check CHECK (niveau_acces IN (1, 2, 3))');
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
