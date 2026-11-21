<?php

namespace App\Services\BusinessIdentity;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\LaudaErp\ServiceAccessResolver;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class CentralIdentityService
{
    public function __construct(private CentralMembership $membership, private IdentityRegistry $registry) {}

    public function start(User $user, Company $company, string $service, bool $manageCompany, bool $restart = false): array
    {
        $peer = $this->peer($service);

        return DB::transaction(function () use ($user, $company, $service, $manageCompany, $peer, $restart): array {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $this->membership->authorize($user->fresh(), $company, $manageCompany);
            abort_unless(Service::where('service_key', $service)->where('active', true)->exists(), 404, 'Servicio no disponible.');
            $link = $this->registry->reserve($service, $company->id, $company->subscriber_id);
            abort_unless($manageCompany || ($link->active && $link->local_company_id), 403, 'Un propietario o administrador debe vincular primero la empresa.');
            $intents = DB::table('business_identity_intents')->where('link_id', $link->id)->where('central_user_id', $user->id);
            $pending = (clone $intents)->whereNull('completed_at')->where('expires_at', '>', now()->timestamp)->orderByDesc('generation')->first();
            $mode = $manageCompany ? 'company' : 'member';
            if (! $restart && $pending?->request_ciphertext && json_decode($pending->payload, true)['mode'] === $mode) {
                return $this->requestResult(Crypt::decryptString($pending->request_ciphertext), $peer['url'], $service, (int) $pending->expires_at);
            }
            $unfinished = (clone $intents)->whereNotNull('completed_at')->where('expires_at', '>', now()->timestamp)->orderByDesc('generation')->first();
            if (! $restart && $unfinished?->activation_ciphertext) {
                $member = DB::table('business_identity_members')->where('link_id', $link->id)->where('central_user_id', $user->id)->first();
                if ($member?->active && $member->ready_at === null && (int) $member->generation === (int) $unfinished->generation) {
                    $payload = json_decode($unfinished->payload, true, 32, JSON_THROW_ON_ERROR);
                    $this->membership->authorize($user->fresh(), $company, $payload['mode'] === 'company');

                    return $this->activationResult(Crypt::decryptString($unfinished->activation_ciphertext), $peer['url'], $service, (int) $unfinished->expires_at);
                }
            }
            $generation = (int) (clone $intents)->max('generation') + 1;
            (clone $intents)->whereNull('completed_at')->update(['expires_at' => now()->timestamp]);
            $data = [
                'link_id' => $link->id, 'service' => $service, 'mode' => $mode, 'generation' => $generation,
                'central_company_name' => $company->name, 'central_user_name' => $user->name, 'central_user_email' => $user->email,
                'central_company_id' => (int) $company->id, 'central_subscriber_id' => (int) $company->subscriber_id,
                'central_user_id' => (int) $user->id, 'local_company_id' => $link->local_company_id === null ? null : (int) $link->local_company_id,
            ];
            $codec = new IdentityToken($peer['secret']);
            $token = $codec->encode($this->issuer(), $service, 'link-request', $data, now()->timestamp, 600);
            $claims = $codec->decode($token, $this->issuer(), $service, 'link-request', now()->timestamp);
            DB::table('business_identity_intents')->insert([
                'nonce' => $claims['nonce'], 'link_id' => $link->id, 'central_user_id' => $user->id,
                'generation' => $generation, 'request_ciphertext' => Crypt::encryptString($token),
                'payload' => json_encode($data, JSON_THROW_ON_ERROR), 'expires_at' => $claims['exp'], 'created_at' => now(),
            ]);

            return $this->requestResult($token, $peer['url'], $service, $claims['exp']);
        }, 3);
    }

    public function complete(User $user, string $service, string $token, bool $review = false): array
    {
        $peer = $this->peer($service);
        $claims = (new IdentityToken($peer['secret']))->decode($token, $service, $this->issuer(), 'link-proof', now()->timestamp);
        $data = $claims['data'];
        IdentityToken::assertBinding($data);
        abort_unless($data['service'] === $service && $data['central_user_id'] === (int) $user->id, 403);

        return DB::transaction(function () use ($user, $data, $token, $peer, $claims, $review): array {
            $company = Company::query()->lockForUpdate()->findOrFail($data['central_company_id']);
            $intent = DB::table('business_identity_intents')->where('nonce', $data['consent'])
                ->where('central_user_id', $user->id)->lockForUpdate()->first();
            abort_unless($intent && (int) $intent->expires_at > now()->timestamp, 409, 'La solicitud de vinculación venció o no pertenece a esta cuenta.');
            $expected = json_decode($intent->payload, true, 32, JSON_THROW_ON_ERROR);
            foreach ($expected as $key => $value) {
                if ($key !== 'local_company_id' || $value !== null) {
                    abort_unless(($data[$key] ?? null) === $value, 409, 'La confirmación no corresponde a la solicitud original.');
                }
            }
            abort_unless((int) $company->subscriber_id === $data['central_subscriber_id'], 409);
            $this->membership->authorize($user->fresh(), $company, $expected['mode'] === 'company');
            $this->registry->assertAvailable($data);
            if ($review) {
                return [...IdentityFlow::summary($data, min($claims['exp'], (int) $intent->expires_at)), 'cancel_url' => route('business.identity.cancel', $data['consent'], false)];
            }
            if ($intent->completed_at !== null) {
                abort_unless(hash_equals($intent->proof_hash, hash('sha256', $token)), 409);
                $this->registry->assertCurrent($data);

                return $this->activationResult(Crypt::decryptString($intent->activation_ciphertext), $peer['url'], $data['service'], (int) $intent->expires_at);
            }
            $this->registry->bind($data, $expected['mode'] === 'company');
            $activation = (new IdentityToken($peer['secret']))->encode($this->issuer(), $data['service'], 'link-activation', $data, now()->timestamp, (int) $intent->expires_at - now()->timestamp);
            DB::table('business_identity_intents')->where('nonce', $data['consent'])->update([
                'activation_ciphertext' => Crypt::encryptString($activation),
                'proof_hash' => hash('sha256', $token), 'completed_at' => now(),
            ]);

            return $this->activationResult($activation, $peer['url'], $data['service'], (int) $intent->expires_at);
        }, 3);
    }

