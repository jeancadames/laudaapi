<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('review_summary')->nullable();
            $table->json('review_priorities')->nullable();

            $table->string('final_modality', 30)->nullable();
            $table->string('final_modality_label', 120)->nullable();

            $table->timestamp('published_at')->nullable();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->dropIndex(['status', 'published_at']);
            $table->dropConstrainedForeignId('reviewed_by_user_id');

            $table->dropColumn([
                'review_summary',
                'review_priorities',
                'final_modality',
                'final_modality_label',
                'published_at',
            ]);
        });
    }
};
