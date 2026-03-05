<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('zoho_customer_id', 50)->nullable();
            $table->string('zoho_subscription_id', 100)->nullable();
            $table->string('zoho_plan_code', 100)->nullable();
            $table->string('zoho_last_invoice_id', 100)->nullable();
            $table->timestampTz('membership_starts_at')->nullable();
            $table->timestampTz('membership_ends_at')->nullable();
            $table->timestampTz('last_payment_at')->nullable();

            $table->index('zoho_customer_id');
            $table->index('zoho_subscription_id');
            $table->index('membership_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['zoho_customer_id']);
            $table->dropIndex(['zoho_subscription_id']);
            $table->dropIndex(['membership_ends_at']);

            $table->dropColumn([
                'zoho_customer_id',
                'zoho_subscription_id',
                'zoho_plan_code',
                'zoho_last_invoice_id',
                'membership_starts_at',
                'membership_ends_at',
                'last_payment_at',
            ]);
        });
    }
};

