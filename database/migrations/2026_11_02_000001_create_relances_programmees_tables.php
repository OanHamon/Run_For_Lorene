<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Relances programmées depuis l'admin (hors MLD du spec, outil du back-office) :
 * - relances_programmees : un message et sa cible, envoyés à une date et une heure, N jours
 *   avant l'événement, ou tous les N jours sur une période ; en pause ou actifs ;
 * - reglages : réglages des relances automatiques du site (documents, paiement) ;
 * - relances.id_programmee : l'envoi programmé qui a produit la relance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances_programmees', function (Blueprint $table) {
            $table->increments('id_programmee');
            $table->string('modele', 30);
            $table->string('sujet', 150);
            $table->text('message');
            $table->json('cible');
            $table->string('quand', 10)->comment('date, avant ou repetee');
            $table->dateTime('date_envoi')->nullable();
            $table->unsignedSmallInteger('jours_avant')->nullable();
            $table->unsignedSmallInteger('tous_les')->nullable()->comment('Nombre de jours entre deux envois');
            $table->date('repetee_du')->nullable();
            $table->date('repetee_au')->nullable();
            $table->time('heure')->default('10:00:00');
            $table->boolean('actif')->default(true);
            $table->timestamp('derniere_execution')->nullable();
            $table->unsignedSmallInteger('id_evenement');
            $table->unsignedInteger('id_admin');
            $table->timestamps();

            $table->foreign('id_evenement')->references('id_evenement')->on('evenements')->onDelete('cascade');
            $table->foreign('id_admin')->references('id_user')->on('users')->onDelete('restrict');
        });

        Schema::table('relances', function (Blueprint $table) {
            $table->unsignedInteger('id_programmee')->nullable()->after('id_admin');
            $table->foreign('id_programmee')->references('id_programmee')->on('relances_programmees')->onDelete('set null');
        });

        Schema::create('reglages', function (Blueprint $table) {
            $table->string('cle', 60)->primary();
            $table->json('valeur');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglages');
        Schema::table('relances', function (Blueprint $table) {
            $table->dropForeign(['id_programmee']);
            $table->dropColumn('id_programmee');
        });
        Schema::dropIfExists('relances_programmees');
    }
};
