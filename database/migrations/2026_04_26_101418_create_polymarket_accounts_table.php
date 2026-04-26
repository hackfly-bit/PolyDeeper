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
        Schema::create('polymarket_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_slug')->unique();
            $table->string('wallet_address')->nullable();
            $table->string('funder_address')->nullable();
            $table->unsignedTinyInteger('signature_type')->default(0);
            $table->string('env_key_name')->nullable();
            $table->string('vault_key_ref')->nullable();
            $table->string('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('api_passphrase')->nullable();
            $table->string('credential_status')->default('pending');
            $table->string('last_error_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polymarket_accounts');
    }
};
