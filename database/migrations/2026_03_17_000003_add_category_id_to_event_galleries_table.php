<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_galleries')) {
            return;
        }

        Schema::table('event_galleries', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_galleries', 'category_id')) {
                $table->foreignId('category_id')->nullable();
            }
        });

        if (Schema::hasTable('categories') && Schema::hasColumn('event_galleries', 'category')) {
            DB::table('event_galleries as eg')
                ->join('categories as c', DB::raw('LOWER(TRIM(eg.category))'), '=', DB::raw('LOWER(TRIM(c.category_name))'))
                ->whereNull('eg.category_id')
                ->update(['category_id' => DB::raw('c.id')]);
        }

        Schema::table('event_galleries', function (Blueprint $table): void {
            if (Schema::hasColumn('event_galleries', 'category_id')) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
                $table->index('category_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_galleries') || ! Schema::hasColumn('event_galleries', 'category_id')) {
            return;
        }

        Schema::table('event_galleries', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
