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
        Schema::create('become_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_user')->unique()->constrained('users')->onDelete('cascade');
            $table->string('semester', 4);
            $table->json('UVs');
            $table->text('motivation');
            $table->enum('status', ['pending', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('become_tutor');
    }
};
