<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image'); // Image file path
            $table->string('link')->nullable(); // Optional URL
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0); // Optional sort order
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('banners');
    }
};
