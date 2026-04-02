<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_with_us_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 255);
            $table->string('mobile_number', 30);
            $table->string('email_id', 255);
            $table->string('city', 150);
            $table->string('brand_or_company_name', 255);
            $table->string('website_or_social_media_link', 500)->nullable();
            $table->string('industry', 150);
            $table->text('about_your_business');
            $table->text('partnership_goal');
            $table->text('why_partner_with_peers_global');
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('email_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_with_us_submissions');
    }
};
