<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('circle_subscriptions')) {
            return;
        }

        Schema::table('circle_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('circle_subscriptions', 'reference_id')) {
                $table->string('reference_id', 150)->nullable();
                $table->index('reference_id', 'idx_circle_subscriptions_reference_id_v2');
            }

            if (! Schema::hasColumn('circle_subscriptions', 'hostedpage_id')) {
                $table->string('hostedpage_id', 255)->nullable();
                $table->index('hostedpage_id', 'idx_circle_subscriptions_hostedpage_id');
            }

            if (! Schema::hasColumn('circle_subscriptions', 'decrypted_hosted_page_id')) {
                $table->string('decrypted_hosted_page_id', 100)->nullable();
                $table->index('decrypted_hosted_page_id', 'idx_circle_subscriptions_decrypted_hosted_page_id_v2');
            }

            if (! Schema::hasColumn('circle_subscriptions', 'raw_final_payload')) {
                $table->jsonb('raw_final_payload')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('circle_subscriptions')) {
            return;
        }

        Schema::table('circle_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('circle_subscriptions', 'raw_final_payload')) {
                $table->dropColumn('raw_final_payload');
            }

            if (Schema::hasColumn('circle_subscriptions', 'decrypted_hosted_page_id')) {
                $table->dropIndex('idx_circle_subscriptions_decrypted_hosted_page_id_v2');
                $table->dropColumn('decrypted_hosted_page_id');
            }

            if (Schema::hasColumn('circle_subscriptions', 'hostedpage_id')) {
                $table->dropIndex('idx_circle_subscriptions_hostedpage_id');
                $table->dropColumn('hostedpage_id');
            }

            if (Schema::hasColumn('circle_subscriptions', 'reference_id')) {
                $table->dropIndex('idx_circle_subscriptions_reference_id_v2');
                $table->dropColumn('reference_id');
            }
        });
    }
};

