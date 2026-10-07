<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'transformation_implementation_commercial_engagements',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Exact modern professional-service scope.
                 *
                 * The Request remains terminal at ready_for_commercial.
                 * This aggregate owns the separate commercial lifecycle.
                 */
                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_definition_id'
                );

                $table->unsignedBigInteger('company_id');

                $table->unsignedBigInteger(
                    'transformation_implementation_phase_capability_id'
                );

                $table->string('capability_key', 120);

                /*
                 * Commercial revisions are immutable versions.
                 *
                 * A new commercial proposal must create a new version
                 * instead of mutating a version already presented.
                 */
                $table->unsignedSmallInteger('version');

                $table->string('status', 40)
                    ->default('draft');

                /*
                 * Commercial terms.
                 *
                 * Nullable while draft. Presentation/acceptance services
                 * will later enforce their own completeness contracts.
                 */
                $table->char('currency', 3)
                    ->default('DOP');

                $table->decimal('price_amount', 12, 2)
                    ->nullable();

                $table->unsignedInteger('duration_days')
                    ->nullable();

                /*
                 * Immutable commercial evidence snapshots.
                 *
                 * These are deliberately distinct from the functional
                 * Definition. The Definition is pinned separately above.
                 */
                $table->json('scope_snapshot')
                    ->nullable();

                $table->json('deliverables_snapshot')
                    ->nullable();

                $table->json('commercial_terms_snapshot')
                    ->nullable();

                $table->text('internal_notes')
                    ->nullable();

                /*
                 * Lifecycle actors.
                 */
                $table->unsignedBigInteger('created_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('updated_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('presented_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('accepted_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('rejected_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('cancelled_by_user_id')
                    ->nullable();

                /*
                 * Lifecycle timestamps.
                 */
                $table->timestamp('presented_at')
                    ->nullable();

                $table->timestamp('accepted_at')
                    ->nullable();

                $table->timestamp('rejected_at')
                    ->nullable();

                $table->timestamp('superseded_at')
                    ->nullable();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->text('rejection_reason')
                    ->nullable();

                $table->text('cancellation_reason')
                    ->nullable();

                $table->timestamps();

                /*
                 * One immutable version number per Request.
                 */
                $table->unique(
                    [
                        'transformation_implementation_request_id',
                        'version',
                    ],
                    'tice_request_version_unique'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'tice_request_status_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'tice_company_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_definition_id',
                        'status',
                    ],
                    'tice_definition_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_phase_capability_id',
                        'status',
                    ],
                    'tice_capability_status_idx'
                );

                /*
                 * Commercial evidence must never silently outlive or
                 * drift away from its exact professional-service scope.
                 */
                $table->foreign(
                    'transformation_implementation_request_id',
                    'tice_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->restrictOnDelete();

                $table->foreign(
                    'transformation_implementation_definition_id',
                    'tice_definition_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_definitions')
                    ->restrictOnDelete();

                $table->foreign(
                    'company_id',
                    'tice_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();

                $table->foreign(
                    'transformation_implementation_phase_capability_id',
                    'tice_capability_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_phase_capabilities'
                    )
                    ->restrictOnDelete();

                foreach (
                    [
                        'created_by_user_id' => 'tice_created_by_fk',
                        'updated_by_user_id' => 'tice_updated_by_fk',
                        'presented_by_user_id' => 'tice_presented_by_fk',
                        'accepted_by_user_id' => 'tice_accepted_by_fk',
                        'rejected_by_user_id' => 'tice_rejected_by_fk',
                        'cancelled_by_user_id' => 'tice_cancelled_by_fk',
                    ]
                    as $column => $constraint
                ) {
                    $table->foreign(
                        $column,
                        $constraint
                    )
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            }
        );

        Schema::create(
            'transformation_implementation_authorizations',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Authorization is explicitly separate from commercial
                 * acceptance.
                 */
                $table->unsignedBigInteger(
                    'transformation_implementation_commercial_engagement_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_request_id'
                );

                $table->unsignedBigInteger(
                    'transformation_implementation_definition_id'
                );

                $table->unsignedBigInteger('company_id');

                $table->unsignedBigInteger(
                    'transformation_implementation_phase_capability_id'
                );

                $table->string('capability_key', 120);

                $table->string('status', 40)
                    ->default('authorized');

                /*
                 * Exact evidence used when LAUDA authorizes technical
                 * implementation.
                 */
                $table->json('authorization_snapshot');

                $table->unsignedBigInteger('authorized_by_user_id')
                    ->nullable();

                $table->unsignedBigInteger('revoked_by_user_id')
                    ->nullable();

                $table->timestamp('authorized_at');

                $table->timestamp('revoked_at')
                    ->nullable();

                $table->text('revocation_reason')
                    ->nullable();

                $table->timestamps();

                /*
                 * One authorization aggregate per accepted engagement.
                 * Revocation changes this same authorization record;
                 * a future commercial version receives another one.
                 */
                $table->unique(
                    'transformation_implementation_commercial_engagement_id',
                    'tia_engagement_unique'
                );

                $table->index(
                    [
                        'transformation_implementation_request_id',
                        'status',
                    ],
                    'tia_request_status_idx'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'tia_company_status_idx'
                );

                $table->index(
                    [
                        'transformation_implementation_phase_capability_id',
                        'status',
                    ],
                    'tia_capability_status_idx'
                );

                $table->foreign(
                    'transformation_implementation_commercial_engagement_id',
                    'tia_engagement_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_commercial_engagements'
                    )
                    ->restrictOnDelete();

                $table->foreign(
                    'transformation_implementation_request_id',
                    'tia_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->restrictOnDelete();

                $table->foreign(
                    'transformation_implementation_definition_id',
                    'tia_definition_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_definitions')
                    ->restrictOnDelete();

                $table->foreign(
                    'company_id',
                    'tia_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();

                $table->foreign(
                    'transformation_implementation_phase_capability_id',
                    'tia_capability_fk'
                )
                    ->references('id')
                    ->on(
                        'transformation_implementation_phase_capabilities'
                    )
                    ->restrictOnDelete();

                $table->foreign(
                    'authorized_by_user_id',
                    'tia_authorized_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'revoked_by_user_id',
                    'tia_revoked_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'transformation_implementation_authorizations'
        );

        Schema::dropIfExists(
            'transformation_implementation_commercial_engagements'
        );
    }
};
