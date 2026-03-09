<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_claim_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('activity_code', 100);
            $table->jsonb('payload')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('coins_awarded')->nullable();
            $table->uuid('reviewed_by_admin_id')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_claim_requests');
    }
};
