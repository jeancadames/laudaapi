<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * Validate and reset the user's forgotten password.
     *
     * A3 rule:
     * An invited tenant user is created with must_change_password=1.
     * Successfully consuming the password-reset link sent to that mailbox
     * proves control of the address. Only in that initial tenant-member case
     * do we mark email_verified_at automatically.
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => [
                'required',
                'string',
                Password::default(),
                'confirmed',
            ],
        ])->validate();

        $isInitialTenantAccess =
            (bool) $user->must_change_password
            && DB::table('subscriber_user')
                ->where('user_id', $user->id)
                ->where('active', 1)
                ->exists();

        /*
         * S10-F4.12-D:
         * el solicitante nuevo de Diagnosis 360 todavía no tiene tenant
         * cuando consume el link para definir su primera contraseña.
         * El workflow nativo prueba que la cuenta nació desde ese intake.
         */
        $isInitialDiagnosisAccess =
            (bool) $user->must_change_password
            && DB::table('diagnosis_access_requests')
                ->where('user_id', $user->id)
                ->where('meta->source', 'lauda360_initial_diagnosis')
                ->where('status', '!=', 'rejected')
                ->exists();

        $isInitialAccess =
            $isInitialTenantAccess || $isInitialDiagnosisAccess;

        $payload = [
            'password' => Hash::make($input['password']),
            'remember_token' => Str::random(60),
            'password_changed_at' => now(),
        ];

        if ($isInitialAccess) {
            $payload['must_change_password'] = false;

            if ($user->email_verified_at === null) {
                $payload['email_verified_at'] = now();
            }
        }

        $user->forceFill($payload)->save();
    }
}
