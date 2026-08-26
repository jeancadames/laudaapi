<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;

class TransformationImplementationModalityCatalog
{
    public const GUIDED = 'guided';
    public const ASSISTED = 'assisted';
    public const MANAGED = 'managed';

    public function all(): array
    {
        return [
            self::GUIDED => [
                'key' => self::GUIDED,
                'label' => 'Guiado',
                'summary' => 'LAUDA define la ruta, entrega lineamientos y acompaña; el cliente ejecuta la mayor parte de la implementación.',
                'lauda_role' => 'Orientar, revisar y acompañar.',
                'client_role' => 'Ejecutar las actividades y mantener la operación.',
            ],
            self::ASSISTED => [
                'key' => self::ASSISTED,
                'label' => 'Asistido',
                'summary' => 'LAUDA y el cliente comparten la ejecución de la implementación.',
                'lauda_role' => 'Configurar y ejecutar componentes acordados junto al cliente.',
                'client_role' => 'Aportar información, validar decisiones y ejecutar las actividades asignadas.',
            ],
            self::MANAGED => [
                'key' => self::MANAGED,
                'label' => 'Gestionado',
                'summary' => 'LAUDA lidera y ejecuta la mayor parte de la implementación, con validaciones del cliente.',
                'lauda_role' => 'Liderar la implementación y ejecutar los componentes acordados.',
                'client_role' => 'Proveer información, aprobar decisiones y asumir la operación posterior acordada.',
            ],
        ];
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function exists(?string $key): bool
    {
        return is_string($key) && array_key_exists($key, $this->all());
    }

    public function get(string $key): array
    {
        if (!$this->exists($key)) {
            throw new InvalidArgumentException("Modalidad de implementación no válida: {$key}");
        }

        return $this->all()[$key];
    }

    public function label(string $key): string
    {
        return $this->get($key)['label'];
    }
}
