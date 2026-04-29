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
        Schema::table('positions', function (Blueprint $table) {
            $table->decimal('closed_pnl_usd', 14, 2)->nullable()->after('closed_at');
            $table->string('outcome')->nullable()->after('closed_pnl_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'closed_pnl_usd',
                'outcome',
            ]);
        });
    }
};
