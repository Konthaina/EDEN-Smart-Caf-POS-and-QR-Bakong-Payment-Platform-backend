<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('slug');
            $table->softDeletes(); // deleted_at
        });

        // Backfill slug ពី name
        DB::table('categories')->select('id','name')->orderBy('id')->get()
            ->each(function ($row) {
                $base = Str::slug($row->name);
                $slug = $base;
                $i = 1;
                while (DB::table('categories')->where('slug', $slug)->where('id','<>',$row->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                DB::table('categories')->where('id', $row->id)->update(['slug' => $slug]);
            });

        // (ជម្រើស) បើចង់បង្ខំ NOT NULL ត្រូវការ doctrine/dbal → យើងនឹងធ្វើពេលក្រោយ
        // Schema::table('categories', function (Blueprint $table) {
        //     $table->string('slug')->unique()->nullable(false)->change();
        // });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_active','slug']);
        });
    }
};
