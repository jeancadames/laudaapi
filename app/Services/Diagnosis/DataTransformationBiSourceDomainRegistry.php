<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

final class DataTransformationBiSourceDomainRegistry
{
    public const CUSTOMERS = 'customers';

    public const SALES = 'sales';

    public const ACCOUNTS_RECEIVABLE =
        'accounts_receivable';

    public const PRODUCTS = 'products';

    public const SUPPLIERS = 'suppliers';

    public const ACCOUNTS_PAYABLE =
        'accounts_payable';

    /**
     * Dominios que actualmente aceptan archivos fuente
     * entregados por el cliente en CSV/XLSX con su estructura original.
     *
     * Inventory sigue existiendo en el esquema canónico, pero su contrato
     * de recepción source-native todavía no está activado.
     *
     * @return array<int, string>
     */
    public static function domainKeys(): array
    {
        return [
            self::CUSTOMERS,
            self::SALES,
            self::ACCOUNTS_RECEIVABLE,
            self::PRODUCTS,
            self::SUPPLIERS,
            self::ACCOUNTS_PAYABLE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CUSTOMERS =>
                'Clientes',

            self::SALES =>
                'Ventas',

            self::ACCOUNTS_RECEIVABLE =>
                'Cuentas por cobrar',

            self::PRODUCTS =>
                'Productos',

            self::SUPPLIERS =>
                'Suplidores',

            self::ACCOUNTS_PAYABLE =>
                'Cuentas por pagar',
        ];
    }

    public static function supports(
        string $domain
    ): bool {
        return in_array(
            $domain,
            self::domainKeys(),
            true
        );
    }

    public static function assertSupported(
        string $domain
    ): void {
        if (! self::supports($domain)) {
            throw new InvalidArgumentException(
                "Dominio source no soportado: {$domain}."
            );
        }

        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                "El dominio source {$domain} no existe "
                .'en el esquema canónico.'
            );
        }
    }
}
