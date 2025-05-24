<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusEnum extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert back to include 'paid' if ever needed
        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'completed', 'cancelled', 'paid') NOT NULL DEFAULT 'pending'");
    }
}
