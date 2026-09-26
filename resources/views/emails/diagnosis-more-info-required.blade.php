<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Información adicional requerida</title>
</head>
<body>
    <p>Hola{{ $workflow->user?->name ? ' '.$workflow->user->name : '' }},</p>

    <p>
        Revisamos tu Diagnóstico 360 y necesitamos información
        adicional para poder continuar con el proceso.
    </p>

    @if (!empty($missingInformation['business_profile']))
        <p><strong>Información del negocio pendiente o por corregir:</strong></p>

        <ul>
            @foreach ($missingInformation['business_profile'] as $item)
                <li>
                    <strong>{{ $item['field'] }}</strong>

                    @if (!empty($item['messages']))
                        — {{ implode(' ', $item['messages']) }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if (!empty($missingInformation['missing_answers']))
        <p>
            <strong>Preguntas del diagnóstico pendientes:</strong>
            {{ implode(', ', $missingInformation['missing_answers']) }}
        </p>
    @endif

    @if ($reviewNotes)
        <p><strong>Observaciones de revisión:</strong></p>
        <p>{{ $reviewNotes }}</p>
    @endif

    @if (
        empty($missingInformation['business_profile'])
        && empty($missingInformation['missing_answers'])
    )
        <p>
            El equipo de LAUDA necesita una aclaración adicional
            antes de continuar. Revisa las observaciones indicadas
            arriba.
        </p>
    @endif

    <p>
        Puedes ingresar nuevamente a LAUDA para completar o
        corregir la información solicitada.
    </p>

    <p>
        Una vez actualizada, podremos continuar con la revisión
        de tu Diagnóstico 360.
    </p>

    <p>
        Equipo LAUDA
    </p>
</body>
</html>
