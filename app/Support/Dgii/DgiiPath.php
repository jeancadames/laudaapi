<?php

declare(strict_types=1);

namespace App\Support\Dgii;

final class DgiiPath
{
    /**
     * Tenant key (future-proof):
     * - prefer UUID
     * - fallback to ID
     */
    public static function companyKey(object|array $company): string
    {
        $uuid = is_array($company) ? ($company['uuid'] ?? null) : ($company->uuid ?? null);
        $id   = is_array($company) ? ($company['id'] ?? null) : ($company->id ?? null);

        $key = $uuid ?: $id;

        if (!$key) {
            throw new \InvalidArgumentException('Company key not found (missing uuid/id).');
        }

        // normalize
        return 'company_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) $key);
    }

    public static function base(object|array $company): string
    {
        return 'dgii';
    }

    public static function certsDir(object|array $company): string
    {
        return self::base($company) . '/certs/' . self::companyKey($company);
    }

    public static function authDebugDir(object|array $company): string
    {
        return self::base($company) . '/auth_debug/' . self::companyKey($company);
    }

    public static function certEcfDir(object|array $company): string
    {
        return self::base($company) . '/cert-ecf/' . self::companyKey($company);
    }

    public static function certAcecfDir(object|array $company): string
    {
        return self::base($company) . '/cert-acecf/' . self::companyKey($company);
    }

    public static function certRfceDir(object|array $company): string
    {
        return self::base($company) . '/cert-rfce/' . self::companyKey($company);
    }

    public static function outputDir(object|array $company): string
    {
        // opcional: si quieres output por company en vez de global
        return 'output/' . self::companyKey($company);
    }
}
