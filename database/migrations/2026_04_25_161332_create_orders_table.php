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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition_id')->nullable()->index();
            $table->string('token_id')->nullable()->index();
            $table->string('side', 10);
            $table->string('order_type', 10)->default('GTC');
            $table->decimal('price', 10, 4);
            $table->decimal('size', 18, 6);
            $table->decimal('filled_size', 18, 6)->default(0);
            $table->string('status', 40)->default('pending')->index();
            $table->string('polymarket_order_id')->nullable()->index();
            $table->string('client_order_id')->nullable()->index();
            $table->unsignedTinyInteger('signature_type')->nullable();
            $table->string('funder_address')->nullable();
            $table->string('tx_hash')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
