<?php

namespace App\Services\Billing;

use App\Models\Service;
use App\Models\ServiceBundleDiscountRule;
use App\Models\ServiceBundleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceBundleConfigurationService
{
    public function sync(Service $bundleService, array $payload): void
    {
        DB::transaction(function () use ($bundleService, $payload): void {
            $locked = Service::query()
                ->whereKey($bundleService->id)
                ->lockForUpdate()
                ->firstOrFail();

            $enabled = (bool) ($payload['enabled'] ?? false);

            if (! $enabled) {
                ServiceBundleItem::query()
                    ->where('bundle_service_id', $locked->id)
                    ->delete();

                ServiceBundleDiscountRule::query()
                    ->where('bundle_service_id', $locked->id)
                    ->where('active', true)
                    ->update(['active' => false]);

                return;
            }

            $items = $this->normalizeItems(
                $locked,
                $payload['items'] ?? []
            );

            $rules = $this->normalizeRules(
                $locked,
                $payload['rules'] ?? []
            );

            $this->validateActiveRules(
                $locked,
                $items,
                $rules
            );

            ServiceBundleItem::query()
                ->where('bundle_service_id', $locked->id)
                ->delete();

            foreach ($items as $item) {
                ServiceBundleItem::query()->create([
                    'bundle_service_id' => $locked->id,
                    'included_service_id' => $item['service_id'],
                    'required' => $item['required'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            $this->syncRules($locked, $rules);
        });
    }

    private function normalizeItems(
        Service $bundleService,
        array $items
    ): array {
        $normalized = [];
        $seen = [];

        foreach (array_values($items) as $index => $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);

            if ($serviceId < 1) {
                throw ValidationException::withMessages([
                    "bundle.items.$index.service_id" =>
                        'Debe seleccionar un Service válido.',
                ]);
            }

            if ($serviceId === (int) $bundleService->id) {
                throw ValidationException::withMessages([
                    "bundle.items.$index.service_id" =>
                        'Un bundle no puede incluirse a sí mismo.',
                ]);
            }

            if (isset($seen[$serviceId])) {
                throw ValidationException::withMessages([
                    "bundle.items.$index.service_id" =>
                        'El Service está duplicado dentro del bundle.',
                ]);
            }

            if (! Service::query()->whereKey($serviceId)->exists()) {
                throw ValidationException::withMessages([
                    "bundle.items.$index.service_id" =>
                        'El Service seleccionado no existe.',
                ]);
            }

            $seen[$serviceId] = true;

            $normalized[] = [
                'service_id' => $serviceId,
                'required' => (bool) ($item['required'] ?? false),
                'sort_order' => isset($item['sort_order'])
                    ? max(0, (int) $item['sort_order'])
                    : $index,
            ];
        }

        return $normalized;
    }

    private function normalizeRules(
        Service $bundleService,
        array $rules
    ): array {
        $normalized = [];
        $seenIds = [];
        $seenCodes = [];

        foreach (array_values($rules) as $index => $rule) {
            $id = isset($rule['id'])
                && $rule['id'] !== ''
                && $rule['id'] !== null
                    ? (int) $rule['id']
                    : null;

            if ($id !== null) {
                if (isset($seenIds[$id])) {
                    throw ValidationException::withMessages([
                        "bundle.rules.$index.id" =>
                            'La regla está duplicada.',
                    ]);
                }

                $belongs = ServiceBundleDiscountRule::query()
                    ->whereKey($id)
                    ->where(
                        'bundle_service_id',
                        $bundleService->id
                    )
                    ->exists();

                if (! $belongs) {
                    throw ValidationException::withMessages([
                        "bundle.rules.$index.id" =>
                            'La regla no pertenece a este bundle.',
                    ]);
                }

                $seenIds[$id] = true;
            }

            $code = strtoupper(trim((string) ($rule['code'] ?? '')));

            if ($code === '') {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.code" =>
                        'El código de la regla es obligatorio.',
                ]);
            }

            if (isset($seenCodes[$code])) {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.code" =>
                        'El código está duplicado en el payload.',
                ]);
            }

            $codeExists = ServiceBundleDiscountRule::query()
                ->where('code', $code)
                ->when(
                    $id !== null,
                    fn ($query) => $query->whereKeyNot($id)
                )
                ->exists();

            if ($codeExists) {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.code" =>
                        'El código de la regla ya existe.',
                ]);
            }

            $seenCodes[$code] = true;

            $name = trim((string) ($rule['name'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.name" =>
                        'El nombre de la regla es obligatorio.',
                ]);
            }

            $type = trim((string) ($rule['discount_type'] ?? ''));

            if (! in_array(
                $type,
                [
                    ServiceBundleDiscountRule::TYPE_PERCENTAGE,
                    ServiceBundleDiscountRule::TYPE_FIXED_AMOUNT,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.discount_type" =>
                        'Tipo de descuento no compatible.',
                ]);
            }

            if (! is_numeric($rule['discount_value'] ?? null)) {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.discount_value" =>
                        'El valor del descuento debe ser numérico.',
                ]);
            }

            $value = round((float) $rule['discount_value'], 4);

            if ($value < 0) {
                throw ValidationException::withMessages([
                    "bundle.rules.$index.discount_value" =>
                        'El descuento no puede ser negativo.',
                ]);
            }

            $currency = strtoupper(trim((string) ($rule['currency'] ?? '')));

            if (
                $type
                === ServiceBundleDiscountRule::TYPE_PERCENTAGE
            ) {
                if ($value > 100) {
                    throw ValidationException::withMessages([
                        "bundle.rules.$index.discount_value" =>
                            'El porcentaje no puede superar 100.',
                    ]);
                }

                $currency = null;
            } else {
                if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                    throw ValidationException::withMessages([
                        "bundle.rules.$index.currency" =>
                            'fixed_amount requiere moneda ISO de tres letras.',
                    ]);
                }

                $bundleCurrency = strtoupper(
                    trim((string) $bundleService->currency)
                );

                if ($currency !== $bundleCurrency) {
                    throw ValidationException::withMessages([
                        "bundle.rules.$index.currency" =>
                            'La moneda fixed_amount debe coincidir con la moneda del Service bundle.',
                    ]);
                }
            }

            $normalized[] = [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'discount_type' => $type,
                'discount_value' => $value,
                'currency' => $currency,
                'priority' => (int) ($rule['priority'] ?? 0),
                'active' => (bool) ($rule['active'] ?? false),
            ];
        }

        return $normalized;
    }

    private function validateActiveRules(
        Service $bundleService,
        array $items,
        array $rules
    ): void {
        $hasActiveRule = collect($rules)->contains(
            fn (array $rule): bool => $rule['active'] === true
        );

        if (! $hasActiveRule) {
            return;
        }

        if (! $bundleService->active) {
            throw ValidationException::withMessages([
                'bundle.rules' =>
                    'No puede activar reglas de un Service bundle inactivo.',
            ]);
        }

        $requiredIds = collect($items)
            ->where('required', true)
            ->pluck('service_id')
            ->values()
            ->all();

        if (count($requiredIds) < 2) {
            throw ValidationException::withMessages([
                'bundle.items' =>
                    'Una regla activa requiere al menos dos componentes required.',
            ]);
        }

        $requiredServices = Service::query()
            ->whereIn('id', $requiredIds)
            ->get(['id', 'active', 'currency']);

        if ($requiredServices->count() !== count($requiredIds)) {
            throw ValidationException::withMessages([
                'bundle.items' =>
                    'Uno o más componentes required ya no existen.',
            ]);
        }

        if ($requiredServices->contains(
            fn (Service $service): bool => ! $service->active
        )) {
            throw ValidationException::withMessages([
                'bundle.items' =>
                    'Todos los componentes required deben estar activos.',
            ]);
        }

        $bundleCurrency = strtoupper(
            trim((string) $bundleService->currency)
        );

        $wrongCurrency = $requiredServices->first(
            fn (Service $service): bool =>
                strtoupper(trim((string) $service->currency))
                !== $bundleCurrency
        );

        if ($wrongCurrency) {
            throw ValidationException::withMessages([
                'bundle.items' =>
                    'Los componentes required deben compartir moneda con el Service bundle.',
            ]);
        }
    }

    private function syncRules(
        Service $bundleService,
        array $rules
    ): void {
        $incomingIds = [];

        foreach ($rules as $rule) {
            $id = $rule['id'];
            unset($rule['id']);

            if ($id !== null) {
                $model = ServiceBundleDiscountRule::query()
                    ->whereKey($id)
                    ->where(
                        'bundle_service_id',
                        $bundleService->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $model->update($rule);
                $incomingIds[] = (int) $model->id;
                continue;
            }

            $model = ServiceBundleDiscountRule::query()->create([
                'bundle_service_id' => $bundleService->id,
                ...$rule,
            ]);

            $incomingIds[] = (int) $model->id;
        }

        ServiceBundleDiscountRule::query()
            ->where('bundle_service_id', $bundleService->id)
            ->when(
                $incomingIds !== [],
                fn ($query) =>
                    $query->whereNotIn('id', $incomingIds)
            )
            ->where('active', true)
            ->update(['active' => false]);
    }
}
