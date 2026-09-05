<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationDefinitionAutogenerator;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionReviewService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionRevisionService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionFunctionalClosureService;
use App\Services\Diagnosis\TransformationImplementationRequestReadyForCommercialService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AdminTransformationImplementationRequestDefinitionActionController
    extends Controller
{
    public function store(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestDefinitionService $definitions
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $definition =
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $request->user()
                );

        return back()->with(
            'success',
            "Definición funcional V{$definition->version} creada para {$definition->capability_key}."
        );
    }

    /**
     * Preparación explícita del contenido funcional.
     *
     * Crear la Definition y preparar su contenido son acciones
     * deliberadamente separadas.
     *
     * Este endpoint:
     * - NO cambia el estado del Request;
     * - NO envía la Definition al tenant;
     * - NO marca Definition ready;
     * - NO activa;
     * - NO inicia ejecución;
     * - NO inicia etapa comercial.
     */
    public function generate(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationDefinition $definition,
        TransformationImplementationDefinitionAutogenerator $autogenerator
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $this->assertDefinitionContext(
            $implementationRequest,
            $definition
        );

        if (
            $implementationRequest->status
            !== TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe estar en preparación de definición.',
                ],
            ]);
        }

        /*
         * Una vez empieza revisión humana, la Definition
         * jamás puede volver al autogenerador.
         *
         * isEditable() por sí solo no basta porque under_review
         * continúa siendo editable para nuevas revisiones humanas.
         */
        if (
            $definition->status
            !== TransformationImplementationDefinition::STATUS_DRAFT
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo una Definition en borrador puede preparar contenido autogenerado.',
                ],
            ]);
        }

        if (
            ! $definition->isEditable()
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo una Definición editable puede prepararse.',
                ],
            ]);
        }

        /*
         * Idempotencia de preparación inicial.
         *
         * Si ya existe contenido preparado no se ejecuta
         * nuevamente el generador.
         */
        if (
            $this->contentPrepared(
                $definition
            )
        ) {
            return back()->with(
                'info',
                'El contenido funcional de esta Definition ya está preparado.'
            );
        }

        $definition =
            $autogenerator->generate(
                $definition,
                $request->user()->id
            );

        return back()->with(
            'success',
            "Contenido funcional de la Definition V{$definition->version} preparado para revisión de LAUDA."
        );
    }

    /**
     * Guarda revisión humana de la Definition request-scoped.
     *
     * No marca ready y no cambia el lifecycle del Request.
     */
    public function review(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationDefinition $definition,
        TransformationImplementationRequestDefinitionReviewService $reviews
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $validated =
            $request->validate([
                'implementation_scope' =>
                    ['sometimes', 'array'],

                'deliverables' =>
                    ['sometimes', 'array', 'min:1'],

                'dependencies' =>
                    ['sometimes', 'array'],

                'responsibility_model' =>
                    ['required', 'array'],

                'responsibility_model.assignments' =>
                    ['required', 'array'],

                'responsibility_model.assignments.*' =>
                    ['required', 'array'],

                'responsibility_model.assignments.*.initiative_id' =>
                    ['required', 'string', 'max:255'],

                'responsibility_model.assignments.*.initiative_title' =>
                    ['nullable', 'string', 'max:1000'],

                'responsibility_model.assignments.*.suggested_owner_role' =>
                    ['nullable', 'string', 'max:255'],

                'responsibility_model.assignments.*.responsible_party' =>
                    ['required', 'in:lauda,client,shared'],

                'readiness' =>
                    ['required', 'array'],

                'readiness.scope_confirmed' =>
                    ['required', 'boolean'],

                'readiness.deliverables_confirmed' =>
                    ['required', 'boolean'],

                'readiness.dependencies_confirmed' =>
                    ['required', 'boolean'],

                'readiness.inputs_validated' =>
                    ['required', 'boolean'],

                'readiness.accesses_validated' =>
                    ['required', 'boolean'],

                'readiness.responsibilities_confirmed' =>
                    ['required', 'boolean'],
            ]);

        $definition =
            $reviews->saveReview(
                $implementationRequest,
                $definition,
                $validated,
                $request->user()
            );

        return back()->with(
            'success',
            "Revisión humana de la Definition V{$definition->version} guardada."
        );
    }

    /**
     * Envía la Definition funcional revisada por LAUDA
     * al Tenant Admin para su revisión.
     *
     * No marca la Definition ready y no equivale
     * a aceptación del tenant.
     */
    public function submitForTenantReview(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationDefinition $definition,
        TransformationImplementationRequestDefinitionTenantReviewService $tenantReview
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $validated =
            $request->validate([
                'notes' =>
                    ['nullable', 'string', 'max:4000'],
            ]);

        $transitioned =
            $tenantReview->submit(
                $implementationRequest,
                $definition,
                $request->user(),
                isset(
                    $validated['notes']
                )
                    ? trim(
                        (string) $validated['notes']
                    )
                    : null
            );

        return back()->with(
            'success',
            "Definition V{$definition->version} enviada a revisión de la empresa."
        );
    }

    /**
     * Crea explícitamente la siguiente versión request-scoped
     * después de que el tenant solicitó cambios.
     *
     * El navegador NO selecciona la Definition anterior:
     * siempre se resuelve la última versión desde servidor.
     */
    public function createRevision(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestDefinitionRevisionService $revisions
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        if (
            $implementationRequest->status
            !== TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe tener cambios solicitados por la empresa antes de preparar una nueva versión.',
                ],
            ]);
        }

        $previousDefinition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'company_id',
                    $implementationRequest->company_id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $implementationRequest->diagnosis_assessment_id
                )
                ->where(
                    'transformation_implementation_plan_id',
                    $implementationRequest->transformation_implementation_plan_id
                )
                ->where(
                    'transformation_implementation_phase_capability_id',
                    $implementationRequest
                        ->transformation_implementation_phase_capability_id
                )
                ->where(
                    'capability_key',
                    $implementationRequest->capability_key
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        if (! $previousDefinition) {
            throw ValidationException::withMessages([
                'definition' => [
                    'No existe una Definition presentada que pueda usarse como base de revisión.',
                ],
            ]);
        }

        $revision =
            $revisions
                ->createRevision(
                    $implementationRequest,
                    $previousDefinition,
                    $request->user()
                );

        return back()->with(
            'success',
            "Definition V{$revision->version} preparada como nueva versión de trabajo. La versión anterior permanece preservada."
        );
    }


    /**
     * Finaliza funcionalmente la Definition exacta acordada
     * por el tenant.
     *
     * El navegador NO selecciona una Definition.
     * El dominio recupera la versión acordada desde
     * definition_agreed_by_tenant.
     *
     * Este endpoint:
     * - NO cambia Request a ready_for_commercial;
     * - NO activa;
     * - NO inicia ejecución;
     * - NO crea suscripción;
     * - NO representa aceptación comercial.
     */
    public function finalizeFunctional(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestDefinitionFunctionalClosureService $functionalClosure
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $definition =
            $functionalClosure
                ->finalize(
                    $implementationRequest,
                    $request->user()
                );

        return back()->with(
            'success',
            "Definition funcional V{$definition->version} finalizada. La solicitud permanece en Definition acordada; la etapa comercial requiere una acción separada."
        );
    }


    /**
     * Marca la solicitud como lista únicamente para que una etapa
     * comercial separada pueda iniciarse posteriormente.
     *
     * El navegador NO selecciona Definition.
     *
     * El dominio resuelve y valida:
     * - Definition exacta acordada por el tenant;
     * - cierre funcional exacto de esa Definition;
     * - Definition ready.
     *
     * No crea propuesta, pricing, contrato, factura, pago,
     * suscripción, activación ni ejecución.
     */
    public function readyForCommercial(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestReadyForCommercialService $readyForCommercial
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request
        );

        $readyForCommercial
            ->markReadyForCommercial(
                $implementationRequest,
                $request->user()
            );

        return back()->with(
            'success',
            'Ciclo funcional completado. La solicitud quedó lista únicamente para iniciar posteriormente una etapa comercial separada.'
        );
    }


    private function authorizeAdmin(
        Request $request
    ): void {
        if (
            ($request->user()?->role ?? null)
            !== 'admin'
        ) {
            abort(403);
        }
    }

    private function assertDefinitionContext(
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            (int) $definition
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->id

            || (int) $definition
                ->transformation_implementation_phase_capability_id
                !== (int) $implementationRequest
                    ->transformation_implementation_phase_capability_id

            || trim(
                (string) $definition->capability_key
            )
                !== trim(
                    (string) $implementationRequest
                        ->capability_key
                )
        ) {
            abort(404);
        }
    }

    private function contentPrepared(
        TransformationImplementationDefinition $definition
    ): bool {
        return
            is_array(
                $definition->deliverables
            )
            && is_array(
                $definition->dependencies
            )
            && is_array(
                $definition->responsibility_model
            )
            && is_array(
                $definition->readiness
            )
            && in_array(
                data_get(
                    $definition->readiness,
                    'state'
                ),
                [
                    'prepared_for_review',
                    'under_review',
                ],
                true
            );
    }
}
