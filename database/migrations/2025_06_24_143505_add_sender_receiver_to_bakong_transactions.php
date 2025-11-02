<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakong_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3); // KHR or USD
            $table->text('qr_string');
            $table->string('status')->default('pending');
            $table->string('md5_hash')->nullable();
            $table->string('send_from')->nullable();      // Account ID from
            $table->string('receive_to')->nullable();     // Account ID to
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

};


