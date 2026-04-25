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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->decimal('weight', 5, 4)->default(0.5); // e.g. 0.8500
            $table->decimal('win_rate', 5, 2)->default(0); // e.g. 75.50
            $table->decimal('roi', 8, 2)->default(0);
            $table->timestamp('last_active')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};