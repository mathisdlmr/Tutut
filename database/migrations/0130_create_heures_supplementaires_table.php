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
        Schema::create('heures_supplementaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_user')->constrained('users')->onDelete('cascade');
            $table->foreignId('fk_semaine')->constrained('semaines')->onDelete('cascade');
            $table->float('nb_heures');
            $table->text('commentaire');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heures_supplementaires');
    }
};
