<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('discount_amount');      // e.g., 10.00 for 10%
            $table->decimal('tax_amount', 10, 2)->nullable()->after('tax_rate');          // the calculated amount
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('tax_amount');     // e.g., 4100
            $table->decimal('total_khr', 12, 0)->nullable()->after('exchange_rate');      // grand total in KHR
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount', 'exchange_rate', 'total_khr']);
        });
    }

};
