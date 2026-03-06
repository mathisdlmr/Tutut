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
        Schema::create('semestres', function (Blueprint $table) {
            $table->string('code', 3)->primary(); // ex: "A25"
            $table->boolean('is_active')->default(false);
            $table->date('debut');
            $table->date('fin');
            $table->date('debut_medians')->nullable();
            $table->date('fin_medians')->nullable();
            $table->date('debut_finaux')->nullable();
            $table->date('fin_finaux')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semestres');
    }
};
