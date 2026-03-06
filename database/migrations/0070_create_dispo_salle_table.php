<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dispo_salle', function (Blueprint $table) {
            $table->id();
            $table->string('fk_salle', 4);
            $table->foreign('fk_salle')->references('numero')->on('salles')->onDelete('cascade');
            $table->enum('jour', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Médians', 'Finaux']);
            $table->time('debut');
            $table->time('fin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispo_salle');
    }
};
