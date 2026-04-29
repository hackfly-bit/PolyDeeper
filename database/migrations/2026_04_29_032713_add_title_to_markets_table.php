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
        if (! Schema::hasColumn('markets', 'title')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->string('title')->nullable()->after('slug');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('markets', 'title')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
    }
};
