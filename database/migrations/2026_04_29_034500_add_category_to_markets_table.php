<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('markets', 'category')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->string('category')->nullable()->after('title')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('markets', 'category')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
