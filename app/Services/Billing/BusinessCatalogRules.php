<?php

namespace App\Services\Billing;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Structural and publication rules, independent of environment or database. */
final class BusinessCatalogRules
{
    public const DIMENSIONS = ['documents', 'branches', 'registers', 'users'];

    public const INITIAL_CAPACITY_POLICY = [
        'schema_version' => 1,
        'scope' => 'company',
        'users' => 'shared_identity',
        'owner_counts' => true,
        'branches' => 'active_branches',
        'registers' => 'concurrent_sessions',
        'excluded_channels' => ['cantina', 'autoservicio'],
    ];

    public function validate(array $data, bool $publishing = false): void
    {
        $this->require(($data['currency'] ?? null) === 'DOP', 'currency', 'La moneda del catálogo Business es DOP.');
        $this->require(in_array($data['timezone'] ?? '', DateTimeZone::listIdentifiers(), true), 'timezone', 'Zona horaria inválida.');
        $this->integer($data['annual_discount_bps'] ?? null, 'annual_discount_bps', 0, 10000);
        $this->text($data['terms'] ?? null, 'terms', ! $publishing);
        $this->integer($data['quote_valid_days'] ?? null, 'quote_valid_days', 1, 365, ! $publishing);
        $tax = $data['tax_rate'] ?? null;
        $this->require(($tax === null && ! $publishing) || (is_numeric($tax) && $tax >= 0 && $tax <= 1), 'tax_rate', 'Configure una tasa explícita entre 0 y 1 antes de publicar.');
        if (array_key_exists('capacity_policy', $data)) {
            $this->capacityPolicy($data['capacity_policy']);
        } else {
            $this->require(! $publishing, 'capacity_policy', 'Configure las reglas de capacidad antes de publicar.');
        }

        foreach (['products', 'offers', 'capacities', 'addons', 'implementations', 'discounts'] as $section) {
            $this->require(isset($data[$section]) && is_array($data[$section]), $section, 'Se requiere una colección.');
            $this->require(count($data[$section]) <= 100, $section, 'Máximo 100 elementos.');
            if ($section !== 'discounts') {
                $this->require($data[$section] !== [], $section, 'Debe contener al menos un elemento.');
            }
            foreach ($data[$section] as $code => $entry) {
                $this->require(is_string($code) && preg_match('/^[a-z][a-z0-9_-]{0,49}$/D', $code) === 1 && is_array($entry), $section, 'Código o estructura inválidos.');
                $this->text($entry['name'] ?? null, "$section.$code.name");
            }
        }
        $this->require(array_diff(array_keys($data['products']), ['ecf', 'pos', 'cumplimiento', 'bys']) === [], 'products', 'Este catálogo solo admite las cuatro soluciones Business.');
        foreach ($data['products'] as $code => $product) {
            $this->boolean($product['available'] ?? null, "products.$code.available");
            $this->integer($product['service_id'] ?? null, "products.$code.service_id", 1, PHP_INT_MAX, ! ($publishing && $product['available']));
            $this->text($product['description'] ?? null, "products.$code.description", true);
            $this->require(is_array($product['features'] ?? null) && count($product['features']) <= 100, "products.$code.features", 'Lista de funcionalidades requerida.');
            foreach ($product['features'] as $feature) {
                $this->text($feature, "products.$code.features");
            }
        }
        foreach ($data['capacities'] as $code => $capacity) {
            foreach (self::DIMENSIONS as $dimension) {
                $this->integer($capacity[$dimension] ?? null, "capacities.$code.$dimension", 1, 100000000);
            }
        }
        foreach ($data['offers'] as $code => $offer) {
            $path = "offers.$code";
            $this->boolean($offer['available'] ?? null, "$path.available");
            $this->require(is_array($offer['products'] ?? null) && count($offer['products']) >= 1 && count($offer['products']) <= 4, "$path.products", 'Seleccione productos, no otros paquetes.');
            foreach ($offer['products'] as $product) {
                $this->require(is_string($product) && isset($data['products'][$product]), "$path.products", 'Producto inexistente; no se admiten paquetes anidados.');
                if ($publishing && $offer['available']) {
                    $this->require($data['products'][$product]['available'], "$path.products", 'Una oferta disponible contiene un producto retirado.');
                }
            }
            $this->require(count(array_unique($offer['products'])) === count($offer['products']), "$path.products", 'No repita productos.');
            $this->require(in_array($offer['pricing_method'] ?? null, ['own', 'sum'], true), "$path.pricing_method", 'Método inválido.');
            $this->integer($offer['package_discount_bps'] ?? null, "$path.package_discount_bps", 0, 10000);
            $this->require($offer['pricing_method'] === 'sum' || $offer['package_discount_bps'] === 0, "$path.package_discount_bps", 'La tarifa propia ya contiene su precio final; no admite otro descuento de paquete.');
            $this->require(count($offer['products']) > 1 || $offer['pricing_method'] === 'own', "$path.pricing_method", 'Los individuales requieren tarifa propia.');
            $this->require(is_array($offer['levels'] ?? null) && $offer['levels'] !== [], "$path.levels", 'Configure niveles.');
            foreach ($offer['levels'] as $level => $price) {
                $this->require(isset($data['capacities'][$level]) && is_array($price), "$path.levels.$level", 'Nivel inexistente.');
                $this->boolean($price['enabled'] ?? null, "$path.levels.$level.enabled");
                $needed = $publishing && $offer['available'] && $price['enabled'];
                $this->integer($price['monthly_cents'] ?? null, "$path.levels.$level.monthly_cents", 0, 10000000000, ! ($needed && $offer['pricing_method'] === 'own'));
                $this->integer($price['annual_cents'] ?? null, "$path.levels.$level.annual_cents", 0, 10000000000, true);
                if ($offer['pricing_method'] === 'sum') {
                    $this->require(($price['monthly_cents'] ?? null) === null, "$path.levels.$level.monthly_cents", 'La suma de productos excluye una tarifa mensual propia.');
                    if ($needed) {
                        $this->monthlyPrice($data, $code, $level);
                    }
                }
            }
        }
        foreach ($data['addons'] as $code => $addon) {
            $path = "addons.$code";
            $this->text($addon['unit'] ?? null, "$path.unit");
            $this->integer($addon['block_size'] ?? null, "$path.block_size", 1, 100000000);
            $this->integer($addon['price_cents'] ?? null, "$path.price_cents", 0, 10000000000, ! $publishing);
            $this->boolean($addon['recurring'] ?? null, "$path.recurring");
            $this->require(array_key_exists('product', $addon) && ($addon['product'] === null || (is_string($addon['product']) && isset($data['products'][$addon['product']]))), "$path.product", 'Producto inválido.');
            $this->require(is_array($addon['capacity'] ?? null), "$path.capacity", 'Capacidad requerida.');
            foreach ($addon['capacity'] as $dimension => $value) {
                $this->require(in_array($dimension, self::DIMENSIONS, true), "$path.capacity", 'Dimensión inválida.');
                $this->integer($value, "$path.capacity.$dimension", 0, 100000000);
            }
            $this->require($addon['recurring'] || $addon['capacity'] === [], "$path.capacity", 'Un cargo único no concede capacidad recurrente.');
        }
        foreach ($data['implementations'] as $code => $mode) {
            $this->integer($mode['price_cents'] ?? null, "implementations.$code.price_cents", 0, 10000000000, ! $publishing);
            $this->integer($mode['hours'] ?? null, "implementations.$code.hours", 0, 100000);
            $this->text($mode['scope'] ?? null, "implementations.$code.scope", ! $publishing);
            $this->require(is_array($mode['offers'] ?? null), "implementations.$code.offers", 'Seleccione las ofertas a las que aplica la modalidad.');
            foreach ($mode['offers'] as $offer) {
                $this->require(is_string($offer) && isset($data['offers'][$offer]), "implementations.$code.offers", 'Oferta inexistente.');
            }
        }
        foreach ($data['discounts'] as $code => $discount) {
            $path = "discounts.$code";
            $this->integer($discount['percent_bps'] ?? null, "$path.percent_bps", 0, 10000);
            $this->integer($discount['company_id'] ?? null, "$path.company_id", 1, PHP_INT_MAX, true);
            $this->text($discount['reason'] ?? null, "$path.reason");
            $this->boolean($discount['compatible_annual'] ?? null, "$path.compatible_annual");
            $this->boolean($discount['compatible_package'] ?? null, "$path.compatible_package");
            $this->require(is_array($discount['offers'] ?? null), "$path.offers", 'Seleccione ofertas compatibles.');
            foreach ($discount['offers'] as $offer) {
                $this->require(is_string($offer) && isset($data['offers'][$offer]), "$path.offers", 'Oferta inexistente.');
            }
            $start = $this->date($discount['starts_at'] ?? null, "$path.starts_at");
            $end = $this->date($discount['ends_at'] ?? null, "$path.ends_at");
            $this->require($end > $start, "$path.ends_at", 'La vigencia debe terminar después de comenzar.');
        }
    }

