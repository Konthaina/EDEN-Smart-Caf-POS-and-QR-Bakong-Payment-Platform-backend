<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table) {
            // Variant-level discount fields (nullable; when null we fall back to parent)
            $table->enum('discount_type', ['percent', 'fixed'])->nullable()->after('price');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            $table->timestamp('discount_starts_at')->nullable()->after('discount_value');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table) {
            $table->dropColumn([
                'discount_type',
                'discount_value',
                'discount_starts_at',
                'discount_ends_at',
            ]);
        });
    }
};
