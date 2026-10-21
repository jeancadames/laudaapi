<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use App\Models\DataTransformationBiImplementationCandidate;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\DataTransformationBiImplementationCandidateService;
use App\Services\Diagnosis\DataTransformationBiImplementationHandoffEligibilityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * H1-C: Solo Admin LAUDA. Selección humana para revisión futura de planificación.
 * NO produce plan, propuesta comercial, contratación, ejecución ni datasets BI.
 */
final class AdminDataBiImplementationCandidateController extends Controller
{
    public function index(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiImplementationHandoffEligibilityPolicy $policy
    ): JsonResponse {
        $actor = $this->admin($request);
        $this->dataBiRequest($implementationRequest);
        $companyId = (int) $implementationRequest->company_id;
        $reqId = (int) $implementationRequest->getKey();

        $candidates = DataTransformationBiImplementationCandidate::query()
            ->where('company_id', $companyId)
            ->where('origin_request_id', $reqId)
            ->orderByDesc('selected_at')
            ->orderByDesc('id')
            ->get()
            ->map(static function (DataTransformationBiImplementationCandidate $candidate): array {
                $origin = (array) $candidate->origin_snapshot;
                $challenge = (array) ($origin['challenge'] ?? []);
                return [
                    'id' => (int) $candidate->getKey(),
                    'challenge_id' => (int) $candidate->origin_challenge_id,
                    'title' => (string) ($challenge['title'] ?? 'Reto seleccionado'),
                    'selected_at' => $candidate->selected_at?->format('Y-m-d H:i'),
                    'origin_available' => $candidate->challenge_ref_id !== null,
                ];
            })->values()->all();

        $selected = array_fill_keys(array_column($candidates, 'challenge_id'), true);
        $available = [];
        $evaluations = DataTransformationBiEvaluation::query()
            ->where('company_id', $companyId)
            ->where('transformation_implementation_request_id', $reqId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('id')
            ->get();

        foreach ($evaluations as $evaluation) {
            $session = DataTransformationBiIntakeSession::query()
                ->whereKey($evaluation->data_transformation_bi_intake_session_id)
                ->where('company_id', $companyId)
                ->where('transformation_implementation_request_id', $reqId)
                ->first();
            if ($session === null) {
                continue;
            }

            $challenges = DataTransformationBiEvaluationImplementationChallenge::query()
                ->where('company_id', $companyId)
                ->where('data_transformation_bi_evaluation_id', $evaluation->getKey())
                ->with('findings')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($challenges as $challenge) {
                $challengeId = (int) $challenge->getKey();
                if (isset($selected[$challengeId])) {
                    continue;
                }
                $gate = $policy->assessSelectedChallenge(
                    $actor, $implementationRequest, $session, $evaluation, $challenge
                );
                if (($gate['eligible'] ?? false) !== true) {
                    continue;
                }
                $available[] = [
                    'id' => $challengeId,
                    'evaluation_id' => (int) $evaluation->getKey(),
                    'title' => (string) $challenge->title,
                    'details' => (string) $challenge->details,
                    'priority' => $challenge->priority === null ? null : (string) $challenge->priority,
                ];
            }
        }

        return response()->json([
            'available' => $available,
            'selected' => $candidates,
            'is_commercial_action' => false,
        ]);
    }

    public function store(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        int $challengeId,
        DataTransformationBiImplementationCandidateService $candidates
    ): JsonResponse {
        $actor = $this->admin($request);
        $this->dataBiRequest($implementationRequest);
        $companyId = (int) $implementationRequest->company_id;
        $reqId = (int) $implementationRequest->getKey();

        // Resolver el origen únicamente desde BD y restringido a la solicitud/empresa.
        $challenge = DataTransformationBiEvaluationImplementationChallenge::query()
            ->whereKey($challengeId)
            ->where('company_id', $companyId)
            ->firstOrFail();
        $evaluation = DataTransformationBiEvaluation::query()
            ->whereKey($challenge->data_transformation_bi_evaluation_id)
            ->where('company_id', $companyId)
            ->where('transformation_implementation_request_id', $reqId)
            ->firstOrFail();
        $session = DataTransformationBiIntakeSession::query()
            ->whereKey($evaluation->data_transformation_bi_intake_session_id)
            ->where('company_id', $companyId)
            ->where('transformation_implementation_request_id', $reqId)
            ->firstOrFail();

        $result = $candidates->selectChallenge(
            $actor,
            $reqId,
            (int) $session->getKey(),
            (int) $evaluation->getKey(),
            (int) $challenge->getKey()
        );

        return response()->json([
            'candidate_id' => (int) $result['candidate']->getKey(),
            'created' => (bool) $result['created'],
            'message' => $result['created']
                ? 'Reto seleccionado para revisión futura.'
                : 'Este reto ya estaba seleccionado. No se duplicó.',
        ], $result['created'] ? 201 : 200);
    }

    private function admin(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && (string) $actor->role === 'admin', 403);
        return $actor;
    }

    private function dataBiRequest(TransformationImplementationRequest $implementationRequest): void
    {
        abort_unless(
            $implementationRequest->exists
            && (string) $implementationRequest->capability_key === 'data_transformation_bi',
            404
        );
    }
}
