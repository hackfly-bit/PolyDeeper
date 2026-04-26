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
        Schema::create('market_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('token_id')->unique();
            $table->string('outcome', 20)->nullable();
            $table->boolean('is_yes')->default(false)->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['market_id', 'outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_tokens');
    }
};
