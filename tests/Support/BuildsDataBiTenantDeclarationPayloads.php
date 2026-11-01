<?php

namespace Tests\Support;

use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use App\Services\Diagnosis\DataBiInformationRequirementReviewContract;

trait BuildsDataBiTenantDeclarationPayloads
{
    private function reviewSnapshot(): array
    {
        $generated = DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => ['gestion_clientes']],
        ]);

        return DataBiInformationRequirementReviewContract::normalize($generated, [
            'requirements' => array_map(static fn (array $item): array => [
                'key' => $item['key'], 'included' => true,
                'label' => $item['label'], 'guidance' => $item['guidance'],
            ], $generated['requirements']),
        ]);
    }

    private function declarationInput(int $version, array $snapshot, bool $complete = true): array
    {
        return ['version' => $version, 'declarations' => $complete
            ? array_map(static fn (array $item): array => [
                'requirement_key' => $item['key'], 'availability' => 'available',
                'sources' => [['type' => 'software', 'name' => 'ERP', 'structures' => ['Clientes'],
                    'notes' => 'Solo descripción de la fuente.']],
            ], $snapshot['requirements']) : []];
    }
}
