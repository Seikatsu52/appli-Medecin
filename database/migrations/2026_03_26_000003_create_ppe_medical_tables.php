<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id('idPatient');
            $table->string('nomPatient', 100);
            $table->string('prenomPatient', 100);
            $table->string('ruePatient', 255)->nullable();
            $table->string('cpPatient', 50)->nullable();
            $table->string('villePatient', 100)->nullable();
            $table->string('telPatient', 50)->nullable();
            $table->string('loginPatient', 50)->unique();
            $table->string('mdpPatient', 255);
            $table->timestamps();
        });

        Schema::create('authentification', function (Blueprint $table) {
            $table->string('token', 100)->primary();
            $table->foreignId('idPatient')->constrained('patients', 'idPatient')->cascadeOnDelete();
            $table->string('ipAppareil', 100);
            $table->timestamps();
        });

        Schema::create('rdv', function (Blueprint $table) {
            $table->id('idRdv');
            $table->dateTime('dateHeureRdv');
            $table->foreignId('idPatient')->constrained('patients', 'idPatient')->cascadeOnDelete();
            $table->string('nomMedecin', 100);
            $table->string('prenomMedecin', 100);
            $table->string('idMedecin', 100);
            $table->timestamps();
            $table->unique(['dateHeureRdv', 'nomMedecin', 'prenomMedecin'], 'rdv_unique_slot_medecin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rdv');
        Schema::dropIfExists('authentification');
        Schema::dropIfExists('patients');
    }
};
