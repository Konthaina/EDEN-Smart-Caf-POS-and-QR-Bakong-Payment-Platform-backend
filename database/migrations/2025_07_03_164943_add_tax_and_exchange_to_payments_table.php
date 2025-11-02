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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 10, 2)->nullable()->after('tax_amount');
            $table->decimal('total_khr', 12, 0)->nullable()->after('exchange_rate');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'exchange_rate', 'total_khr']);
        });
    }

};
