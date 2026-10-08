<?php

namespace App\Services\Diagnosis;

use App\Jobs\DataTransformationBi\MaterializeDataTransformationBiImplementationSession;
use App\Models\DataTransformationBiImplementationMaterializationRun;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DataTransformationBiImplementationMaterializationDispatchService
{
    public function __construct(
        private readonly TransformationImplementationAuthorizationGate
            $authorizationGate
    ) {
    }

    /**
     * Reserve and enqueue one materialization run.
     *
     * Re-entering while QUEUED/PROCESSING is idempotent: the active run is
     * returned and no duplicate queue job is dispatched.
     *
     * @return array{
     *     run:DataTransformationBiImplementationMaterializationRun,
     *     reused:bool
     * }
     */
    public function dispatch(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $reservation =
            $this->reserve(
                $request,
                $session,
                $actor
            );

        /** @var DataTransformationBiImplementationMaterializationRun $run */
        $run =
            $reservation['run'];

        /*
         * Queue delivery is intentionally at-least-once for QUEUED runs.
         *
         * There is an unavoidable process boundary between committing the
         * run reservation and handing the job to the queue backend. If PHP
         * dies in that tiny window, a later Admin request must be capable of
         * redelivering the same QUEUED run instead of leaving it orphaned.
         *
         * Duplicate deliveries are safe because the Job atomically changes
         * QUEUED -> PROCESSING under run_uuid + row lock. Every later copy is
         * classified as stale.
         *
         * PROCESSING is different: a worker already owns that run, so a
         * repeated request must not enqueue another copy.
         */
        if (
            $reservation['reused']
            && (string) $run->status
                === DataTransformationBiImplementationMaterializationRun
                    ::STATUS_PROCESSING
        ) {
            return $reservation;
        }

        try {
            MaterializeDataTransformationBiImplementationSession
                ::dispatch(
                    (int) $run->getKey(),
                    (string) $run->run_uuid,
                    (int) $request->getKey(),
                    (int) $request->company_id,
                    (int) $session->getKey(),
                    (int) $actor->getKey()
                )
                ->onConnection(
                    'data_bi'
                )
                ->onQueue(
                    'data-bi'
                );
        } catch (Throwable $exception) {
            $this->markDispatchFailure(
                (int) $run->getKey(),
                (string) $run->run_uuid,
                (int) $request->company_id
            );

            throw new RuntimeException(
                'No se pudo encolar la materialización técnica de la sesión.',
                0,
                $exception
            );
        }

        return $reservation;
    }

    /**
     * Reserve one asynchronous materialization run for a session.
     *
     * The session row is the serialization lock because one session is the
     * orchestration scope.
     *
     * @return array{
     *     run:DataTransformationBiImplementationMaterializationRun,
     *     reused:bool
     * }
     */
    public function reserve(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $this->assertAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $session,
                $actor
            ): array {
                $this->authorizationGate
                    ->assertActiveForRequest(
                        $request,
                        true
                    );

                $lockedSession =
                    DataTransformationBiIntakeSession::query()
                        ->whereKey(
                            (int) $session->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->where(
                            'transformation_implementation_request_id',
                            (int) $request->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedSession === null) {
                    throw new AuthorizationException(
                        'La sesión no pertenece a esta implementación.'
                    );
                }

                $active =
                    DataTransformationBiImplementationMaterializationRun
                        ::query()
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->where(
                            'transformation_implementation_request_id',
                            (int) $request->getKey()
                        )
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $lockedSession->getKey()
                        )
                        ->whereIn(
                            'status',
                            [
                                DataTransformationBiImplementationMaterializationRun
                                    ::STATUS_QUEUED,

                                DataTransformationBiImplementationMaterializationRun
                                    ::STATUS_PROCESSING,
                            ]
                        )
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();

                if ($active !== null) {
                    return [
                        'run' =>
                            $active,

                        'reused' =>
                            true,
                    ];
                }

                $run =
                    new DataTransformationBiImplementationMaterializationRun();

                $run->forceFill([
                    'run_uuid' =>
                        (string) Str::uuid(),

                    'company_id' =>
                        (int) $request->company_id,

                    'transformation_implementation_request_id' =>
                        (int) $request->getKey(),

                    'data_transformation_bi_intake_session_id' =>
                        (int) $lockedSession->getKey(),

                    'requested_by_user_id' =>
                        (int) $actor->getKey(),

                    'status' =>
                        DataTransformationBiImplementationMaterializationRun
                            ::STATUS_QUEUED,

                    'selected_mapping_count' =>
                        0,

                    'materialized_dataset_count' =>
                        0,

                    'reused_dataset_count' =>
                        0,

                    'result_snapshot' =>
                        null,

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,

                    'queued_at' =>
                        now(),

                    'started_at' =>
                        null,

                    'finished_at' =>
                        null,
                ]);

                $run->save();

                return [
                    'run' =>
                        $run->fresh()
                        ?? $run,

                    'reused' =>
                        false,
                ];
            }
        );
    }

    private function assertAdmin(
        User $actor
    ): void {
        if (
            ! $actor->exists
            || (int) $actor->getKey() <= 0
            || (string) $actor->role !== 'admin'
        ) {
            throw new AuthorizationException(
                'La materialización técnica corresponde a Admin LAUDA.'
            );
        }
    }

    private function markDispatchFailure(
        int $runId,
        string $runUuid,
        int $companyId
    ): void {
        DataTransformationBiImplementationMaterializationRun
            ::query()
            ->whereKey(
                $runId
            )
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'run_uuid',
                $runUuid
            )
            ->where(
                'status',
                DataTransformationBiImplementationMaterializationRun
                    ::STATUS_QUEUED
            )
            ->update([
                'status' =>
                    DataTransformationBiImplementationMaterializationRun
                        ::STATUS_FAILED,

                'failure_code' =>
                    'implementation_materialization_dispatch_failed',

                'failure_message' =>
                    'No se pudo iniciar la materialización técnica de la sesión.',

                'finished_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }
}
