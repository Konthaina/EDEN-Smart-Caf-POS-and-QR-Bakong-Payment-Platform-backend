<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                 // e.g. Small, Medium, Large
            $table->decimal('price', 10, 2)->default(0); // per-variant price
            $table->boolean('is_active')->default(true);
            $table->string('sku', 80)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->softDeletes();
            $table->timestamps();

            // unique per item (allow reuse after soft-delete)
            $table->unique(['menu_item_id', 'name', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variants');
    }
};
