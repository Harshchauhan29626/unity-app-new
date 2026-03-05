<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circle_subscription_prices')) {
            Schema::table('circle_subscription_prices', function (Blueprint $table): void {
                if (! Schema::hasColumn('circle_subscription_prices', 'payload')) {
                    $table->jsonb('payload')->nullable();
                }

                if (! Schema::hasColumn('circle_subscription_prices', 'zoho_addon_interval_unit')) {
                    $table->string('zoho_addon_interval_unit', 20)->nullable();
                }
            });

            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_circle_subscription_prices_zoho_addon_code ON circle_subscription_prices(zoho_addon_code) WHERE zoho_addon_code IS NOT NULL');
        }

        if (! Schema::hasTable('zoho_addon_code_sequences')) {
            Schema::create('zoho_addon_code_sequences', function (Blueprint $table): void {
                $table->smallInteger('id')->primary();
                $table->unsignedBigInteger('next_code')->default(10);
                $table->timestamps();
            });
        }

        DB::statement('INSERT INTO zoho_addon_code_sequences (id, next_code, created_at, updated_at) VALUES (1, 10, NOW(), NOW()) ON CONFLICT (id) DO NOTHING');
    }

    public function down(): void
    {
        if (Schema::hasTable('circle_subscription_prices')) {
            Schema::table('circle_subscription_prices', function (Blueprint $table): void {
                if (Schema::hasColumn('circle_subscription_prices', 'zoho_addon_interval_unit')) {
                    $table->dropColumn('zoho_addon_interval_unit');
                }

                if (Schema::hasColumn('circle_subscription_prices', 'payload')) {
                    $table->dropColumn('payload');
                }
            });
        }

        DB::statement('DROP INDEX IF EXISTS uq_circle_subscription_prices_zoho_addon_code');

        Schema::dropIfExists('zoho_addon_code_sequences');
    }
};
