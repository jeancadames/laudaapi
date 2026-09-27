<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiEvaluationFindingService;
use App\Services\Diagnosis\DataTransformationBiEvaluationService;
use App\Services\Diagnosis\DataTransformationBiEvaluationWorkspaceReadModel;
use App\Services\Diagnosis\DataTransformationBiTenantSourceWorkspaceGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AdminDataTransformationBiEvaluationController
    extends Controller
{
    /**
     * Strictly read-only Admin LAUDA evaluation workspace.
     *
     * GET must never create or refresh an evaluation draft.
     */
    public function workspace(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $this->actor(
            $request
        );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        return response()->json([
            'ok' => true,

            'workspace' =>
                $workspace->forSession(
                    $implementationRequest,
                    $session
                ),
        ]);
    }

    public function prepare(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiEvaluationService $evaluations,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $evaluations->createOrRefreshDraft(
                $session,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo preparar la evaluación diagnóstica.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Evaluación diagnóstica preparada correctamente.'
        );
    }

    public function createFinding(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiEvaluationFindingService $findings,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $evaluation =
            $this->scopedEvaluation(
                $implementationRequest,
                $session
            );

        $validated =
            $this->findingInput(
                $request
            );

        try {
            $findings->create(
                $evaluation,
                $actor,
                $validated
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo registrar el hallazgo.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Hallazgo registrado correctamente.'
        );
    }

    public function updateFinding(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $findingId,
        DataTransformationBiEvaluationFindingService $findings,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $evaluation =
            $this->scopedEvaluation(
                $implementationRequest,
                $session
            );

        $finding =
            $this->scopedFinding(
                $evaluation,
                $findingId
            );

        $validated =
            $this->findingInput(
                $request
            );

        try {
            $findings->update(
                $evaluation,
                $finding,
                $actor,
                $validated
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo actualizar el hallazgo.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Hallazgo actualizado correctamente.'
        );
    }

    public function reconfirmFinding(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $findingId,
        DataTransformationBiEvaluationFindingService $findings,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $evaluation =
            $this->scopedEvaluation(
                $implementationRequest,
                $session
            );

        $finding =
            $this->scopedFinding(
                $evaluation,
                $findingId
            );

        try {
            $findings->reconfirm(
                $evaluation,
                $finding,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo reconfirmar el hallazgo.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Hallazgo reconfirmado contra la evidencia actual.'
        );
    }

    public function deleteFinding(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        int $findingId,
        DataTransformationBiEvaluationFindingService $findings,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        $evaluation =
            $this->scopedEvaluation(
                $implementationRequest,
                $session
            );

        $finding =
            $this->scopedFinding(
                $evaluation,
                $findingId
            );

        try {
            $findings->delete(
                $evaluation,
                $finding,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'No se pudo eliminar el hallazgo.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Hallazgo eliminado correctamente.'
        );
    }

    public function readyForReview(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiEvaluationService $evaluations,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $evaluations->markReadyForReview(
                $session,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'La evaluación todavía no puede enviarse a revisión.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Evaluación lista para revisión.'
        );
    }

    public function publish(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $sessionId,
        DataTransformationBiEvaluationService $evaluations,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace
    ): JsonResponse {
        $actor =
            $this->actor(
                $request
            );

        $session =
            $this->scopedSession(
                $implementationRequest,
                $sessionId
            );

        try {
            $evaluations->publish(
                $session,
                $actor
            );
        } catch (
            ValidationException $exception
        ) {
            return $this->validationError(
                $exception,
                'La evaluación todavía no puede publicarse.'
            );
        }

        return $this->workspaceResponse(
            $implementationRequest,
            $session,
            $workspace,
            'Evaluación publicada correctamente.'
        );
    }

    private function actor(
        Request $request
    ): User {
        $actor =
            $request->user();

        abort_unless(
            $actor instanceof User
            && (string) $actor->role === 'admin',
            403
        );

        return $actor;
    }

    private function assertRequest(
        TransformationImplementationRequest $implementationRequest
    ): void {
        abort_unless(
            $implementationRequest->exists
            && (int) $implementationRequest->getKey() > 0
            && (int) $implementationRequest->company_id > 0
            && (string) $implementationRequest->capability_key
                === 'data_transformation_bi',
            404
        );

        /*
         * Keep the same Data BI request/lifecycle boundary already
         * enforced by the existing Admin Intake V2 controller.
         *
         * This guard performs authorization only. It must never
         * create or refresh an evaluation.
         */
        app(
            DataTransformationBiTenantSourceWorkspaceGate::class
        )->assertCanManage(
            $implementationRequest
        );
    }

    private function scopedSession(
        TransformationImplementationRequest $implementationRequest,
        int $sessionId
    ): DataTransformationBiIntakeSession {
        $this->assertRequest(
            $implementationRequest
        );

        return DataTransformationBiIntakeSession::query()
            ->whereKey(
                $sessionId
            )
            ->where(
                'transformation_implementation_request_id',
                $implementationRequest->getKey()
            )
            ->where(
                'company_id',
                $implementationRequest->company_id
            )
            ->firstOrFail();
    }

    private function scopedEvaluation(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): DataTransformationBiEvaluation {
        return DataTransformationBiEvaluation::query()
            ->where(
                'data_transformation_bi_intake_session_id',
                $session->getKey()
            )
            ->where(
                'transformation_implementation_request_id',
                $implementationRequest->getKey()
            )
            ->where(
                'company_id',
                $implementationRequest->company_id
            )
            ->firstOrFail();
    }

    private function scopedFinding(
        DataTransformationBiEvaluation $evaluation,
        int $findingId
    ): DataTransformationBiEvaluationFinding {
        return DataTransformationBiEvaluationFinding::query()
            ->whereKey(
                $findingId
            )
            ->where(
                'data_transformation_bi_evaluation_id',
                $evaluation->getKey()
            )
            ->firstOrFail();
    }

    /**
     * @return array{
     *     finding_type:string,
     *     title:string,
     *     details:string,
     *     recommendation:?string,
     *     priority:?string,
     *     source_ids:list<int>
     * }
     */
    private function findingInput(
        Request $request
    ): array {
        $validator =
            Validator::make(
                $request->all(),
                [
                    'finding_type' => [
                        'required',
                        'string',
                        'max:32',
                    ],

                    'title' => [
                        'required',
                        'string',
                        'max:191',
                    ],

                    'details' => [
                        'required',
                        'string',
                    ],

                    'recommendation' => [
                        'nullable',
                        'string',
                    ],

                    'priority' => [
                        'nullable',
                        'string',
                        'max:16',
                    ],

                    'source_ids' => [
                        'sometimes',
                        'array',
                    ],

                    'source_ids.*' => [
                        'integer',
                        'min:1',
                    ],
                ]
            );

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                $validator
                    ->errors()
                    ->toArray()
            );
        }

        $validated =
            $validator->validated();

        return [
            'finding_type' =>
                (string) $validated[
                    'finding_type'
                ],

            'title' =>
                (string) $validated[
                    'title'
                ],

            'details' =>
                (string) $validated[
                    'details'
                ],

            'recommendation' =>
                isset(
                    $validated['recommendation']
                )
                    ? (string) $validated[
                        'recommendation'
                    ]
                    : null,

            'priority' =>
                isset(
                    $validated['priority']
                )
                    ? (string) $validated[
                        'priority'
                    ]
                    : null,

            'source_ids' =>
                array_values(
                    array_map(
                        'intval',
                        $validated['source_ids']
                        ?? []
                    )
                ),
        ];
    }

    private function workspaceResponse(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiEvaluationWorkspaceReadModel $workspace,
        string $message
    ): JsonResponse {
        return response()->json([
            'ok' =>
                true,

            'message' =>
                $message,

            'workspace' =>
                $workspace->forSession(
                    $implementationRequest,
                    $session->fresh()
                    ?? $session
                ),
        ]);
    }

    private function validationError(
        ValidationException $exception,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'ok' =>
                    false,

                'message' =>
                    $message,

                'errors' =>
                    $exception->errors(),
            ],
            422
        );
    }
}
