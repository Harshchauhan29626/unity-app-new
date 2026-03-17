<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('circle_category_mappings')) {
            return;
        }

        Schema::create('circle_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->foreignId('category_id');
            $table->timestamps();

            $table->unique(['circle_id', 'category_id']);
            $table->foreign('circle_id')->references('id')->on('circles')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_category_mappings');
    }
};
