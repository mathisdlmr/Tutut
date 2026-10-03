<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un tutoré ne s'inscrit qu'une fois par créneau, et un tuteur n'a qu'une ligne de comptabilité par semaine.
     */
    public function up(): void
    {
        Schema::table('inscription', function (Blueprint $table) {
            $table->unique(['tutee_id', 'creneau_id']);
        });

        Schema::table('comptabilite', function (Blueprint $table) {
            $table->unique(['fk_user', 'fk_semaine']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscription', function (Blueprint $table) {
            $table->dropUnique(['tutee_id', 'creneau_id']);
        });

        Schema::table('comptabilite', function (Blueprint $table) {
            $table->dropUnique(['fk_user', 'fk_semaine']);
        });
    }
};
