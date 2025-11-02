<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Table 1"
            $table->string('slug')->unique(); // e.g., "table1"
            $table->unsignedBigInteger('user_id')->unique(); // links to table user
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('tables');
    }
};
