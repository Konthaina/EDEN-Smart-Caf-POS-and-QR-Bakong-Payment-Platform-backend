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
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('customizations')->nullable()->after('subtotal');
            $table->string('note')->nullable()->after('customizations');
        });
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('customizations');
            $table->dropColumn('note');
        });
    }

};
