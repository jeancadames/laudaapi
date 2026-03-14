<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LaunchController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            throw new HttpException(400, 'Token de lanzamiento requerido.');
        }

        $payload = $this->decodeToken($token);

        $this->validatePayload($payload);

        $user = DB::transaction(function () use ($payload) {
            $company = $this->upsertCompanyContext($payload);
            $user = $this->upsertUserFromLaunch($payload, $company);

            return $user;
        });

        Auth::login($user, false);

        session([
            'launch.company_id' => data_get($payload, 'company.id'),
            'launch.company_name' => data_get($payload, 'company.name'),
            'launch.subscriber_id' => data_get($payload, 'company.subscriber_id'),
            'launch.service_slug' => data_get($payload, 'service.slug'),
            'launch.service_key' => data_get($payload, 'service.service_key'),
            'launch.mode' => data_get($payload, 'service.config.mode', 'corporate'),
            'launch.launched_via_sso' => true,
        ]);

        return redirect()->route('dashboard');
    }

    private function decodeToken(string $token): array
    {
        try {
            $json = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            throw new HttpException(401, 'Token inválido o no se pudo desencriptar.');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new HttpException(401, 'Payload de lanzamiento inválido.');
        }

        return $payload;
    }

    private function validatePayload(array $payload): void
    {
        $exp = (int) data_get($payload, 'exp', 0);
        $sub = (string) data_get($payload, 'sub', '');
        $serviceSlug = (string) data_get($payload, 'service.slug', '');
        $companyId = (int) data_get($payload, 'company.id', 0);
        $userEmail = (string) data_get($payload, 'user.email', '');

        if ($exp <= 0 || now()->timestamp >= $exp) {
            throw new HttpException(401, 'Token expirado.');
        }

        if ($sub === '' || $serviceSlug === '' || $companyId <= 0 || $userEmail === '') {
            throw new HttpException(422, 'Token de lanzamiento incompleto.');
        }
    }

    private function upsertCompanyContext(array $payload): WorkspaceCompany
    {
        $mode = (string) (data_get($payload, 'service.config.mode') ?? data_get($payload, 'company.mode') ?? 'corporate');

        $externalCompanyId = (int) data_get($payload, 'company.id');
        $subscriberId = (int) data_get($payload, 'company.subscriber_id');
        $name = (string) (data_get($payload, 'company.name') ?? 'Empresa');

        $company = WorkspaceCompany::query()
            ->where('external_company_id', $externalCompanyId)
            ->first();

        if (! $company) {
            $company = WorkspaceCompany::create([
                'external_company_id' => $externalCompanyId,
                'subscriber_id' => $subscriberId,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $externalCompanyId,
                'is_active' => true,
            ]);
        } else {
            $company->update([
                'subscriber_id' => $subscriberId,
                'name' => $name,
                'is_active' => true,
            ]);
        }

        return $company;
    }

    private function upsertUserFromLaunch(array $payload, WorkspaceCompany $company): User
    {
        $externalUserId = (int) data_get($payload, 'user.id');
        $name = (string) (data_get($payload, 'user.name') ?? 'Usuario');
        $email = (string) data_get($payload, 'user.email');
        $role = (string) (data_get($payload, 'user.role') ?? 'subscriber');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => now(),
                'external_user_id' => $externalUserId,
                'workspace_company_id' => $company->id,
                'role' => $role,
            ]);
        } else {
            $user->update([
                'name' => $name,
                'external_user_id' => $externalUserId,
                'workspace_company_id' => $company->id,
                'role' => $role,
            ]);
        }

        return $user;
    }
}
