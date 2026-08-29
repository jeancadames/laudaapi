<?php

namespace App\Services\Diagnosis;

use App\Mail\ContactInternalNotificationMail;
use App\Mail\DiagnosisAccountAccessMail;
use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PublicDiagnosisIntakeService
{
    public const APP_HUB_URL = 'https://app.laudaapi.com';

    public const REQUEST_TYPE = 'digital_diagnosis_access_request';

    public const TOPIC = 'Solicitud de acceso al Diagnóstico LAUDA 360';

    public const INTAKE_SOURCE = 'welcome';

    /**
     * @return array{
     *   user: User,
     *   contact: ContactRequest,
     *   workflow: DiagnosisAccessRequest,
     *   account_created: bool,
     *   idempotent: bool,
     *   message: string
     * }
     */
    public function submit(array $data): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['El correo electrónico es requerido.'],
            ]);
        }

        $lockName = 'laudaapi:diag-intake:'.sha1($email);
        $lock = DB::selectOne(
            'SELECT GET_LOCK(?, 10) AS acquired',
            [$lockName]
        );

        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'email' => [
                    'La solicitud se está procesando. Intenta nuevamente en unos segundos.',
                ],
            ]);
        }

        try {
            $result = DB::transaction(
                fn (): array => $this->persistLocked($data, $email)
            );
        } finally {
            try {
                DB::selectOne(
                    'SELECT RELEASE_LOCK(?) AS released',
                    [$lockName]
                );
            } catch (\Throwable $e) {
                Log::warning(
                    'No se pudo liberar el lock del intake Diagnosis 360.',
                    [
                        'email_hash' => sha1($email),
                        'exception' => $e,
                    ]
                );
            }
        }

        $this->dispatchEmails($result);

        return $result;
    }

    private function persistLocked(array $data, string $email): array
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->lockForUpdate()
            ->first();

        if (
            $user
            && ! in_array((string) $user->role, ['user', 'subscriber'], true)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Este correo no está disponible para una solicitud de Diagnóstico 360.',
                ],
            ]);
        }

        /*
         * Idempotencia del flujo NUEVO.
         * Solo reconoce el intake App Hub nativo; no reescribe solicitudes
         * legacy de invitación.
         */
        $existingContact = ContactRequest::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('metadata->request_type', self::REQUEST_TYPE)
            ->where('metadata->diagnosis_access', 'apphub_native')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        $existingWorkflow = $existingContact
            ? DiagnosisAccessRequest::query()
                ->where('contact_request_id', $existingContact->id)
                ->where(
                    'status',
                    '!=',
                    DiagnosisAccessRequest::STATUS_REJECTED
                )
                ->latest('id')
                ->lockForUpdate()
                ->first()
            : null;

        if ($existingWorkflow) {
            $existingUser = $user;

            if (! $existingUser && $existingWorkflow->user_id) {
                $existingUser = User::query()
                    ->whereKey($existingWorkflow->user_id)
                    ->first();
            }

            if (! $existingUser) {
                throw ValidationException::withMessages([
                    'email' => [
                        'La solicitud existente requiere revisión administrativa.',
                    ],
                ]);
            }

            $meta = is_array($existingWorkflow->meta)
                ? $existingWorkflow->meta
                : [];

            return [
                'user' => $existingUser,
                'contact' => $existingContact,
                'workflow' => $existingWorkflow,
                'account_created' => (bool) data_get(
                    $meta,
                    'intake.account_created',
                    false
                ),
                'idempotent' => true,
                'message' => $this->publicMessage(),
            ];
        }

        $accountCreated = false;

        if (! $user) {
            $user = User::query()->create([
                'name' => trim((string) ($data['name'] ?? 'Cliente LAUDAAPI')),
                'email' => $email,
                /*
                 * Credencial aleatoria no conocida por nadie.
                 * El usuario define su contraseña mediante token seguro.
                 */
                'password' => Str::random(64),
                'role' => 'subscriber',
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);

            $accountCreated = true;
        }

        $incomingMetadata = is_array($data['metadata'] ?? null)
            ? $data['metadata']
            : [];

        $contactMetadata = array_merge(
            $incomingMetadata,
            [
                'source' => 'laudaapi.com',
                'request_type' => self::REQUEST_TYPE,
                'intake_type' => 'digital_transformation_360',
                'diagnosis_access' => 'apphub_native',
                'intake_source' => self::INTAKE_SOURCE,
                'apphub_user_id' => $user->id,
                'account_created' => $accountCreated,
            ]
        );

        $contact = ContactRequest::query()->create([
            'name' => trim((string) ($data['name'] ?? $user->name)),
            'email' => $email,
            'phone' => trim((string) ($data['phone'] ?? '')),
            'company' => trim((string) ($data['company'] ?? '')),
            'topic' => self::TOPIC,
            'message' => $this->nullableString($data['message'] ?? null),
            'terms' => true,
            'metadata' => $contactMetadata,
        ]);

        $workflow = DiagnosisAccessRequest::query()->create([
            'contact_request_id' => $contact->id,
            'user_id' => $user->id,
            'status' => DiagnosisAccessRequest::STATUS_PENDING,
            'meta' => [
                'source' => InitialDiagnosisCommercialService::SOURCE,
                'apphub_native' => true,
                'requested_at' => now()->toIso8601String(),
                'intake' => [
                    'source' => self::INTAKE_SOURCE,
                    'account_created' => $accountCreated,
                    'company' => $contact->company,
                    'phone' => $contact->phone,
                    'company_size' => data_get(
                        $contactMetadata,
                        'company_size'
                    ),
                    'main_challenge' => data_get(
                        $contactMetadata,
                        'main_challenge'
                    ),
                    'assistance_level' => data_get(
                        $contactMetadata,
                        'assistance_level'
                    ),
                    'context' => $contact->message,
                    'account_mail_sent_at' => null,
                ],
            ],
        ]);

        AuditService::log(
            'diagnosis_public_intake_received',
            $workflow,
            [
                'user_id' => $user->id,
                'contact_request_id' => $contact->id,
                'account_created' => $accountCreated,
                'source' => self::INTAKE_SOURCE,
            ]
        );

        return [
            'user' => $user,
            'contact' => $contact,
            'workflow' => $workflow,
            'account_created' => $accountCreated,
            'idempotent' => false,
            'message' => $this->publicMessage(),
        ];
    }

    private function dispatchEmails(array $result): void
    {
        /** @var User $user */
        $user = $result['user'];

        /** @var ContactRequest $contact */
        $contact = $result['contact'];

        /** @var DiagnosisAccessRequest $workflow */
        $workflow = $result['workflow'];

        $meta = is_array($workflow->meta) ? $workflow->meta : [];

        /*
         * Si el email de cuenta ya salió, un doble submit devuelve éxito
         * idempotente pero NO vuelve a enviarlo.
         * Si el primer envío falló, el timestamp sigue null y un retry sí
         * puede reintentarlo.
         */
        if (data_get($meta, 'intake.account_mail_sent_at')) {
            return;
        }

        $accountCreated = (bool) $result['account_created'];
        $setupUrl = null;

        if ($accountCreated && (bool) $user->must_change_password) {
            $token = Password::broker()->createToken($user);

            $setupUrl = self::APP_HUB_URL
                .'/reset-password/'
                .rawurlencode($token)
                .'?email='
                .rawurlencode((string) $user->email);
        }

        $continueUrl = self::APP_HUB_URL.'/app/diagnostico-360/entrada';

        try {
            Mail::to($user->email)->send(
                new DiagnosisAccountAccessMail(
                    contact: $contact,
                    accountCreated: $accountCreated,
                    setupUrl: $setupUrl,
                    continueUrl: $continueUrl
                )
            );

            $fresh = $workflow->fresh();
            $freshMeta = is_array($fresh->meta) ? $fresh->meta : [];
            data_set(
                $freshMeta,
                'intake.account_mail_sent_at',
                now()->toIso8601String()
            );

            $fresh->forceFill([
                'meta' => $freshMeta,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning(
                'Falló email de acceso del intake Diagnosis 360.',
                [
                    'workflow_id' => $workflow->id,
                    'contact_request_id' => $contact->id,
                    'mailer' => config('mail.default'),
                    'exception' => $e,
                ]
            );
        }

        /*
         * Notificación interna separada: si falla, no revierte el intake.
         */
        try {
            Mail::to('contacto@laudaapi.com')->send(
                new ContactInternalNotificationMail($contact)
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Falló email interno del intake Diagnosis 360.',
                [
                    'workflow_id' => $workflow->id,
                    'contact_request_id' => $contact->id,
                    'mailer' => config('mail.default'),
                    'exception' => $e,
                ]
            );
        }
    }

    private function publicMessage(): string
    {
        /*
         * Deliberadamente idéntico para cuenta nueva/existente:
         * evita enumeración pública de cuentas.
         * El correo enviado sí explica el caso correcto.
         */
        return 'Solicitud recibida. Revisa tu correo para continuar con tu cuenta LAUDAAPI y el Diagnóstico 360.';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
