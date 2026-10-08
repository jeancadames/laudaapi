<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiImplementationMaterializationRun;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiImplementationMaterializationDispatchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminDataTransformationBiImplementationMaterializationController
    extends Controller
{
    public function store(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiImplementationMaterializationDispatchService $dispatchService
    ): JsonResponse {
        $actor =
            $this->adminActor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $dispatch =
                $dispatchService->dispatch(
                    $implementationRequest,
                    $session,
                    $actor
                );
        } catch (
            AuthorizationException $exception
        ) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        $exception->getMessage(),
                ],
                403
            );
        } catch (
            ValidationException $exception
        ) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        'La sesión no está lista para materialización técnica.',

                    'errors' =>
                        $exception->errors(),
                ],
                422
            );
        } catch (
            RuntimeException $exception
        ) {
            return response()->json(
                [
                    'ok' =>
                        false,

                    'message' =>
                        $exception->getMessage(),
                ],
                503
            );
        }

        /** @var DataTransformationBiImplementationMaterializationRun $run */
        $run =
            $dispatch['run'];

        return response()->json(
            [
                'ok' =>
                    true,

                'message' =>
                    $dispatch['reused']
                        ? 'La materialización técnica de esta sesión ya está en curso.'
                        : 'La materialización técnica fue encolada correctamente.',

                'materialization' =>
                    $this->runPayload(
                        $run,
                        (bool) $dispatch['reused']
                    ),
            ],
            202
        );
    }

    public function show(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        string $runUuid
    ): JsonResponse {
        $this->adminActor(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $run =
            DataTransformationBiImplementationMaterializationRun
                ::query()
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    (int) $implementationRequest->getKey()
                )
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->where(
                    'run_uuid',
                    $runUuid
                )
                ->firstOrFail();

        return response()->json([
            'ok' =>
                true,

            'materialization' =>
                $this->runPayload(
                    $run
                ),
        ]);
    }

    private function scopedSession(
        TransformationImplementationRequest $implementationRequest,
        int $sessionId
    ): DataTransformationBiIntakeSession {
        return DataTransformationBiIntakeSession::query()
            ->whereKey(
                $sessionId
            )
            ->where(
                'company_id',
                (int) $implementationRequest->company_id
            )
            ->where(
                'transformation_implementation_request_id',
                (int) $implementationRequest->getKey()
            )
            ->firstOrFail();
    }

    private function adminActor(
        Request $request
    ): User {
        $actor =
            $request->user();

        if (
            ! $actor instanceof User
            || (string) $actor->role !== 'admin'
        ) {
            throw new AuthorizationException(
                'La materialización técnica corresponde a Admin LAUDA.'
            );
        }

        return $actor;
    }

    /**
     * @return array<string,mixed>
     */
    private function runPayload(
        DataTransformationBiImplementationMaterializationRun $run,
        ?bool $reused = null
    ): array {
        $payload = [
            'id' =>
                (int) $run->getKey(),

            'run_uuid' =>
                (string) $run->run_uuid,

            'status' =>
                (string) $run->status,

            'selected_mapping_count' =>
                (int) $run->selected_mapping_count,

            'materialized_dataset_count' =>
                (int) $run->materialized_dataset_count,

            'reused_dataset_count' =>
                (int) $run->reused_dataset_count,

            'failure_code' =>
                $run->failure_code !== null
                    ? (string) $run->failure_code
                    : null,

            'failure_message' =>
                $run->failure_message !== null
                    ? (string) $run->failure_message
                    : null,

            'queued_at' =>
                $run->queued_at
                    ?->toISOString(),

            'started_at' =>
                $run->started_at
                    ?->toISOString(),

            'finished_at' =>
                $run->finished_at
                    ?->toISOString(),

            /*
             * This snapshot contains only dataset ids/pins/hashes/counts.
             * Canonical row payloads are never exposed here.
             */
            'result' =>
                $run->result_snapshot,
        ];

        if ($reused !== null) {
            $payload['reused_run'] =
                $reused;
        }

        return $payload;
    }
}