    public function capacityPolicy(mixed $policy): void
    {
        $this->require(is_array($policy), 'capacity_policy', 'Configure las reglas de capacidad.');
        $this->require(array_diff(array_keys($policy), array_keys(self::INITIAL_CAPACITY_POLICY)) === [], 'capacity_policy', 'La política contiene campos desconocidos.');
        foreach (['schema_version', 'scope', 'branches', 'registers', 'excluded_channels'] as $field) {
            $this->require(($policy[$field] ?? null) === self::INITIAL_CAPACITY_POLICY[$field], "capacity_policy.$field", 'Regla de capacidad no compatible.');
        }
        $this->require(in_array($policy['users'] ?? null, ['shared_identity', 'per_solution'], true), 'capacity_policy.users', 'Seleccione usuarios compartidos o por solución.');
        $this->boolean($policy['owner_counts'] ?? null, 'capacity_policy.owner_counts');
    }

    public function monthlyPrice(array $data, string $offerCode, string $level): array
    {
        $offer = $data['offers'][$offerCode] ?? null;
        $this->require($offer && $offer['available'] && ($offer['levels'][$level]['enabled'] ?? false), 'offer', 'La combinación no está disponible.');
        $sources = [];
        if ($offer['pricing_method'] === 'own') {
            $amount = $offer['levels'][$level]['monthly_cents'] ?? null;
            $this->integer($amount, "offers.$offerCode.levels.$level.monthly_cents", 0, 10000000000);
        } else {
            $amount = 0;
            foreach ($offer['products'] as $product) {
                // Only individual own-price offers may feed a calculated package.
                $component = $data['offers'][$product] ?? null;
                $this->require($component && $component['products'] === [$product] && $component['pricing_method'] === 'own', 'offer', 'Falta la oferta individual de un componente.');
                $price = $this->monthlyPrice($data, $product, $level)['amount_cents'];
                $sources[] = ['offer' => $product, 'level' => $level, 'amount_cents' => $price];
                $amount += $price;
            }
            $amount -= self::percentage($amount, $offer['package_discount_bps']);
        }

        return ['amount_cents' => $amount, 'sources' => $sources];
    }

