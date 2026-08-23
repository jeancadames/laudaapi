<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DiagnosisResultPublisher;
use PHPUnit\Framework\TestCase;

class DiagnosisResultPublisherContractTest extends TestCase
{
    public function test_supported_modalities_have_canonical_labels(): void
    {
        $this->assertSame(
            'LAUDA 360 Guiado',
            DiagnosisResultPublisher::labelForModality('guided')
        );

        $this->assertSame(
            'LAUDA 360 Asistido',
            DiagnosisResultPublisher::labelForModality('assisted')
        );

        $this->assertSame(
            'LAUDA 360 Gestionado',
            DiagnosisResultPublisher::labelForModality('managed')
        );
    }

    public function test_unknown_modality_has_no_label(): void
    {
        $this->assertNull(
            DiagnosisResultPublisher::labelForModality('unknown')
        );
    }
}
