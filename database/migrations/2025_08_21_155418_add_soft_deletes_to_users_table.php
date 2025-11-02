<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // លប់ UNIQUE ដើមលើ email
            // (index name លំនាំដើម: users_email_unique)
            if (Schema::hasColumn('users', 'email')) {
                $table->dropUnique('users_email_unique');
            }

            // បន្ថែម deleted_at
            $table->softDeletes();

            // បង្កើត UNIQUE ថ្មី (email + deleted_at)
            // អនុញ្ញាតឲ្យមាន email ដូចគ្នា បើមួយបាន soft-deleted (deleted_at NOT NULL)
            $table->unique(['email', 'deleted_at'], 'users_email_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // លប់ UNIQUE ថ្មី
            $table->dropUnique('users_email_deleted_at_unique');

            // ដកចេញ deleted_at
            $table->dropSoftDeletes();

            // ត្រឡប់ UNIQUE ដើមលើ email
            $table->unique('email', 'users_email_unique');
        });
    }
};
