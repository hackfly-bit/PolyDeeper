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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('polymarket_account_id')
                ->nullable()
                ->after('market_id')
                ->constrained('polymarket_accounts')
                ->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique()->after('client_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_idempotency_key_unique');
            $table->dropConstrainedForeignId('polymarket_account_id');
            $table->dropColumn('idempotency_key');
        });
    }
};
