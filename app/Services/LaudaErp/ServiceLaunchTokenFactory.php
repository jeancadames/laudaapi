<?php

namespace App\Http\Controllers\LaudaErp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Services\LaudaErp\ServiceAccessResolver;
use App\Services\LaudaErp\ServiceLaunchTokenFactory;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceLaunchController extends Controller
{
    public function __construct(
        private readonly ServiceAccessResolver $accessResolver,
        private readonly ServiceLaunchTokenFactory $launchTokenFactory,
    ) {}

    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);

        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        return Company::query()
            ->where('subscriber_id', $subscriberId)
            ->firstOrFail();
    }

    public function open(Request $request, Service $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $company = $this->companyFromErp($request);

        if (! $service->active) {
            throw new HttpException(404, 'Servicio no disponible.');
        }

        if (! $this->accessResolver->userCanAccess($user, $company, $service)) {
            throw new HttpException(403, 'No tienes acceso a este servicio.');
        }

        $launchMode = (string) ($service->launch_mode ?? 'internal');

        return match ($launchMode) {
            'internal' => $this->openInternal($service),
            'external' => $this->openExternal($user, $company, $service),
            'embedded' => $this->openEmbedded($service),
            'api' => $this->openApi($service),
            default => throw new HttpException(500, 'launch_mode no soportado.'),
        };
    }

    private function openInternal(Service $service): RedirectResponse
    {
        $href = trim((string) ($service->href ?? ''));

        if ($href === '') {
            throw new HttpException(404, 'El servicio interno no tiene href configurado.');
        }

        return redirect()->to($href);
    }

    private function openExternal($user, Company $company, Service $service): RedirectResponse
    {
        $externalUrl = rtrim((string) ($service->external_url ?? ''), '/');
        $launchPath = trim((string) ($service->launch_path ?? '/launch'));

        if ($externalUrl === '') {
            throw new HttpException(500, 'El servicio externo no tiene external_url configurado.');
        }

        $token = $this->launchTokenFactory->make(
            user: $user,
            company: $company,
            service: $service,
        );

        $path = $launchPath !== '' ? '/' . ltrim($launchPath, '/') : '/launch';

        return redirect()->away($externalUrl . $path . '?token=' . urlencode($token));
    }

    private function openEmbedded(Service $service): RedirectResponse
    {
        $href = trim((string) ($service->href ?? ''));

        if ($href === '') {
            throw new HttpException(404, 'El servicio embebido no tiene href configurado.');
        }

        return redirect()->to($href);
    }

    private function openApi(Service $service): RedirectResponse
    {
        $href = trim((string) ($service->href ?? ''));

        if ($href === '') {
            throw new HttpException(404, 'El servicio API no tiene href configurado.');
        }

        return redirect()->to($href);
    }
}
