<?php

namespace App\Console\Commands;

use App\Models\DgiiCompanySetting;
use App\Services\Dgii\DgiiTokenManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DgiiRefreshTokens extends Command
{
    protected $signature = 'dgii:tokens:refresh {--company_id=}';
    protected $description = 'Renueva tokens DGII para compañías con dgii_token_auto=1';

    public function handle(DgiiTokenManager $tm): int
    {
        $companyId = $this->option('company_id');
        $q = DgiiCompanySetting::query()
            ->where('dgii_token_auto', 1);

        if ($companyId) {
            $q->where('company_id', (int) $companyId);
        }

        $settings = $q->get();

        if ($settings->isEmpty()) {
            $this->info('No hay compañías con auto token activo.');
            return self::SUCCESS;
        }

        foreach ($settings as $setting) {
            try {
                // ✅ si no tienes refresh_before_seconds configurado, usa 90s por defecto
                $refreshBefore = (int) ($setting->dgii_token_refresh_before_seconds ?? 90);
                if ($refreshBefore <= 0) $refreshBefore = 90;

                // ✅ esta llamada debe: usar cache si aún sirve, o renovar si está por vencer
                $tm->getValidToken($setting, $refreshBefore);

                $this->info("OK company_id={$setting->company_id}");
            } catch (\Throwable $e) {
                Log::warning('DGII token auto refresh failed', [
                    'company_id' => $setting->company_id,
                    'msg' => $e->getMessage(),
                ]);

                // opcional: guarda error en setting
                try {
                    $setting->forceFill([
                        'dgii_token_last_error' => mb_substr($e->getMessage(), 0, 255),
                        'dgii_token_last_requested_at' => now(),
                    ])->save();
                } catch (\Throwable $ignored) {}

                $this->error("FAIL company_id={$setting->company_id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}