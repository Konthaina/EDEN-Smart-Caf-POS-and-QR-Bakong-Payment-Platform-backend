<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable(); // (e.g., 'order', 'promo', etc.)
            $table->string('title');
            $table->text('message');
            $table->boolean('read')->default(false);
            $table->timestamp('scheduled_at')->nullable();      // When to send (future, or null for now)
            $table->boolean('recurring')->default(false);       // Is it recurring?
            $table->string('recurring_type')->nullable();       // daily, weekly, monthly, custom
            $table->string('recurring_value')->nullable();      // e.g., 'monday'
            $table->timestamp('next_run_at')->nullable();       // Next scheduled send
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
