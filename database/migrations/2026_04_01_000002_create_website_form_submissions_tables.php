<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('become_speaker_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('city', 150);
            $table->string('linkedin_profile_url', 500);
            $table->string('company_name', 255);
            $table->text('brief_bio');
            $table->text('topics_to_speak_on');
            $table->uuid('image_file_id')->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['email', 'phone']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('sme_business_story_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('contact_number', 30);
            $table->string('business_name', 255);
            $table->text('company_introduction');
            $table->text('co_founders_and_partners_details')->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['email', 'contact_number']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('leadership_certification_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 255);
            $table->string('business_name', 255);
            $table->string('email', 255);
            $table->string('contact_no', 30);
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['email', 'contact_no']);
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('entrepreneur_certification_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 255);
            $table->string('business_name', 255);
            $table->string('email', 255);
            $table->string('contact_no', 30);
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['email', 'contact_no']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_certification_submissions');
        Schema::dropIfExists('leadership_certification_submissions');
        Schema::dropIfExists('sme_business_story_submissions');
        Schema::dropIfExists('become_speaker_submissions');
    }
};
