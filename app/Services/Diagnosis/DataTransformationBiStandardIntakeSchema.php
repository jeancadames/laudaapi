<?php

namespace App\Services\Diagnosis;

final class DataTransformationBiStandardIntakeSchema
{
    public const VERSION = 1;

    /**
     * @return array<string, array{
     *     label:string,
     *     description:string,
     *     fields:array<int, array{
     *         name:string,
     *         required:bool,
     *         type:string,
     *         description:string
     *     }>
     * }>
     */
    public static function domains(): array
    {
        return [
            'customers' => [
                'label' =>
                    'Clientes',

                'description' =>
                    'Maestro base de clientes.',

                'fields' => [
                    self::field(
                        'customer_id',
                        true,
                        'text',
                        'Identificador único y estable del cliente.'
                    ),

                    self::field(
                        'customer_name',
                        true,
                        'text',
                        'Nombre o razón social del cliente.'
                    ),

                    self::field(
                        'tax_id',
                        false,
                        'text',
                        'Identificación fiscal del cliente, por ejemplo RNC o cédula.'
                    ),

                    self::field(
                        'customer_type',
                        false,
                        'text',
                        'Tipo o clasificación comercial del cliente.'
                    ),

                    self::field(
                        'city',
                        false,
                        'text',
                        'Ciudad principal asociada al cliente.'
                    ),

                    self::field(
                        'country',
                        false,
                        'text',
                        'País asociado al cliente.'
                    ),

                    self::field(
                        'credit_limit',
                        false,
                        'decimal',
                        'Límite de crédito vigente.'
                    ),

                    self::field(
                        'created_at',
                        false,
                        'datetime',
                        'Fecha y hora de creación del cliente en el sistema de origen.'
                    ),

                    self::field(
                        'active',
                        false,
                        'boolean',
                        'Indica si el cliente se encuentra activo.'
                    ),
                ],
            ],

            'products' => [
                'label' =>
                    'Productos',

                'description' =>
                    'Maestro base de productos.',

                'fields' => [
                    self::field(
                        'product_id',
                        true,
                        'text',
                        'Identificador único y estable del producto.'
                    ),

                    self::field(
                        'product_name',
                        true,
                        'text',
                        'Nombre o descripción principal del producto.'
                    ),

                    self::field(
                        'sku',
                        false,
                        'text',
                        'Código SKU o código comercial.'
                    ),

                    self::field(
                        'category',
                        false,
                        'text',
                        'Categoría del producto.'
                    ),

                    self::field(
                        'brand',
                        false,
                        'text',
                        'Marca del producto.'
                    ),

                    self::field(
                        'unit_of_measure',
                        false,
                        'text',
                        'Unidad de medida principal.'
                    ),

                    self::field(
                        'unit_cost',
                        false,
                        'decimal',
                        'Costo unitario de referencia.'
                    ),

                    self::field(
                        'unit_price',
                        false,
                        'decimal',
                        'Precio unitario de referencia.'
                    ),

                    self::field(
                        'active',
                        false,
                        'boolean',
                        'Indica si el producto se encuentra activo.'
                    ),
                ],
            ],

            'inventory' => [
                'label' =>
                    'Inventario',

                'description' =>
                    'Existencias por fecha, producto y ubicación.',

                'fields' => [
                    self::field(
                        'snapshot_date',
                        true,
                        'date',
                        'Fecha de corte de la existencia.'
                    ),

                    self::field(
                        'product_id',
                        true,
                        'text',
                        'Identificador del producto. Debe existir en products.product_id.'
                    ),

                    self::field(
                        'quantity_on_hand',
                        true,
                        'decimal',
                        'Cantidad física registrada a la fecha de corte.'
                    ),

                    self::field(
                        'branch',
                        false,
                        'text',
                        'Sucursal asociada al inventario.'
                    ),

                    self::field(
                        'warehouse',
                        false,
                        'text',
                        'Almacén o ubicación física.'
                    ),

                    self::field(
                        'quantity_available',
                        false,
                        'decimal',
                        'Cantidad disponible para uso o venta.'
                    ),

                    self::field(
                        'quantity_committed',
                        false,
                        'decimal',
                        'Cantidad reservada o comprometida.'
                    ),

                    self::field(
                        'unit_cost',
                        false,
                        'decimal',
                        'Costo unitario utilizado para valorizar la existencia.'
                    ),
                ],
            ],

            'sales' => [
                'label' =>
                    'Ventas',

                'description' =>
                    'Histórico de ventas a nivel de línea de documento.',

                'fields' => [
                    self::field(
                        'document_id',
                        true,
                        'text',
                        'Identificador único del documento de venta.'
                    ),

                    self::field(
                        'line_number',
                        true,
                        'integer',
                        'Número de línea dentro del documento.'
                    ),

                    self::field(
                        'document_date',
                        true,
                        'date',
                        'Fecha del documento de venta.'
                    ),

                    self::field(
                        'customer_id',
                        true,
                        'text',
                        'Identificador del cliente. Debe existir en customers.customer_id.'
                    ),

                    self::field(
                        'product_id',
                        true,
                        'text',
                        'Identificador del producto. Debe existir en products.product_id.'
                    ),

                    self::field(
                        'quantity',
                        true,
                        'decimal',
                        'Cantidad vendida en la línea.'
                    ),

                    self::field(
                        'unit_price',
                        true,
                        'decimal',
                        'Precio unitario antes de descuentos e impuestos.'
                    ),

                    self::field(
                        'branch',
                        false,
                        'text',
                        'Sucursal donde se originó la venta.'
                    ),

                    self::field(
                        'salesperson',
                        false,
                        'text',
                        'Vendedor o responsable comercial.'
                    ),

                    self::field(
                        'discount_amount',
                        false,
                        'decimal',
                        'Monto de descuento de la línea.'
                    ),

                    self::field(
                        'tax_amount',
                        false,
                        'decimal',
                        'Monto de impuesto de la línea.'
                    ),

                    self::field(
                        'cost_amount',
                        false,
                        'decimal',
                        'Costo total asociado a la línea.'
                    ),

                    self::field(
                        'net_amount',
                        false,
                        'decimal',
                        'Monto neto de la línea.'
                    ),

                    self::field(
                        'currency',
                        false,
                        'text',
                        'Código de moneda, preferiblemente ISO 4217.'
                    ),
                ],
            ],

            'accounts_receivable' => [
                'label' =>
                    'Cuentas por cobrar',

                'description' =>
                    'Documentos de clientes con saldo y vencimiento.',

                'fields' => [
                    self::field(
                        'document_id',
                        true,
                        'text',
                        'Identificador único del documento por cobrar.'
                    ),

                    self::field(
                        'customer_id',
                        true,
                        'text',
                        'Identificador del cliente. Debe existir en customers.customer_id.'
                    ),

                    self::field(
                        'issue_date',
                        true,
                        'date',
                        'Fecha de emisión del documento.'
                    ),

                    self::field(
                        'due_date',
                        true,
                        'date',
                        'Fecha de vencimiento del documento.'
                    ),

                    self::field(
                        'original_amount',
                        true,
                        'decimal',
                        'Monto original del documento.'
                    ),

                    self::field(
                        'outstanding_amount',
                        true,
                        'decimal',
                        'Saldo pendiente a la fecha de extracción.'
                    ),

                    self::field(
                        'document_type',
                        false,
                        'text',
                        'Tipo de documento, por ejemplo factura o nota.'
                    ),

                    self::field(
                        'currency',
                        false,
                        'text',
                        'Código de moneda, preferiblemente ISO 4217.'
                    ),

                    self::field(
                        'status',
                        false,
                        'text',
                        'Estado del documento en el sistema de origen.'
                    ),
                ],
            ],

            'suppliers' => [
                'label' =>
                    'Suplidores',

                'description' =>
                    'Maestro base de suplidores.',

                'fields' => [
                    self::field(
                        'supplier_id',
                        true,
                        'text',
                        'Identificador único y estable del suplidor.'
                    ),

                    self::field(
                        'supplier_name',
                        true,
                        'text',
                        'Nombre o razón social del suplidor.'
                    ),

                    self::field(
                        'tax_id',
                        false,
                        'text',
                        'Identificación fiscal del suplidor.'
                    ),

                    self::field(
                        'city',
                        false,
                        'text',
                        'Ciudad principal asociada al suplidor.'
                    ),

                    self::field(
                        'country',
                        false,
                        'text',
                        'País asociado al suplidor.'
                    ),

                    self::field(
                        'payment_terms_days',
                        false,
                        'integer',
                        'Plazo comercial de pago expresado en días.'
                    ),

                    self::field(
                        'active',
                        false,
                        'boolean',
                        'Indica si el suplidor se encuentra activo.'
                    ),
                ],
            ],

            'accounts_payable' => [
                'label' =>
                    'Cuentas por pagar',

                'description' =>
                    'Documentos de suplidores con saldo y vencimiento.',

                'fields' => [
                    self::field(
                        'document_id',
                        true,
                        'text',
                        'Identificador único del documento por pagar.'
                    ),

                    self::field(
                        'supplier_id',
                        true,
                        'text',
                        'Identificador del suplidor. Debe existir en suppliers.supplier_id.'
                    ),

                    self::field(
                        'issue_date',
                        true,
                        'date',
                        'Fecha de emisión del documento.'
                    ),

                    self::field(
                        'due_date',
                        true,
                        'date',
                        'Fecha de vencimiento del documento.'
                    ),

                    self::field(
                        'original_amount',
                        true,
                        'decimal',
                        'Monto original del documento.'
                    ),

                    self::field(
                        'outstanding_amount',
                        true,
                        'decimal',
                        'Saldo pendiente a la fecha de extracción.'
                    ),

                    self::field(
                        'document_type',
                        false,
                        'text',
                        'Tipo de documento.'
                    ),

                    self::field(
                        'currency',
                        false,
                        'text',
                        'Código de moneda, preferiblemente ISO 4217.'
                    ),

                    self::field(
                        'status',
                        false,
                        'text',
                        'Estado del documento en el sistema de origen.'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     value:string,
     *     description:string
     * }>
     */
    public static function formatRules(): array
    {
        return [
            'identifiers' => [
                'value' =>
                    'text',

                'description' =>
                    'Los identificadores deben tratarse como texto para preservar ceros a la izquierda.'
            ],

            'dates' => [
                'value' =>
                    'YYYY-MM-DD',

                'description' =>
                    'Formato obligatorio para fechas.'
            ],

            'datetimes' => [
                'value' =>
                    'YYYY-MM-DD HH:MM:SS',

                'description' =>
                    'Formato recomendado para fecha y hora.'
            ],

            'decimal_separator' => [
                'value' =>
                    '.',

                'description' =>
                    'Usar punto como separador decimal.'
            ],

            'thousands_separator' => [
                'value' =>
                    'none',

                'description' =>
                    'No usar separador de miles.'
            ],

            'monetary_values' => [
                'value' =>
                    'decimal_without_currency_symbol',

                'description' =>
                    'Los montos deben enviarse como números, sin símbolos de moneda.'
            ],

            'booleans' => [
                'value' =>
                    'true|false',

                'description' =>
                    'Usar únicamente true o false.'
            ],

            'optional_empty_values' => [
                'value' =>
                    'blank',

                'description' =>
                    'Campos opcionales sin valor deben quedar vacíos.'
            ],

            'csv_encoding' => [
                'value' =>
                    'UTF-8',

                'description' =>
                    'Los archivos CSV deben utilizar codificación UTF-8.'
            ],

            'csv_delimiter' => [
                'value' =>
                    ',',

                'description' =>
                    'El delimitador estándar es la coma.'
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function domainKeys(): array
    {
        return array_keys(
            self::domains()
        );
    }

    /**
     * @return array{
     *     name:string,
     *     required:bool,
     *     type:string,
     *     description:string
     * }
     */
    public static function identityKeys(): array
    {
        return [
            'customers' => [
                'customer_id',
            ],

            'products' => [
                'product_id',
            ],

            'inventory' => [
                'snapshot_date',
                'product_id',
                'branch',
                'warehouse',
            ],

            'sales' => [
                'document_id',
                'line_number',
            ],

            'accounts_receivable' => [
                'document_id',
            ],

            'suppliers' => [
                'supplier_id',
            ],

            'accounts_payable' => [
                'document_id',
            ],
        ];
    }

    public static function relationships(): array
    {
        return [
            [
                'from_domain' => 'inventory',
                'from_field' => 'product_id',
                'to_domain' => 'products',
                'to_field' => 'product_id',
            ],

            [
                'from_domain' => 'sales',
                'from_field' => 'customer_id',
                'to_domain' => 'customers',
                'to_field' => 'customer_id',
            ],

            [
                'from_domain' => 'sales',
                'from_field' => 'product_id',
                'to_domain' => 'products',
                'to_field' => 'product_id',
            ],

            [
                'from_domain' => 'accounts_receivable',
                'from_field' => 'customer_id',
                'to_domain' => 'customers',
                'to_field' => 'customer_id',
            ],

            [
                'from_domain' => 'accounts_payable',
                'from_field' => 'supplier_id',
                'to_domain' => 'suppliers',
                'to_field' => 'supplier_id',
            ],
        ];
    }

    private static function field(
        string $name,
        bool $required,
        string $type,
        string $description
    ): array {
        return [
            'name' =>
                $name,

            'required' =>
                $required,

            'type' =>
                $type,

            'description' =>
                $description,
        ];
    }
}
