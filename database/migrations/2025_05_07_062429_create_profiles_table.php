<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Profile fields
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();        // 'male', 'female', 'other'
            $table->date('birthdate')->nullable();
            $table->string('address')->nullable();
            $table->string('avatar')->nullable();        // image file path

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
