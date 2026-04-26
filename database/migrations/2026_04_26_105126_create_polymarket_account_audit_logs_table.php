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
        Schema::create('polymarket_account_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polymarket_account_id')
                ->constrained('polymarket_accounts')
                ->cascadeOnDelete();
            $table->string('action');
            $table->string('status')->default('info');
            $table->string('actor')->default('system');
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polymarket_account_audit_logs');
    }
};
