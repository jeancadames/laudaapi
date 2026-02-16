<?php

namespace App\Services\Dgii;

use App\Models\DgiiCertificate;

class DgiiCertificateRequirements
{
    public function checkForCompany(int $companyId): array
    {
        $certs = DgiiCertificate::query()
            ->where('company_id', $companyId)
            ->get(['id', 'type', 'status', 'has_private_key', 'password_ok', 'is_default', 'meta']);

        // Presencia simple por tipo (NO requiere default)
        $hasCer = $certs->contains(fn($c) => $c->type === 'cer');
        $hasP12 = $certs->contains(fn($c) => $c->type === 'p12');
        $hasPfx = $certs->contains(fn($c) => $c->type === 'pfx');

        $missingTypes = [];
        if (!$hasCer) $missingTypes[] = 'cer';
        if (!$hasP12) $missingTypes[] = 'p12';
        if (!$hasPfx) $missingTypes[] = 'pfx';

        $hasAllTypes = empty($missingTypes);

        // Helpers
        $metaHasEnc = function ($c): bool {
            $meta = $c->meta;

            // meta puede venir como array por accessor/casts, o string JSON, o null
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }

            if (!is_array($meta)) {
                $meta = (array) ($meta ?? []);
            }

            // ✅ Acepta claves comunes (evita falsos negativos)
            return !empty($meta['p12_password_enc'])
                || !empty($meta['pfx_password_enc'])
                || !empty($meta['password_enc']);
        };

        $isPotentialSigner = function ($c): bool {
            return in_array($c->type, ['p12', 'pfx'], true)
                && !in_array($c->status, ['invalid', 'revoked'], true);
        };

        // “Usable para firmar” (solo p12/pfx)
        $usableSigner = $certs->first(function ($c) use ($metaHasEnc, $isPotentialSigner) {
            if (!$isPotentialSigner($c)) return false;
            if (!(bool) $c->has_private_key) return false;

            // ✅ robusto: evita bug por tinyint/string/null
            if (!(bool) $c->password_ok) return false;

            if (!$metaHasEnc($c)) return false;

            return true;
        });

        // why_blocked: causa exacta
        $whyBlocked = null;

        if (!$hasAllTypes) {
            $whyBlocked = 'Faltan certificados: ' . implode(', ', $missingTypes);
        } else {
            $hasAnyP12Pfx = $certs->contains(fn($c) => in_array($c->type, ['p12', 'pfx'], true));

            if (!$hasAnyP12Pfx) {
                $whyBlocked = 'No hay P12/PFX para firmar.';
            } else {
                $hasKeyOk = $certs->contains(
                    fn($c) =>
                    in_array($c->type, ['p12', 'pfx'], true) && (bool) $c->has_private_key
                );

                // ✅ robusto
                $hasPwdOk = $certs->contains(
                    fn($c) =>
                    in_array($c->type, ['p12', 'pfx'], true) && (bool) $c->password_ok
                );

                $hasEncOk = $certs->contains(
                    fn($c) =>
                    in_array($c->type, ['p12', 'pfx'], true) && $metaHasEnc($c)
                );

                $hasValidStatus = $certs->contains(
                    fn($c) =>
                    in_array($c->type, ['p12', 'pfx'], true) && !in_array($c->status, ['invalid', 'revoked'], true)
                );

                if (!$hasValidStatus) {
                    $whyBlocked = 'P12/PFX con estado inválido o revocado.';
                } elseif (!$hasKeyOk) {
                    $whyBlocked = 'P12/PFX sin llave privada.';
                } elseif (!$hasPwdOk) {
                    $whyBlocked = 'P12/PFX con password inválido.';
                } elseif (!$hasEncOk) {
                    $whyBlocked = 'P12/PFX OK pero falta el password cifrado en meta (no guardado).';
                } else {
                    $whyBlocked = 'P12/PFX presentes pero no hay ninguno usable para firmar.';
                }
            }
        }

        return [
            'required_types' => ['cer', 'p12', 'pfx'],
            'present' => [
                'cer' => $hasCer,
                'p12' => $hasP12,
                'pfx' => $hasPfx,
            ],
            'missing' => $missingTypes,
            'has_all_required_types' => $hasAllTypes,

            'has_usable_signer' => (bool) $usableSigner,
            'usable_signer_id' => $usableSigner?->id,

            // ON solo si están los 3 tipos + hay firmador usable
            'can_enable_auto_token' => $hasAllTypes && (bool) $usableSigner,
            'why_blocked' => ($hasAllTypes && (bool) $usableSigner) ? null : $whyBlocked,
        ];
    }
}
