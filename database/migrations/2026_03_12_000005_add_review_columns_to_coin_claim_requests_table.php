<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_claim_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('coin_claim_requests', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }

            if (! Schema::hasColumn('coin_claim_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('coin_claim_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS coin_claim_requests_approved_at_idx ON coin_claim_requests (approved_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS coin_claim_requests_rejected_at_idx ON coin_claim_requests (rejected_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS coin_claim_requests_approved_at_idx');
        DB::statement('DROP INDEX IF EXISTS coin_claim_requests_rejected_at_idx');

        Schema::table('coin_claim_requests', function (Blueprint $table): void {
            $dropColumns = [];

            if (Schema::hasColumn('coin_claim_requests', 'admin_notes')) {
                $dropColumns[] = 'admin_notes';
            }

            if (Schema::hasColumn('coin_claim_requests', 'approved_at')) {
                $dropColumns[] = 'approved_at';
            }

            if (Schema::hasColumn('coin_claim_requests', 'rejected_at')) {
                $dropColumns[] = 'rejected_at';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
