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
        Schema::table('wallet_trades', function (Blueprint $table) {
            $table->foreignId('market_ref_id')->nullable()->after('wallet_id')->constrained('markets')->nullOnDelete();
            $table->string('condition_id')->nullable()->after('market_id')->index();
            $table->string('token_id')->nullable()->after('condition_id')->index();
        });

        Schema::table('signals', function (Blueprint $table) {
            $table->foreignId('market_ref_id')->nullable()->after('market_id')->constrained('markets')->nullOnDelete();
            $table->string('condition_id')->nullable()->after('market_ref_id')->index();
            $table->string('token_id')->nullable()->after('condition_id')->index();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('market_ref_id')->nullable()->after('market_id')->constrained('markets')->nullOnDelete();
            $table->string('condition_id')->nullable()->after('market_ref_id')->index();
            $table->string('token_id')->nullable()->after('condition_id')->index();
            $table->foreignId('order_id')->nullable()->after('token_id')->constrained('orders')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->string('exit_reason')->nullable()->after('closed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_ref_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['condition_id', 'token_id', 'closed_at', 'exit_reason']);
        });

        Schema::table('signals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_ref_id');
            $table->dropColumn(['condition_id', 'token_id']);
        });

        Schema::table('wallet_trades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_ref_id');
            $table->dropColumn(['condition_id', 'token_id']);
        });
    }
};
