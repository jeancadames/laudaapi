<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->boolean('is_active')
                ->default(true)
                ->after('status');

            $table->timestamp('inactivated_at')
                ->nullable()
                ->after('reviewed_at');

            $table->unsignedBigInteger('superseded_by_assessment_id')
                ->nullable()
                ->after('inactivated_at');

            $table->index(
                ['organization_id', 'is_active'],
                'daa_org_active_idx'
            );

            $table->foreign(
                'superseded_by_assessment_id',
                'daa_superseded_fk'
            )
                ->references('id')
                ->on('diagnosis_assessments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->dropForeign('daa_superseded_fk');
            $table->dropIndex('daa_org_active_idx');
            $table->dropColumn([
                'superseded_by_assessment_id',
                'inactivated_at',
                'is_active',
            ]);
        });
    }
};
