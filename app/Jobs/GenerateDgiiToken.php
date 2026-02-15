<?php

namespace App\Jobs;

use App\Models\DgiiCompanySetting;
use App\Services\Dgii\DgiiTokenManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDgiiToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $settingId,
        public int $skewSeconds = 90,
    ) {}

    public function handle(DgiiTokenManager $tm): void
    {
        /** @var DgiiCompanySetting $setting */
        $setting = DgiiCompanySetting::query()->findOrFail($this->settingId);

        // Si apagaron auto entre que se encoló y ejecutó => no hacer nada
        if (!$setting->dgii_token_auto) {
            return;
        }

        try {
            $tm->getValidToken($setting, $this->skewSeconds);
        } catch (\Throwable $e) {
            $setting->update([
                'dgii_token_last_requested_at' => now(),
                'dgii_token_last_error' => mb_substr($e->getMessage(), 0, 255),
            ]);

            throw $e; // opcional: para retries/failed jobs
        }
    }
}
