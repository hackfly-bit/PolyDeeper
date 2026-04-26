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
            $table->unsignedInteger('priority')->default(100)->after('is_active');
            $table->string('risk_profile')->default('standard')->after('priority');
            $table->decimal('max_exposure_usd', 14, 2)->nullable()->after('risk_profile');
            $table->decimal('max_order_size', 18, 6)->nullable()->after('max_exposure_usd');
            $table->unsignedInteger('cooldown_seconds')->default(0)->after('max_order_size');
            $table->timestamp('last_rotated_at')->nullable()->after('last_validated_at');
            $table->timestamp('cooldown_until')->nullable()->after('last_rotated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polymarket_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'risk_profile',
                'max_exposure_usd',
                'max_order_size',
                'cooldown_seconds',
                'last_rotated_at',
                'cooldown_until',
            ]);
        });
    }
};
