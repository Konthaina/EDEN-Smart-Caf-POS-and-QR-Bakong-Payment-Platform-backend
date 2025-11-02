<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('menu_item_variant_id')
                  ->nullable()
                  ->after('menu_item_id')
                  ->constrained('menu_item_variants')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_item_variant_id');
        });
    }
};
