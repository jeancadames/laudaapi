<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => $this->getStats(),
            'recentRequests' => $this->recentRequests(),
        ]);
    }

    protected function getStats(): array
    {
        $contactBase = $this->diagnosisContactsQuery();

        $requestsTotal = (clone $contactBase)->count();

        $pending = (clone $contactBase)
            ->leftJoin(
                'diagnosis_access_requests as dar',
                'dar.contact_request_id',
                '=',
                'contact_requests.id'
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('dar.id')
                    ->orWhere('dar.status', DiagnosisAccessRequest::STATUS_PENDING);
            })
            ->count('contact_requests.id');

        $workflowByStatus = DiagnosisAccessRequest::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $assessmentByStatus = DiagnosisAssessment::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $underReview = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_UNDER_REVIEW] ?? 0);
        $moreInfo = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_MORE_INFO_REQUIRED] ?? 0);
        $approved = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_APPROVED] ?? 0);
        $invited = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_INVITED] ?? 0);
        $active = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_ACTIVE] ?? 0);
        $rejected = (int) ($workflowByStatus[DiagnosisAccessRequest::STATUS_REJECTED] ?? 0);

        $draft = (int) ($assessmentByStatus['draft'] ?? 0);
        $inProgress = (int) ($assessmentByStatus['in_progress'] ?? 0);
        $submitted = (int) ($assessmentByStatus['submitted'] ?? 0);
        $reviewed = (int) ($assessmentByStatus['reviewed'] ?? 0);

        return [
            'requests' => [
                'total' => (int) $requestsTotal,
                'pending' => (int) $pending,
                'under_review' => $underReview,
                'more_info_required' => $moreInfo,
                'rejected' => $rejected,
                'review_queue' => (int) $pending + $underReview + $moreInfo,
            ],

            'access' => [
                'approved' => $approved,
                'invited' => $invited,
                'active' => $active,
                'invitation_pipeline' => $approved + $invited,
            ],

            'assessments' => [
                'draft' => $draft,
                'in_progress' => $inProgress,
                'submitted' => $submitted,
                'reviewed' => $reviewed,
                'completed' => $submitted + $reviewed,
                'results_to_review' => $submitted,
            ],
        ];
    }

    protected function recentRequests(): array
    {
        return DB::table('contact_requests as c')
            ->leftJoin(
                'diagnosis_access_requests as dar',
                'dar.contact_request_id',
                '=',
                'c.id'
            )
            ->leftJoin(
                'diagnosis_assessments as da',
                'da.id',
                '=',
                'dar.diagnosis_assessment_id'
            )
            ->where(function ($query): void {
                $query
                    ->whereIn('c.topic', [
                        'Solicitud de acceso al Diagnóstico LAUDA 360',
                        'Solicitud de Diagnóstico Digital 360',
                    ])
                    ->orWhereIn('c.metadata->request_type', [
                        'digital_diagnosis_access_request',
                        'digital_transformation_diagnosis',
                    ]);
            })
            ->orderByDesc('c.id')
            ->limit(8)
            ->get([
                'c.id',
                'c.name',
                'c.company',
                'c.email',
                'c.created_at',
                'dar.status as workflow_status',
                'da.status as assessment_status',
                'da.maturity_score',
            ])
            ->map(function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'company' => (string) ($row->company ?? ''),
                    'email' => (string) ($row->email ?? ''),
                    'status' => (string) (
                        $row->workflow_status
                        ?: DiagnosisAccessRequest::STATUS_PENDING
                    ),
                    'assessment_status' => $row->assessment_status
                        ? (string) $row->assessment_status
                        : null,
                    'maturity_score' => $row->maturity_score !== null
                        ? (float) $row->maturity_score
                        : null,
                    'created_at' => $row->created_at
                        ? (string) $row->created_at
                        : null,
                    'href' => route(
                        'admin.diagnosis_requests.show',
                        ['contact' => $row->id],
                        false
                    ),
                ];
            })
            ->values()
            ->all();
    }

    protected function diagnosisContactsQuery(): Builder
    {
        return ContactRequest::query()
            ->where(function ($query): void {
                $query
                    ->whereIn('topic', [
                        'Solicitud de acceso al Diagnóstico LAUDA 360',
                        'Solicitud de Diagnóstico Digital 360',
                    ])
                    ->orWhereIn('metadata->request_type', [
                        'digital_diagnosis_access_request',
                        'digital_transformation_diagnosis',
                    ]);
            });
    }
}
