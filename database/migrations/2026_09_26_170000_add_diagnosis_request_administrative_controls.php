<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'company_diagnosis_settings',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->unique()
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->boolean('new_requests_blocked')
                    ->default(false)
                    ->index();

                $table->timestamp('blocked_at')
                    ->nullable();

                $table->foreignId('blocked_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('block_reason')
                    ->nullable();

                $table->timestamps();
            }
        );

        Schema::table(
            'diagnosis_access_requests',
            function (Blueprint $table): void {
                $table->timestamp('inactivated_at')
                    ->nullable()
                    ->after('rejected_at');

                $table->foreignId('inactivated_by_user_id')
                    ->nullable()
                    ->after('inactivated_at')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('inactivation_reason')
                    ->nullable()
                    ->after('inactivated_by_user_id');

                $table->string(
                    'status_before_inactivation',
                    30
                )
                    ->nullable()
                    ->after('inactivation_reason');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'diagnosis_access_requests',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'inactivated_by_user_id',
                ]);

                $table->dropColumn([
                    'inactivated_at',
                    'inactivated_by_user_id',
                    'inactivation_reason',
                    'status_before_inactivation',
                ]);
            }
        );

        Schema::dropIfExists(
            'company_diagnosis_settings'
        );
    }
};
