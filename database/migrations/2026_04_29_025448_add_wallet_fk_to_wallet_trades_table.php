<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF to_regclass('public.wallet_trades') IS NOT NULL
       AND to_regclass('public.wallets') IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM pg_constraint
           WHERE conname = 'wallet_trades_wallet_id_foreign'
       ) THEN
        ALTER TABLE "wallet_trades"
            ADD CONSTRAINT "wallet_trades_wallet_id_foreign"
            FOREIGN KEY ("wallet_id")
            REFERENCES "wallets" ("id")
            ON DELETE CASCADE;
    END IF;
END
$$;
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF to_regclass('public.wallet_trades') IS NOT NULL
       AND EXISTS (
           SELECT 1
           FROM pg_constraint
           WHERE conname = 'wallet_trades_wallet_id_foreign'
       ) THEN
        ALTER TABLE "wallet_trades"
            DROP CONSTRAINT "wallet_trades_wallet_id_foreign";
    END IF;
END
$$;
SQL);
    }
};
