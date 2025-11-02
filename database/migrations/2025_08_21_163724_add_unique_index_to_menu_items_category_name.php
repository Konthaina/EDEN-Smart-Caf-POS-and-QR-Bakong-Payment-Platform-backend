<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unique(['category_id', 'name'], 'menu_items_category_id_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique('menu_items_category_id_name_unique');
        });
    }
};
