<?php

namespace App\Services\Ecosystem;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\LaudaErp\ServiceAccessResolver;
use Illuminate\Support\Collection;

final class EcosystemHubService
{
    public function __construct(
        private readonly ServiceAccessResolver $accessResolver
    ) {
    }

    public function groupsFor(
        User $user,
        Company $company
    ): array {
        $groups = collect(
            config('ecosystem_hub.groups', [])
        );

        $serviceKeys = $groups
            ->flatMap(
                fn (array $group) =>
                    collect($group['solutions'] ?? [])
                        ->pluck('service_key')
            )
            ->filter()
            ->unique()
            ->values();

        $services = Service::query()
            ->whereIn('service_key', $serviceKeys)
            ->get()
            ->keyBy('service_key');

        return $groups
            ->map(
                fn (array $group, string $groupKey) =>
                    $this->buildGroup(
                        $groupKey,
                        $group,
                        $services,
                        $user,
                        $company
                    )
            )
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function buildGroup(
        string $groupKey,
        array $group,
        Collection $services,
        User $user,
        Company $company
    ): array {
        $solutions = collect(
            $group['solutions'] ?? []
        )
            ->map(
                fn (array $definition, string $solutionKey) =>
                    $this->buildSolution(
                        $solutionKey,
                        $definition,
                        $services->get(
                            $definition['service_key'] ?? null
                        ),
                        $user,
                        $company
                    )
            )
            ->sortBy('sort_order')
            ->values()
            ->all();

        return [
            'key' => $groupKey,
            'title' => $group['title'] ?? $groupKey,
            'description' => $group['description'] ?? null,
            'sort_order' => (int) ($group['sort_order'] ?? 0),
            'solutions' => $solutions,
        ];
    }

    private function buildSolution(
        string $solutionKey,
        array $definition,
        ?Service $service,
        User $user,
        Company $company
    ): array {
        $integration = (string) (
            $definition['integration'] ?? 'external'
        );

        $entitled = $service
            ? $this->accessResolver->userCanAccess(
                $user,
                $company,
                $service
            )
            : false;

        $ready = $this->integrationReady(
            $definition,
            $service
        );

        if (
            $integration === 'managed'
            && $service
            && $service->active
        ) {
            $state = $entitled
                ? 'active_managed'
                : 'available';
        } elseif (! $service || ! $ready) {
            $state = 'integration_pending';
        } else {
            $state = $entitled
                ? 'active'
                : 'available';
        }

        $launchUrl = null;

        if (
            (bool) ($definition['launchable'] ?? false)
            && $entitled
            && $ready
            && $service
        ) {
            $launchUrl = route(
                'erp.services.open',
                ['service' => $service->id],
                false
            );
        }

        return [
            'key' => $solutionKey,
            'title' => $definition['title'] ?? $solutionKey,
            'description' => $definition['description'] ?? null,
            'service_key' => $definition['service_key'] ?? null,
            'service_id' => $service?->id,
            'first_wave' => (bool) (
                $definition['first_wave'] ?? false
            ),
            'integration' => $integration,
            'integration_ready' => $ready,
            'entitled' => $entitled,
            'state' => $state,
            'launch_url' => $launchUrl,
            'target_url' => $definition['target_url'] ?? null,
            'sort_order' => (int) (
                $definition['sort_order'] ?? 0
            ),
        ];
    }

    private function integrationReady(
        array $definition,
        ?Service $service
    ): bool {
        if (! $service || ! $service->active) {
            return false;
        }

        $integration = (string) (
            $definition['integration'] ?? 'external'
        );

        if ($integration === 'managed') {
            return true;
        }

        if (
            $integration !== 'external'
            || (string) $service->launch_mode !== 'external'
        ) {
            return false;
        }

        $expectedUrl = rtrim(
            (string) ($definition['target_url'] ?? ''),
            '/'
        );

        $actualUrl = rtrim(
            (string) ($service->external_url ?? ''),
            '/'
        );

        if (
            $expectedUrl === ''
            || $actualUrl === ''
            || $expectedUrl !== $actualUrl
        ) {
            return false;
        }

        return (string) $service->launch_path
            === (string) (
                $definition['target_launch_path'] ?? ''
            );
    }
}
