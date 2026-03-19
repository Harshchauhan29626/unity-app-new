<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->string('button_text', 100)->nullable();
            $table->string('placement', 50)->default('timeline');
            $table->string('page_name', 100)->nullable();
            $table->unsignedInteger('timeline_position')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('placement');
            $table->index('is_active');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('timeline_position');
            $table->index('sort_order');
            $table->index(['placement', 'is_active', 'sort_order']);
            $table->index(['placement', 'timeline_position', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
