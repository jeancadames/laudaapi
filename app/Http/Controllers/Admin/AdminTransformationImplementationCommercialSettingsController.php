<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnosis\TransformationImplementationCommercialMatrixService;
use App\Services\Diagnosis\TransformationImplementationModalityCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransformationImplementationCommercialSettingsController
    extends Controller
{
    public function show(
        TransformationImplementationCommercialMatrixService $matrixService,
        TransformationImplementationModalityCatalog $catalog
    ): Response {
        $matrix =
            $matrixService->current();

        return Inertia::render(
            'Admin/Transformation360/CommercialSettings',
            [
                'matrix' =>
                    $matrix,

                'readiness' =>
                    $matrixService->readiness(
                        $matrix
                    ),

                'modality_options' =>
                    collect(
                        $catalog->all()
                    )
                        ->map(
                            fn (
                                array $definition,
                                string $key
                            ): array => [
                                'key' =>
                                    $key,

                                'label' =>
                                    $definition['label']
                                    ?? $key,

                                'summary' =>
                                    $definition['summary']
                                    ?? null,
                            ]
                        )
                        ->values()
                        ->all(),

                'effort_labels' => [
                    'low' =>
                        'Esfuerzo bajo',

                    'medium' =>
                        'Esfuerzo medio',

                    'high' =>
                        'Esfuerzo alto',
                ],

                'professional_labels' => [
                    'procedures_guide' =>
                        'Guía de Procesos y Procedimientos',

                    'branding_identity' =>
                        'Branding e Identidad Digital',
                ],

                'endpoints' => [
                    'update' =>
                        route(
                            'admin.transformation360.commercial_settings.update'
                        ),
                ],
            ]
        );
    }

    public function update(
        Request $request,
        TransformationImplementationCommercialMatrixService $matrixService
    ): RedirectResponse {
        $matrixService->save(
            (array) $request->input(
                'modalities',
                []
            ),
            $request->user()?->id
        );

        return back()->with(
            'success',
            'Matriz comercial de Transformación 360 actualizada.'
        );
    }
}
