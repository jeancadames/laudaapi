<?php

namespace App\Services\BusinessIdentity;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Stores identity associations; temporary handoff receipts are encrypted separately. */
final class IdentityRegistry
{
    public function reserve(string $service, int $companyId, int $subscriberId): object
    {
        $query = DB::table('business_identity_links')->where('service', $service)->where('central_company_id', $companyId);
        $link = (clone $query)->lockForUpdate()->first();
        if (! $link) {
            DB::table('business_identity_links')->insert([
                'id' => (string) Str::uuid(), 'service' => $service, 'central_company_id' => $companyId,
                'central_subscriber_id' => $subscriberId, 'active' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $link = $query->first();
        }
        abort_unless((int) $link->central_subscriber_id === $subscriberId, 409, 'La empresa cambió de suscriptor; revisa su vínculo.');

        return $link;
    }

    /** Caller holds its company lock and has checked current membership on its own side. */
    public function bind(array $data, bool $manageCompany): void
    {
        IdentityToken::assertBinding($data);
        $member = DB::table('business_identity_members')->where('link_id', $data['link_id'])->where('central_user_id', $data['central_user_id'])->lockForUpdate()->first();
        if ($member) {
            abort_unless($data['generation'] > (int) $member->generation, 409, 'Esta solicitud fue sustituida. Inicia una nueva vinculación.');
        }
        $query = DB::table('business_identity_links')->where('id', $data['link_id']);
        $link = (clone $query)->lockForUpdate()->first();
        if (! $link) {
            abort_unless($manageCompany, 403, 'La empresa todavía no tiene un vínculo confirmado.');
            DB::table('business_identity_links')->insert([
                'id' => $data['link_id'], 'service' => $data['service'],
                'central_company_id' => $data['central_company_id'], 'central_subscriber_id' => $data['central_subscriber_id'],
                'local_company_id' => $data['local_company_id'], 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            abort_unless($link->service === $data['service']
                && (int) $link->central_company_id === $data['central_company_id']
                && (int) $link->central_subscriber_id === $data['central_subscriber_id']
                && ($link->local_company_id === null || (int) $link->local_company_id === $data['local_company_id']), 409, 'La empresa ya tiene otra identidad vinculada.');
            abort_unless($manageCompany || ($link->active && $link->local_company_id !== null), 403, 'La vinculación empresarial requiere autorización del propietario.');
            $query->update(['local_company_id' => $data['local_company_id'], 'active' => true, 'updated_at' => now()]);
        }

        $accounts = DB::table('business_identity_accounts')->where('service', $data['service']);
        $account = (clone $accounts)->where('central_user_id', $data['central_user_id'])->lockForUpdate()->first();
        if ($account) {
            abort_unless((int) $account->local_user_id === $data['local_user_id'], 409, 'La cuenta ya tiene otra identidad vinculada.');
        } else {
            $accounts->insert([
                'service' => $data['service'], 'central_user_id' => $data['central_user_id'],
                'local_user_id' => $data['local_user_id'], 'created_at' => now(),
            ]);
        }

        DB::table('business_identity_members')->updateOrInsert(
            ['link_id' => $data['link_id'], 'central_user_id' => $data['central_user_id']],
            ['local_user_id' => $data['local_user_id'], 'consent' => $data['consent'], 'generation' => $data['generation'], 'ready_at' => null, 'active' => true, 'updated_at' => now()]
        );
        $this->event('consented', $data);
    }

    public function binding(string $service, int $companyId, int $userId): array
    {
        $link = DB::table('business_identity_links')->where('service', $service)
            ->where('central_company_id', $companyId)->where('active', true)->first();
        abort_unless($link, 409, 'Primero confirma el vínculo de la empresa en ambas aplicaciones.');
        $member = DB::table('business_identity_members')->where('link_id', $link->id)
            ->where('central_user_id', $userId)->where('active', true)->first();
        abort_unless($member, 403, 'Primero vincula tu cuenta con esta empresa.');

        return [
            'link_id' => $link->id, 'service' => $link->service,
            'central_company_id' => (int) $link->central_company_id, 'central_subscriber_id' => (int) $link->central_subscriber_id,
            'central_user_id' => (int) $member->central_user_id, 'local_company_id' => (int) $link->local_company_id,
            'local_user_id' => (int) $member->local_user_id, 'consent' => $member->consent, 'generation' => (int) $member->generation,
        ];
    }

    public function assertCurrent(array $data): void
    {
        IdentityToken::assertBinding($data);
        $current = $this->binding($data['service'], $data['central_company_id'], $data['central_user_id']);
        foreach ($current as $key => $value) {
            abort_unless(($data[$key] ?? null) === $value, 403, 'El vínculo de acceso fue cambiado o revocado.');
        }
    }

    public function consume(string $service, string $purpose, string $nonce, int $expiresAt): void
    {
        try {
            DB::table('business_identity_nonces')->insert([
                'service' => $service, 'purpose' => $purpose, 'nonce' => $nonce,
                'expires_at' => $expiresAt, 'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new HttpException(409, 'Este comprobante ya fue utilizado.');
        }
    }

    public function markReady(array $data): void
    {
        $this->assertCurrent($data);
        DB::table('business_identity_members')->where('link_id', $data['link_id'])->where('central_user_id', $data['central_user_id'])
            ->whereNull('ready_at')->update(['ready_at' => now()]);
    }

    public function assertAvailable(array $data): void
    {
        $links = DB::table('business_identity_links')->where('service', $data['service'])
            ->where(fn ($query) => $query->where('central_company_id', $data['central_company_id'])->orWhere('local_company_id', $data['local_company_id']))->get();
        foreach ($links as $link) {
            abort_unless($link->id === $data['link_id'] && (int) $link->central_company_id === $data['central_company_id']
                && (int) $link->central_subscriber_id === $data['central_subscriber_id']
                && ($link->local_company_id === null || (int) $link->local_company_id === $data['local_company_id']), 409, 'Esta empresa ya está vinculada a otra empresa.');
        }
        $accounts = DB::table('business_identity_accounts')->where('service', $data['service'])
            ->where(fn ($query) => $query->where('central_user_id', $data['central_user_id'])->orWhere('local_user_id', $data['local_user_id']))->get();
        foreach ($accounts as $account) {
            abort_unless((int) $account->central_user_id === $data['central_user_id'] && (int) $account->local_user_id === $data['local_user_id'], 409, 'Esta cuenta ya está vinculada a otra persona.');
        }
    }

    public function revoke(array $data, bool $wholeCompany): void
    {
        if ($wholeCompany) {
            DB::table('business_identity_links')->where('id', $data['link_id'])->update(['active' => false, 'updated_at' => now()]);
            DB::table('business_identity_members')->where('link_id', $data['link_id'])->update(['active' => false, 'updated_at' => now()]);
        } else {
            DB::table('business_identity_members')->where('link_id', $data['link_id'])
                ->where('central_user_id', $data['central_user_id'])->update(['active' => false, 'updated_at' => now()]);
        }
        $this->event($wholeCompany ? 'company_revoked' : 'member_revoked', $data);
    }

    public function event(string $event, array $data): void
    {
        DB::table('business_identity_events')->insert([
            'event' => $event, 'link_id' => $data['link_id'], 'central_user_id' => $data['central_user_id'],
            'local_user_id' => $data['local_user_id'] ?? null, 'consent' => $data['consent'] ?? null, 'created_at' => now(),
        ]);
    }
}