    public static function percentage(int $amount, int $basisPoints): int
    {
        return intdiv($amount * $basisPoints + 5000, 10000);
    }

    public function require(bool $condition, string $path, string $message): void
    {
        if (! $condition) {
            throw new DomainException($path.': '.$message);
        }
    }

    public function integer(mixed $value, string $path, int $min, int $max, bool $nullable = false): void
    {
        $this->require(($nullable && $value === null) || (is_int($value) && $value >= $min && $value <= $max), $path, "Se requiere un entero entre $min y $max".($nullable ? ' o un valor pendiente.' : '.'));
    }

    private function boolean(mixed $value, string $path): void
    {
        $this->require(is_bool($value), $path, 'Se requiere verdadero o falso.');
    }

    private function text(mixed $value, string $path, bool $empty = false): void
    {
        $this->require(is_string($value) && mb_strlen($value) <= 10000 && ($empty || trim($value) !== ''), $path, 'Texto requerido (máximo 10000 caracteres).');
    }

    public function date(mixed $value, string $path): DateTimeImmutable
    {
        $this->require(is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $value) === 1, $path, 'Fecha ISO 8601 con zona horaria requerida.');
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            $this->require($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0), $path, 'Fecha inválida.');

            return $date;
        } catch (\Exception $exception) {
            throw new DomainException($path.': Fecha inválida.', previous: $exception);
        }
    }
}
