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
        Schema::create('creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor1_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('tutor2_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->boolean('tutor1_compted')->nullable(); // null = non traité, true/false sinon
            $table->boolean('tutor2_compted')->nullable();
            $table->foreignId('fk_semaine')->constrained('semaines', 'id')->onDelete('cascade');
            $table->string('fk_salle', 4);
            $table->foreign('fk_salle')->references('numero')->on('salles')->onDelete('cascade');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->string('day_and_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creneaux');
    }
};
