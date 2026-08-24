<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DiagnosisBusinessProfileServiceTest extends TestCase
{
    private DiagnosisBusinessProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            DiagnosisBusinessProfileService::class
        );
    }

    public function test_goods_profile_is_valid(): void
    {
        $data = $this->service->normalize([
            'business_activity_type' => 'goods',
            'business_sector' => 'commerce',
            'customer_market' => 'both',
            'sales_channels' => [
                'physical',
                'salesforce',
            ],
            'business_activity_description' =>
                'Venta de productos al detalle y a empresas.',
        ]);

        $validator = Validator::make(
            $data,
            $this->service->rules($data)
        );

        $this->assertTrue(
            $validator->passes(),
            json_encode($validator->errors()->toArray())
        );
    }

    public function test_mixed_business_profile_is_valid(): void
    {
        $data = $this->service->normalize([
            'business_activity_type' => 'mixed',
            'business_sector' => 'technology',
            'customer_market' => 'b2b',
            'sales_channels' => [
                'quotations',
                'contracts',
                'projects',
            ],
            'business_activity_description' =>
                'Venta de equipos con implementación y soporte.',
        ]);

        $validator = Validator::make(
            $data,
            $this->service->rules($data)
        );

        $this->assertTrue($validator->passes());
    }

    public function test_logistics_requires_operation_type(): void
    {
        $data = $this->service->normalize([
            'business_activity_type' => 'services',
            'business_sector' => 'logistics',
            'customer_market' => 'b2b',
            'sales_channels' => ['contracts'],
            'logistics_operation_types' => [],
            'business_activity_description' =>
                'Operador logístico para clientes empresariales.',
        ]);

        $validator = Validator::make(
            $data,
            $this->service->rules($data)
        );

        $this->assertFalse($validator->passes());

        $this->assertArrayHasKey(
            'logistics_operation_types',
            $validator->errors()->toArray()
        );
    }

    public function test_logistics_and_transportation_are_separate(): void
    {
        $sectors = config(
            'lauda360_business_profile.sectors'
        );

        $this->assertSame(
            'Logística',
            $sectors['logistics']
        );

        $this->assertSame(
            'Transporte',
            $sectors['transportation']
        );
    }

    public function test_other_sector_requires_name(): void
    {
        $data = $this->service->normalize([
            'business_activity_type' => 'services',
            'business_sector' => 'other',
            'customer_market' => 'b2c',
            'sales_channels' => ['physical'],
            'business_activity_description' =>
                'Prestación de servicios especializados al consumidor.',
        ]);

        $validator = Validator::make(
            $data,
            $this->service->rules($data)
        );

        $this->assertFalse($validator->passes());

        $this->assertArrayHasKey(
            'business_sector_other',
            $validator->errors()->toArray()
        );
    }

    public function test_non_logistics_clears_logistics_context(): void
    {
        $data = $this->service->normalize([
            'business_activity_type' => 'goods',
            'business_sector' => 'distribution',
            'customer_market' => 'b2b',
            'sales_channels' => ['salesforce'],
            'logistics_operation_types' => [
                'warehousing',
                'last_mile',
            ],
            'logistics_operation_other' => 'No aplica',
            'business_activity_description' =>
                'Distribución comercial de productos a empresas.',
        ]);

        $this->assertSame(
            [],
            $data['logistics_operation_types']
        );

        $this->assertNull(
            $data['logistics_operation_other']
        );
    }
}
