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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('order_code')->unique();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('discount_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
