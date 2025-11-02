<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('low_alert_qty', 12, 3)->nullable()->after('unit'); // e.g., 500.000 ml
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('low_alert_qty');
        });
    }
};
