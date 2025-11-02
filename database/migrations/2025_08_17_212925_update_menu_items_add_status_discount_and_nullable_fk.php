<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Drop old FK so we can alter the column
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'category_id')) {
                $table->dropForeign(['category_id']);
            }
        });

        // 2) Make category_id nullable and re-create FK with SET NULL
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->onDelete('set null');
        });

        // 3) Add housekeeping & discount fields
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
            if (!Schema::hasColumn('menu_items', 'deleted_at')) {
                $table->softDeletes(); // deleted_at
            }

            if (!Schema::hasColumn('menu_items', 'discount_type')) {
                $table->enum('discount_type', ['percent','fixed'])->nullable()->after('price');
            }
            if (!Schema::hasColumn('menu_items', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }
            if (!Schema::hasColumn('menu_items', 'discount_starts_at')) {
                $table->timestamp('discount_starts_at')->nullable()->after('discount_value');
            }
            if (!Schema::hasColumn('menu_items', 'discount_ends_at')) {
                $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
            }

            $table->index(['is_active']);
            $table->index(['discount_starts_at','discount_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Drop new fields
            if (Schema::hasColumn('menu_items','discount_ends_at')) $table->dropColumn('discount_ends_at');
            if (Schema::hasColumn('menu_items','discount_starts_at')) $table->dropColumn('discount_starts_at');
            if (Schema::hasColumn('menu_items','discount_value')) $table->dropColumn('discount_value');
            if (Schema::hasColumn('menu_items','discount_type')) $table->dropColumn('discount_type');

            if (Schema::hasColumn('menu_items','deleted_at')) $table->dropSoftDeletes();
            if (Schema::hasColumn('menu_items','is_active')) $table->dropColumn('is_active');

            // revert FK to NOT NULL + CASCADE (only if you really want)
            $table->dropForeign(['category_id']);
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->onDelete('cascade');
        });
    }
};
