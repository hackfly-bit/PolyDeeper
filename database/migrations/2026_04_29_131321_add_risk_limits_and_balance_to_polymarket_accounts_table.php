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
            $table->unsignedInteger('max_open_positions')->default(0)->after('max_order_size');
            $table->unsignedInteger('max_open_positions_per_market')->default(0)->after('max_open_positions');
            $table->decimal('max_order_size_in_usd', 14, 2)->default(0)->after('max_open_positions_per_market');
            $table->string('daily_limit_mode')->default('count')->after('max_order_size_in_usd');
            $table->decimal('max_daily_loss_position', 14, 2)->default(0)->after('daily_limit_mode');
            $table->decimal('max_daily_win_position', 14, 2)->default(0)->after('max_daily_loss_position');
            $table->decimal('last_balance_usd', 18, 2)->default(0)->after('max_daily_win_position');
            $table->timestamp('last_balance_refreshed_at')->nullable()->after('last_balance_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polymarket_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'max_open_positions',
                'max_open_positions_per_market',
                'max_order_size_in_usd',
                'daily_limit_mode',
                'max_daily_loss_position',
                'max_daily_win_position',
                'last_balance_usd',
                'last_balance_refreshed_at',
            ]);
        });
    }
};
