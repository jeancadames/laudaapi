<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_access_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('contact_request_id')
                ->unique()
                ->constrained('contact_requests')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('diagnosis_assessment_id')
                ->nullable()
                ->constrained('diagnosis_assessments')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 30)->default('pending')->index();

            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('invitation_expires_at')->nullable()->index();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_access_requests');
    }
};
