<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop existing constraint
            $table->dropForeign(['user_id']);

            // Make user_id nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Add foreign key with SET NULL on delete
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
