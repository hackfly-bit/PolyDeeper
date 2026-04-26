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
            $table->dropColumn('vault_key_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polymarket_accounts', function (Blueprint $table) {
            $table->string('vault_key_ref')->nullable()->after('env_key_name');
        });
    }
};
