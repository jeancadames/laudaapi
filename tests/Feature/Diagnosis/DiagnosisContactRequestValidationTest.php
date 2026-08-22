<?php

namespace Tests\Feature\Diagnosis;

use App\Http\Requests\StoreContactRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DiagnosisContactRequestValidationTest extends TestCase
{
    private function validate(array $payload)
    {
        $request = StoreContactRequest::create('/contact', 'POST', $payload);
        $request->setContainer(app());

        return Validator::make(
            $request->all(),
            array_filter(
                $request->rules(),
                static fn ($rule) => $rule !== null
            ),
            $request->messages()
        );
    }

    public function test_current_diagnosis_contract_is_valid(): void
    {
        $validator = $this->validate([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@example.com',
            'phone' => '809-555-0101',
            'company' => 'Empresa Prueba, SRL',
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'terms' => true,
            'metadata' => [
                'source' => 'laudaapi.com',
                'request_type' => 'digital_diagnosis_access_request',
                'company_size' => '11 a 50 personas',
                'main_challenge' => 'Organizar procesos y reducir trabajo manual',
                'assistance_level' => 'Quiero que LAUDA me recomiende la modalidad',
                'intake_type' => 'digital_transformation_360',
                'diagnosis_access' => 'private_invitation',
            ],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_diagnosis_requires_business_context(): void
    {
        $validator = $this->validate([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@example.com',
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'terms' => true,
            'metadata' => [
                'source' => 'laudaapi.com',
                'request_type' => 'digital_diagnosis_access_request',
                'intake_type' => 'digital_transformation_360',
                'diagnosis_access' => 'private_invitation',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('phone', $validator->errors()->toArray());
        $this->assertArrayHasKey('company', $validator->errors()->toArray());
        $this->assertArrayHasKey('metadata.company_size', $validator->errors()->toArray());
        $this->assertArrayHasKey('metadata.main_challenge', $validator->errors()->toArray());
        $this->assertArrayHasKey('metadata.assistance_level', $validator->errors()->toArray());
    }

    public function test_diagnosis_rejects_values_outside_public_catalog(): void
    {
        $validator = $this->validate([
            'name' => 'Cliente Prueba',
            'email' => 'cliente@example.com',
            'phone' => '809-555-0101',
            'company' => 'Empresa Prueba, SRL',
            'topic' => 'Solicitud de acceso al Diagnóstico LAUDA 360',
            'terms' => true,
            'metadata' => [
                'source' => 'otro-sitio.com',
                'request_type' => 'digital_diagnosis_access_request',
                'company_size' => '5000 empleados',
                'main_challenge' => 'valor inventado',
                'assistance_level' => 'Gratis para siempre',
                'intake_type' => 'digital_transformation_360',
                'diagnosis_access' => 'public',
            ],
        ]);

        $this->assertTrue($validator->fails());

        foreach ([
            'metadata.source',
            'metadata.company_size',
            'metadata.main_challenge',
            'metadata.assistance_level',
            'metadata.diagnosis_access',
        ] as $key) {
            $this->assertArrayHasKey($key, $validator->errors()->toArray());
        }
    }

    public function test_regular_contact_keeps_legacy_flexibility(): void
    {
        $validator = $this->validate([
            'name' => 'Contacto General',
            'email' => 'contacto@example.com',
            'terms' => true,
            'topic' => 'API para sistemas propios',
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }
}
