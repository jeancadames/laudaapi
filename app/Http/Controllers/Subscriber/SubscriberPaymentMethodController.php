<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubscriberPaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);

        if (!$company) {
            return redirect()->route('subscriber')
                ->with('error', 'No tienes empresa asignada todavía. Completa tu activación primero.');
        }

        $methods = PaymentMethod::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get([
                'id',
                'company_id',
                'type',
                'provider',
                'name',
                'currency',
                'status',
                'mode',
                'is_default',
                'sort_order',
                'bank_name',
                'bank_account_holder',
                'bank_account_number',
                'bank_account_type',
                'bank_branch',
                'bank_swift',
                'bank_iban',
                'config',
                'instructions',
                'created_at',
                'updated_at',
            ]);

        return Inertia::render('Subscriber/PaymentMethods/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'active' => (bool) $company->active,
            ],
            'paymentMethods' => $methods,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        $data = $this->validatePayload($request);

        // Normalización
        $data = $this->normalizePayload($data);

        try {
            $result = DB::transaction(function () use ($company, $user, $data) {
                // Si este será default, apaga los demás
                if (!empty($data['is_default'])) {
                    PaymentMethod::query()
                        ->where('company_id', $company->id)
                        ->update(['is_default' => false]);
                }

                $pm = PaymentMethod::create([
                    'company_id' => $company->id,
                    'created_by_user_id' => $user->id,

                    'type' => $data['type'],
                    'provider' => $data['provider'] ?? null,
                    'name' => $data['name'],
                    'currency' => $data['currency'],

                    'status' => $data['status'],
                    'mode' => $data['mode'],
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),

                    // bank_transfer fields
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_type' => $data['bank_account_type'] ?? null,
                    'bank_branch' => $data['bank_branch'] ?? null,
                    'bank_swift' => $data['bank_swift'] ?? null,
                    'bank_iban' => $data['bank_iban'] ?? null,

                    // config
                    'credentials' => $data['credentials'] ?? null,
                    'config' => $data['config'] ?? null,
                    'instructions' => $data['instructions'] ?? null,
                ]);

                return $pm;
            });

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

            AuditService::log('subscriber_payment_method_created', $result, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'payment_method_id' => $result->id,
                'type' => $result->type,
                'provider' => $result->provider,
                'is_default' => (bool) $result->is_default,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Método de pago creado.');
        } catch (\Throwable $e) {
            AuditService::log('subscriber_payment_method_create_failed', null, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'No se pudo crear el método de pago: ' . $e->getMessage());
        }
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        // ✅ Multi-tenant guard
        if ((int) $paymentMethod->company_id !== (int) $company->id) abort(403);

        $data = $this->validatePayload($request, true);
        $data = $this->normalizePayload($data);

        try {
            DB::transaction(function () use ($company, $paymentMethod, $data) {
                if (!empty($data['is_default'])) {
                    PaymentMethod::query()
                        ->where('company_id', $company->id)
                        ->where('id', '!=', $paymentMethod->id)
                        ->update(['is_default' => false]);
                }

                $paymentMethod->update([
                    'type' => $data['type'] ?? $paymentMethod->type,
                    'provider' => $data['provider'] ?? $paymentMethod->provider,
                    'name' => $data['name'] ?? $paymentMethod->name,
                    'currency' => $data['currency'] ?? $paymentMethod->currency,

                    'status' => $data['status'] ?? $paymentMethod->status,
                    'mode' => $data['mode'] ?? $paymentMethod->mode,
                    'is_default' => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $paymentMethod->is_default,
                    'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $paymentMethod->sort_order,

                    // bank_transfer fields
                    'bank_name' => $data['bank_name'] ?? $paymentMethod->bank_name,
                    'bank_account_holder' => $data['bank_account_holder'] ?? $paymentMethod->bank_account_holder,
                    'bank_account_number' => $data['bank_account_number'] ?? $paymentMethod->bank_account_number,
                    'bank_account_type' => $data['bank_account_type'] ?? $paymentMethod->bank_account_type,
                    'bank_branch' => $data['bank_branch'] ?? $paymentMethod->bank_branch,
                    'bank_swift' => $data['bank_swift'] ?? $paymentMethod->bank_swift,
                    'bank_iban' => $data['bank_iban'] ?? $paymentMethod->bank_iban,

                    // config
                    'credentials' => array_key_exists('credentials', $data) ? ($data['credentials'] ?? null) : $paymentMethod->credentials,
                    'config' => array_key_exists('config', $data) ? ($data['config'] ?? null) : $paymentMethod->config,
                    'instructions' => array_key_exists('instructions', $data) ? ($data['instructions'] ?? null) : $paymentMethod->instructions,
                ]);
            });

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

            AuditService::log('subscriber_payment_method_updated', $paymentMethod, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Método de pago actualizado.');
        } catch (\Throwable $e) {
            AuditService::log('subscriber_payment_method_update_failed', $paymentMethod, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'No se pudo actualizar: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        // ✅ Multi-tenant guard
        if ((int) $paymentMethod->company_id !== (int) $company->id) abort(403);

        try {
            DB::transaction(function () use ($company, $paymentMethod) {
                $wasDefault = (bool) $paymentMethod->is_default;

                $paymentMethod->delete();

                // Si borraste el default, intenta promover otro activo (opcional)
                if ($wasDefault) {
                    $next = PaymentMethod::query()
                        ->where('company_id', $company->id)
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderByDesc('id')
                        ->first();

                    if ($next) {
                        $next->is_default = true;
                        $next->save();
                    }
                }
            });

            Cache::forget("subscriber.dashboard.stats.company.{$company->id}.user.{$user->id}");

            AuditService::log('subscriber_payment_method_deleted', $paymentMethod, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod->id,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Método de pago eliminado.');
        } catch (\Throwable $e) {
            AuditService::log('subscriber_payment_method_delete_failed', $paymentMethod, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            report($e);
            return back()->with('error', 'No se pudo eliminar: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        // En update, permitimos partial payload
        $requiredIfCreate = $isUpdate ? 'sometimes' : 'required';

        // Nota: credentials/config se aceptan como array (JSON) desde frontend.
        // En producción: considera cifrar y/o guardar references.
        return $request->validate([
            'type' => [$requiredIfCreate, 'in:gateway,bank_transfer,cash,check,other'],
            'provider' => ['nullable', 'string', 'max:50'],
            'name' => [$requiredIfCreate, 'string', 'max:120'],
            'currency' => [$requiredIfCreate, 'string', 'size:3'],

            'status' => [$requiredIfCreate, 'in:active,inactive'],
            'mode' => [$requiredIfCreate, 'in:test,live'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // bank_transfer details (opcionales, pero recomendados)
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_account_type' => ['nullable', 'string', 'max:30'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'bank_swift' => ['nullable', 'string', 'max:20'],
            'bank_iban' => ['nullable', 'string', 'max:40'],

            // config
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function normalizePayload(array $data): array
    {
        if (isset($data['provider'])) {
            $data['provider'] = strtolower(trim((string) $data['provider'])) ?: null;
        }

        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(trim((string) $data['currency']));
        }

        if (isset($data['mode'])) {
            $data['mode'] = strtolower(trim((string) $data['mode']));
        }

        if (isset($data['status'])) {
            $data['status'] = strtolower(trim((string) $data['status']));
        }

        // Limpieza de strings opcionales
        foreach (
            [
                'bank_name',
                'bank_account_holder',
                'bank_account_number',
                'bank_account_type',
                'bank_branch',
                'bank_swift',
                'bank_iban',
            ] as $k
        ) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) ($data[$k] ?? ''));
                $data[$k] = $v !== '' ? $v : null;
            }
        }

        // Si no es bank_transfer, no fuerces campos bancarios
        if (($data['type'] ?? null) && $data['type'] !== 'bank_transfer') {
            // opcional: podrías nullear esos campos para evitar basura
            // pero en update parcial quizá no quieres tocarlos.
        }

        return $data;
    }

    /**
     * ✅ Resolver Company de forma segura.
     * Orden:
     * 1) user->company_id
     * 2) companies.owner_user_id
     * 3) companies.subscriber_id por pivot subscriber_user activo o user->subscriber_id (fallback)
     */
    private function resolveCompanyForUser(int $userId, $userCompanyId, $userSubscriberId): ?Company
    {
        if (!empty($userCompanyId)) {
            $c = Company::query()->find((int) $userCompanyId);
            if ($c) return $c;
        }

        $c = Company::query()->where('owner_user_id', $userId)->first();
        if ($c) return $c;

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->value('subscriber_id');

        if ($subscriberId <= 0 && !empty($userSubscriberId)) {
            $subscriberId = (int) $userSubscriberId;
        }

        if ($subscriberId > 0) {
            return Company::query()->where('subscriber_id', $subscriberId)->first();
        }

        return null;
    }
}
