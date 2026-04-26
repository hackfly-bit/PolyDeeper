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
        Schema::table('polymarket_accounts', function (Blueprint $table) {
            $table->unsignedInteger('auth_failure_count')->default(0)->after('cooldown_until');
            $table->unsignedInteger('rate_limit_hit_count')->default(0)->after('auth_failure_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polymarket_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'auth_failure_count',
                'rate_limit_hit_count',
            ]);
        });
    }
};
