<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('circles')) {
            return;
        }

        Schema::table('circles', function (Blueprint $table): void {
            if (! Schema::hasColumn('circles', 'circle_stage')) {
                $table->string('circle_stage')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('circles') || ! Schema::hasColumn('circles', 'circle_stage')) {
            return;
        }

        Schema::table('circles', function (Blueprint $table): void {
            $table->dropColumn('circle_stage');
        });
    }
};
