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
        Schema::create('wallet_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->index();
            $table->string('market_id')->index();
            $table->string('side'); // YES or NO
            $table->decimal('price', 8, 4);
            $table->decimal('size', 16, 4);
            $table->timestamp('traded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_trades');
    }
};