    public function launch(User $user, Company $company, Service $service): string
    {
        $key = (string) $service->service_key;
        $peer = $this->peer($key);
        $company = $company->fresh();
        $user = $user->fresh();
        $this->membership->authorize($user, $company);
        abort_unless(app(ServiceAccessResolver::class)->userCanAccess($user, $company, $service->fresh()), 403, 'No tienes acceso comercial a este servicio.');
        $data = $this->registry->binding($key, $company->id, $user->id);
        abort_unless($data['central_subscriber_id'] === (int) $company->subscriber_id, 409);
        abort_unless(DB::table('business_identity_members')->where('link_id', $data['link_id'])->where('central_user_id', $user->id)->whereNotNull('ready_at')->exists(), 409, 'Finaliza la vinculación antes de abrir la aplicación.');
        $token = (new IdentityToken($peer['secret']))->encode($this->issuer(), $key, 'launch', $data, now()->timestamp, 60);

        return IdentityFlow::handoff($peer['url'], 'launch', $key, $token);
    }

    public function revoke(User $user, Company $company, string $service, bool $wholeCompany): void
    {
        abort_unless(in_array($service, IdentityToken::SERVICES, true), 404);
        DB::transaction(function () use ($user, $company, $service, $wholeCompany): void {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $this->membership->authorize($user->fresh(), $company, $wholeCompany);
            $link = DB::table('business_identity_links')->where('service', $service)->where('central_company_id', $company->id)->lockForUpdate()->first();
            abort_unless($link, 404);
            $this->registry->revoke(['link_id' => $link->id, 'central_user_id' => (int) $user->id], $wholeCompany);
            DB::table('business_identity_intents')->where('link_id', $link->id)
                ->when(! $wholeCompany, fn ($query) => $query->where('central_user_id', $user->id))
                ->whereNull('completed_at')->update(['expires_at' => now()->timestamp]);
        }, 3);
    }

    public function peer(string $service): array
    {
        abort_unless(config('business_identity.enabled') && in_array($service, IdentityToken::SERVICES, true), 503, 'La identidad Business todavía no está habilitada.');
        $peer = config('business_identity.peers.'.$service, []);
        try {
            $url = IdentityFlow::origin((string) ($peer['url'] ?? ''));
            IdentityFlow::origin($this->issuer());
        } catch (\InvalidArgumentException $error) {
            abort(503, $error->getMessage());
        }
        $secret = (string) ($peer['secret'] ?? '');
        abort_unless(strlen($secret) >= 32 && $this->issuer() !== '', 503, 'Falta la configuración de identidad Business.');

        return ['url' => $url, 'secret' => $secret];
    }

    public function ready(User $user, string $service, string $token): array
    {
        $peer = $this->peer($service);
        $claims = (new IdentityToken($peer['secret']))->decode($token, $service, $this->issuer(), 'link-ready', now()->timestamp);
        $data = $claims['data'];
        IdentityToken::assertBinding($data);
        abort_unless($data['service'] === $service && $data['central_user_id'] === (int) $user->id, 403);

        return DB::transaction(function () use ($user, $data): array {
            $company = Company::query()->lockForUpdate()->findOrFail($data['central_company_id']);
            $this->membership->authorize($user->fresh(), $company);
            abort_unless((int) $company->subscriber_id === $data['central_subscriber_id'], 409);
            $this->registry->markReady($data);

            return ['company_id' => $company->id, 'service' => $data['service'], 'ready' => true];
        }, 3);
    }

    public function cancel(User $user, string $nonce): void
    {
        $intent = DB::table('business_identity_intents')->where('nonce', $nonce)->where('central_user_id', $user->id)->first();
        abort_unless($intent, 404);
        $data = json_decode($intent->payload, true, 32, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($user, $nonce, $data): void {
            $company = Company::query()->lockForUpdate()->findOrFail($data['central_company_id']);
            $this->membership->authorize($user->fresh(), $company);
            $intent = DB::table('business_identity_intents')->where('nonce', $nonce)->lockForUpdate()->first();
            abort_unless($intent->completed_at === null, 409, 'La solicitud ya fue confirmada; puedes revocar el vínculo.');
            DB::table('business_identity_intents')->where('nonce', $nonce)->update(['expires_at' => now()->timestamp]);
        }, 3);
    }

    private function requestResult(string $token, string $origin, string $service, int $expiresAt): array
    {
        return ['request_token' => $token, 'proof_endpoint' => $origin.'/business/identity/proofs',
            'continue_url' => IdentityFlow::handoff($origin, 'request', $service, $token), 'expires_at' => $expiresAt];
    }

    private function activationResult(string $token, string $origin, string $service, int $expiresAt): array
    {
        return ['activation_token' => $token, 'continue_url' => IdentityFlow::handoff($origin, 'activation', $service, $token), 'expires_at' => $expiresAt];
    }

    private function issuer(): string
    {
        return (string) config('business_identity.issuer', '');
    }
}
