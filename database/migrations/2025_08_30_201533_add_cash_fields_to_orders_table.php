<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // What the customer handed over
            $table->enum('tendered_currency', ['USD','KHR'])->nullable()->after('payment_method');
            $table->decimal('cash_tendered_usd', 12, 2)->nullable()->after('tendered_currency');
            $table->unsignedBigInteger('cash_tendered_khr')->nullable()->after('cash_tendered_usd');

            // Change to give back
            $table->decimal('change_usd', 12, 2)->nullable()->after('cash_tendered_khr');
            $table->unsignedBigInteger('change_khr')->nullable()->after('change_usd');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'tendered_currency',
                'cash_tendered_usd',
                'cash_tendered_khr',
                'change_usd',
                'change_khr',
            ]);
        });
    }
};
