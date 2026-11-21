<?php

namespace App\Services\BusinessIdentity;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class IdentityPage
{
    public function data(?User $user): array
    {
        $enabled = (bool) config('business_identity.enabled') && Schema::hasTable('business_identity_proofs');
        $companies = [];
        $endpoints = [];
        foreach (IdentityFlow::LABELS as $key => $label) {
            $endpoints[$key] = ['preview' => route('business.identity.preview', $key, false), 'confirm' => route('business.identity.confirm', $key, false), 'ready' => route('business.identity.ready', $key, false)];
        }
        if ($enabled && $user?->hasVerifiedEmail()) {
            $memberships = $user->subscribers()->where('subscribers.active', true)->wherePivot('active', true)->get();
            foreach (Company::whereIn('subscriber_id', $memberships->modelKeys())->where('active', true)->orderBy('name')->get() as $company) {
                $role = $memberships->firstWhere('id', $company->subscriber_id)->pivot->role;
                $canManage = in_array($role, ['owner', 'admin'], true);
                $services = [];
                foreach (IdentityFlow::LABELS as $key => $label) {
                    $service = Service::where('service_key', $key)->where('active', true)->first();
                    $configured = (bool) $service;
                    try {
                        app(CentralIdentityService::class)->peer($key);
                    } catch (HttpException) {
                        $configured = false;
                    }
                    $link = DB::table('business_identity_links')->where('service', $key)->where('central_company_id', $company->id)->first();
                    $member = $link ? DB::table('business_identity_members')->where('link_id', $link->id)->where('central_user_id', $user->id)->first() : null;
                    $ready = $link?->active && $member?->active && $member->ready_at !== null;
                    $services[] = [
                        'key' => $key, 'name' => $label, 'configured' => $configured, 'ready' => (bool) $ready,
                        'linked' => (bool) $link?->local_company_id, 'active' => (bool) $link?->active,
                        'pending' => $link && DB::table('business_identity_intents')->where('link_id', $link->id)->where('central_user_id', $user->id)->whereNull('completed_at')->where('expires_at', '>', now()->timestamp)->exists(),
                        'member_active' => (bool) $member?->active, 'local_company_id' => $link?->local_company_id,
                        'can_link' => $canManage || (bool) $link?->active, 'link_company' => $canManage && ! $link?->active,
                        'start_url' => route('business.identity.start', [$company, $key], false),
                        'revoke_url' => route('business.identity.revoke', [$company, $key], false),
                        'launch_url' => $ready && $configured ? route('business.identity.launch', [$company, $service->slug], false) : null,
                    ];
                }
                $companies[] = ['id' => (int) $company->id, 'name' => $company->name, 'can_manage' => $canManage, 'services' => $services];
            }
        }

        return ['side' => 'central', 'service' => null, 'service_name' => 'LAUDA', 'enabled' => $enabled,
            'account' => $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'verified' => $user->hasVerifiedEmail()] : null,
            'companies' => $companies, 'endpoints' => $endpoints, 'home_url' => '/app', 'hub_url' => '/business/identity'];
    }
}
