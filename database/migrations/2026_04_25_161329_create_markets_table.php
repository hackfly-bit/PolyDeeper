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
        if (! Schema::hasTable('markets')) {
            Schema::create('markets', function (Blueprint $table) {
                $table->id();
                $table->string('condition_id')->unique();
                $table->string('slug')->nullable()->index();
                $table->text('question')->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->boolean('closed')->default(false)->index();
                $table->timestamp('end_date')->nullable()->index();
                $table->decimal('minimum_tick_size', 8, 4)->nullable();
                $table->boolean('neg_risk')->default(false);
                $table->json('raw_payload')->nullable();
                $table->timestamp('last_synced_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
