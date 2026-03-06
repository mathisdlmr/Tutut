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
        Schema::create('comptabilite', function (Blueprint $table) {
            $table->id();
            $table->float('nb_heures');
            $table->string('commentaire_bve', 255)->nullable();
            $table->boolean('saisie')->default(false);
            $table->foreignId('fk_user')->constrained('users', 'id');
            $table->foreignId('fk_semaine')->constrained('semaines', 'id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comptabilite');
    }
};
