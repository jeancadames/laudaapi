<?php

namespace App\Services\Commercial;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmapOrder;
use App\Models\DiagnosisExpandedReportOrder;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialCustomerProvisioningService
{
    /**
     * Asegura la identidad comercial del cliente de un diagnóstico.
     *
     * IMPORTANTE:
     * - NO crea User.
     * - NO crea Subscription.
     * - NO crea SubscriptionItem.
     * - NO inicia trial.
     *
     * @return array{
     *   user: User,
     *   subscriber: Subscriber,
     *   company: Company,
     *   source: string,
     *   created: array{subscriber: bool, company: bool, pivot: bool}
     * }
     */
    public function ensureForAssessment(
        DiagnosisAssessment $assessment
    ): array {
        $workflow = DiagnosisAccessRequest::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->with([
                'user',
                'contactRequest',
            ])
            ->first();

        if (! $workflow || ! $workflow->user) {
            throw ValidationException::withMessages([
                'user' =>
                    'El diagnóstico debe tener un usuario cliente vinculado antes del provisioning comercial.',
            ]);
        }

        $user = $workflow->user;

        $companyName = trim((string) (
            $workflow->contactRequest?->company
            ?: $assessment->organization_name
            ?: $workflow->contactRequest?->name
            ?: $user->name
        ));

        if ($companyName === '') {
            throw ValidationException::withMessages([
                'company' =>
                    'No se pudo determinar el nombre comercial de la empresa.',
            ]);
        }

        return $this->ensure(
            $user,
            $companyName,
            $assessment
        );
    }

    /**
     * @return array{
     *   user: User,
     *   subscriber: Subscriber,
     *   company: Company,
     *   source: string,
     *   created: array{subscriber: bool, company: bool, pivot: bool}
     * }
     */
    public function ensure(
        User $user,
        string $companyName,
        ?DiagnosisAssessment $assessment = null
    ): array {
        $companyName = trim($companyName);

        if ($companyName === '') {
            throw ValidationException::withMessages([
                'company' => 'El nombre de la empresa es obligatorio.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $companyName,
            $assessment
        ): array {
            /*
             * PASO 9E-A:
             * User es el mutex comercial del provisioning.
             *
             * Dos requests del mismo cliente se serializan aquí antes
             * de resolver/crear Subscriber, pivot y Company.
             */
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $subscriber = null;
            $company = null;
            $source = null;
            $subscriberCreated = false;
            $companyCreated = false;
            $pivotCreated = false;

            if ($assessment) {
                [$subscriber, $company] =
                    $this->resolveFromAssessmentCommercialHistory(
                        $assessment
                    );

                if ($subscriber) {
                    $source = 'diagnosis_commercial_history';
                }
            }

            if (! $subscriber) {
                $activeSubscriberIds = DB::table('subscriber_user')
                    ->where('user_id', $user->id)
                    ->where('active', 1)
                    ->orderBy('subscriber_id')
                    ->pluck('subscriber_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($activeSubscriberIds->count() > 1) {
                    throw ValidationException::withMessages([
                        'subscriber' =>
                            'El usuario pertenece a varios Subscribers activos. Debe identificarse explícitamente cuál corresponde a este diagnóstico.',
                    ]);
                }

                if ($activeSubscriberIds->count() === 1) {
                    $subscriber = Subscriber::query()
                        ->whereKey(
                            $activeSubscriberIds->first()
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($subscriber) {
                        $source = 'user_active_subscriber';
                    }
                }
            }

            if ($subscriber && ! $subscriber->active) {
                throw ValidationException::withMessages([
                    'subscriber' =>
                        'El Subscriber comercial existente está inactivo y no puede reutilizarse automáticamente.',
                ]);
            }

            if (! $subscriber) {
                $subscriber = Subscriber::query()->create([
                    'name' => $companyName,
                    'slug' => $this->uniqueSlug(
                        'subscribers',
                        'slug',
                        Str::slug($companyName) ?: 'subscriber'
                    ),
                    'country_code' => 'DO',
                    'currency' => 'DOP',
                    'timezone' =>
                        'America/Santo_Domingo',
                    'provider' => null,
                    'provider_mode' => 'live',
                    'provider_customer_id' => null,
                    'active' => true,
                    'meta' => [
                        'source' =>
                            'commercial_customer_provisioning',
                        'diagnosis_assessment_id' =>
                            $assessment?->id,
                    ],
                ]);

                $subscriberCreated = true;
                $source = 'created';
            }

            $pivot = DB::table('subscriber_user')
                ->where(
                    'subscriber_id',
                    $subscriber->id
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

            if (! $pivot) {
                DB::table('subscriber_user')->insert([
                    'subscriber_id' => $subscriber->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $pivotCreated = true;
            } elseif (! (bool) $pivot->active) {
                DB::table('subscriber_user')
                    ->where(
                        'subscriber_id',
                        $subscriber->id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->update([
                        'active' => true,
                        'updated_at' => now(),
                    ]);
            }

            if ($company) {
                if (
                    (int) $company->subscriber_id
                    !== (int) $subscriber->id
                ) {
                    throw ValidationException::withMessages([
                        'company' =>
                            'La Company comercial histórica no pertenece al Subscriber resuelto.',
                    ]);
                }
            } else {
                $company = Company::query()
                    ->where(
                        'subscriber_id',
                        $subscriber->id
                    )
                    ->lockForUpdate()
                    ->first();
            }

            if (! $company) {
                $company = Company::query()->create([
                    'name' => $companyName,
                    'slug' => $this->uniqueSlug(
                        'companies',
                        'slug',
                        Str::slug($companyName) ?: 'company'
                    ),
                    'currency' =>
                        $subscriber->currency ?: 'DOP',
                    'timezone' =>
                        $subscriber->timezone
                        ?: 'America/Santo_Domingo',
                    'owner_user_id' => $user->id,
                    'subscriber_id' => $subscriber->id,
                    'active' => true,
                ]);

                $companyCreated = true;
            } else {
                if (! $company->active) {
                    throw ValidationException::withMessages([
                        'company' =>
                            'La Company comercial existente está inactiva y no puede reutilizarse automáticamente.',
                    ]);
                }

                if (! $company->owner_user_id) {
                    $company->forceFill([
                        'owner_user_id' => $user->id,
                    ])->save();
                }
            }

            return [
                'user' => $user,
                'subscriber' => $subscriber->fresh(),
                'company' => $company->fresh(),
                'source' => $source ?? 'resolved',
                'created' => [
                    'subscriber' => $subscriberCreated,
                    'company' => $companyCreated,
                    'pivot' => $pivotCreated,
                ],
            ];
        }, 3);
    }

    /**
     * Prioridad:
     * 1) Roadmap comercial del mismo diagnóstico.
     * 2) Informe Ampliado comercial del mismo diagnóstico.
     *
     * @return array{0: ?Subscriber, 1: ?Company}
     */
    private function resolveFromAssessmentCommercialHistory(
        DiagnosisAssessment $assessment
    ): array {
        $pairs = [
            DiagnosisDetailedRoadmapOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->whereNotNull('subscriber_id')
                ->whereNotNull('company_id')
                ->latest('id')
                ->first([
                    'subscriber_id',
                    'company_id',
                ]),
            DiagnosisExpandedReportOrder::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->whereNotNull('subscriber_id')
                ->whereNotNull('company_id')
                ->latest('id')
                ->first([
                    'subscriber_id',
                    'company_id',
                ]),
        ];

        foreach ($pairs as $pair) {
            if (! $pair) {
                continue;
            }

            $subscriber = Subscriber::query()
                ->whereKey($pair->subscriber_id)
                ->first();

            $company = Company::query()
                ->whereKey($pair->company_id)
                ->first();

            if (! $subscriber || ! $company) {
                throw ValidationException::withMessages([
                    'commercial_history' =>
                        'El diagnóstico contiene una referencia comercial incompleta a Subscriber/Company.',
                ]);
            }

            if (
                (int) $company->subscriber_id
                !== (int) $subscriber->id
            ) {
                throw ValidationException::withMessages([
                    'commercial_history' =>
                        'El Subscriber y la Company del historial comercial del diagnóstico no coinciden.',
                ]);
            }

            return [
                $subscriber,
                $company,
            ];
        }

        return [
            null,
            null,
        ];
    }

    private function uniqueSlug(
        string $table,
        string $column,
        string $base
    ): string {
        $base = trim($base) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where($column, $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
