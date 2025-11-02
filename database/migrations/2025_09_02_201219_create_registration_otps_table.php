<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('registration_otps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('resends')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('registration_otps');
    }
};
