<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_circle_subscription_prices_zoho_addon_code ON circle_subscription_prices(zoho_addon_code) WHERE zoho_addon_code IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_circle_subscription_prices_zoho_addon_code');
    }
};
