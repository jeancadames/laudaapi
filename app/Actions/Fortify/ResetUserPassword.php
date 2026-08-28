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

        $payload = [
            'password' => Hash::make($input['password']),
            'remember_token' => Str::random(60),
            'password_changed_at' => now(),
        ];

        if ($isInitialTenantAccess) {
            $payload['must_change_password'] = false;

            if ($user->email_verified_at === null) {
                $payload['email_verified_at'] = now();
            }
        }

        $user->forceFill($payload)->save();
    }
}
