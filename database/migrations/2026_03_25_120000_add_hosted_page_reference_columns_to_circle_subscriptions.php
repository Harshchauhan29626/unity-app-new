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
                $table->string('reference_id', 150)->nullable()->after('zoho_hosted_page_id');
                $table->index('reference_id', 'idx_circle_subscriptions_reference_id');
            }

            if (! Schema::hasColumn('circle_subscriptions', 'zoho_decrypted_hosted_page_id')) {
                $table->string('zoho_decrypted_hosted_page_id', 150)->nullable()->after('reference_id');
                $table->index('zoho_decrypted_hosted_page_id', 'idx_circle_subscriptions_decrypted_hosted_page_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('circle_subscriptions')) {
            return;
        }

        Schema::table('circle_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('circle_subscriptions', 'zoho_decrypted_hosted_page_id')) {
                $table->dropIndex('idx_circle_subscriptions_decrypted_hosted_page_id');
                $table->dropColumn('zoho_decrypted_hosted_page_id');
            }

            if (Schema::hasColumn('circle_subscriptions', 'reference_id')) {
                $table->dropIndex('idx_circle_subscriptions_reference_id');
                $table->dropColumn('reference_id');
            }
        });
    }
};

