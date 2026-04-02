<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('become_mentor_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('city', 150);
            $table->string('linkedin_profile', 500);
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['email', 'phone']);
            $table->index('created_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('become_mentor_submissions');
    }
};
