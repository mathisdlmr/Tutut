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
        Schema::create('inscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
            $table->json('enseignements_souhaites');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscription');
    }
};
