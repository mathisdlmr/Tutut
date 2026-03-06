<?php

use App\Enums\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = array_map(fn (Roles $role) => $role->value, Roles::cases());

        Schema::create('users', function (Blueprint $table) use ($roles) {
            $table->id('id');
            $table->string('email', 200);
            $table->string('firstName', 100)->nullable();
            $table->string('lastName', 100)->nullable();
            $table->enum('role', $roles)->default('tutee');
            $table->json('languages')->nullable();
            $table->timestamp('rgpd_accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
