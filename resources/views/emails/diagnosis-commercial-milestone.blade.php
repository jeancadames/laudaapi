<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>LAUDA Transformación Digital 360</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#172033;">
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    style="background:#f6f7f9;padding:24px 12px;"
>
<tr>
<td align="center">
<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    style="max-width:680px;background:#ffffff;border:1px solid #e7e9ee;border-radius:16px;overflow:hidden;"
>
<tr>
<td style="padding:28px 32px;background:#111827;color:#ffffff;">
    <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
        LAUDA Transformación Digital 360
    </div>
    <h1 style="margin:10px 0 0;font-size:24px;line-height:1.25;">
        @if($milestone === 'invoice_required')
            Pago requerido
        @elseif($milestone === 'payment_confirmed')
            Pago confirmado
        @else
            Entregable disponible
        @endif
    </h1>
</td>
</tr>
<tr>
<td style="padding:30px 32px;">
    @php
        $deliverableName = $deliverable === 'detailed_roadmap'
            ? 'Roadmap Detallado LAUDA 360'
            : 'Informe Ampliado LAUDA 360';

        $currency = $payload['currency'] ?? 'DOP';

        $money = static fn ($value) =>
            number_format((float) $value, 2);
    @endphp

    <p style="margin:0 0 18px;line-height:1.7;">
        Hola {{ $assessment->user?->name ?: 'cliente' }},
    </p>

    @if($milestone === 'invoice_required')
        <p style="line-height:1.7;">
            Hemos preparado la facturación correspondiente a su
            <strong>{{ $deliverableName }}</strong>.
            A continuación encontrará el importe requerido y el alcance del entregable.
        </p>

        <table
            role="presentation"
            width="100%"
            cellspacing="0"
            cellpadding="8"
            style="margin:20px 0;border-collapse:collapse;border:1px solid #e7e9ee;"
        >
            @if(
                $deliverable === 'detailed_roadmap'
                && data_get($payload, 'credit.eligible')
            )
                <tr>
                    <td>Precio base Roadmap</td>
                    <td align="right">
                        <strong>
                            {{ $currency }}
                            {{ $money(data_get($payload, 'credit.base_subtotal')) }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td>Crédito Informe Ampliado</td>
                    <td align="right">
                        <strong>
                            - {{ $currency }}
                            {{ $money(data_get($payload, 'credit.amount')) }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td>Base luego del crédito</td>
                    <td align="right">
                        <strong>
                            {{ $currency }}
                            {{ $money(data_get($payload, 'credit.net_subtotal')) }}
                        </strong>
                    </td>
                </tr>
            @else
                <tr>
                    <td>Subtotal</td>
                    <td align="right">
                        <strong>
                            {{ $currency }}
                            {{ $money($payload['subtotal'] ?? 0) }}
                        </strong>
                    </td>
                </tr>
            @endif

            <tr>
                <td>ITBIS</td>
                <td align="right">
                    <strong>
                        {{ $currency }}
                        {{ $money($payload['tax_total'] ?? 0) }}
                    </strong>
                </td>
            </tr>
            <tr>
                <td>Total</td>
                <td align="right">
                    <strong>
                        {{ $currency }}
                        {{ $money($payload['total'] ?? 0) }}
                    </strong>
                </td>
            </tr>
            <tr>
                <td>Factura</td>
                <td align="right">
                    <strong>
                        {{ $payload['invoice_number'] ?? '—' }}
                    </strong>
                </td>
            </tr>
        </table>

        @if(
            $deliverable === 'detailed_roadmap'
            && data_get($payload, 'credit.eligible')
        )
            <p
                style="line-height:1.7;background:#f8fafc;padding:14px;border-radius:10px;"
            >
                Se aplicó el crédito del Informe Ampliado de acuerdo con la
                política comercial vigente para solicitudes realizadas dentro
                de la ventana de
                {{ data_get($payload, 'credit.window_days') }} días.
            </p>
        @endif

        <p style="line-height:1.7;">
            Una vez confirmado el pago, LAUDA podrá completar la revisión y
            publicación del entregable.
        </p>
    @elseif($milestone === 'payment_confirmed')
        <p style="line-height:1.7;">
            Hemos confirmado el pago de su
            <strong>{{ $deliverableName }}</strong>.
            El servicio pasa ahora a la etapa de revisión y preparación final
            por LAUDA.
        </p>

        <p style="line-height:1.7;">
            Le notificaremos nuevamente cuando el entregable esté publicado y
            disponible en su cuenta.
        </p>
    @else
        <p style="line-height:1.7;">
            Su <strong>{{ $deliverableName }}</strong>
            @if(!empty($payload['version']))
                versión {{ $payload['version'] }}
            @endif
            ha sido revisado y publicado.
        </p>

        <p style="line-height:1.7;">
            Ya puede ingresar a su cuenta para consultarlo.
        </p>
    @endif

    <h2 style="margin:28px 0 12px;font-size:17px;">
        Alcance
    </h2>

    <ul style="padding-left:20px;line-height:1.7;">
        @foreach(($payload['scope'] ?? []) as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    @if(!empty($payload['exclusions']))
        <h2 style="margin:28px 0 12px;font-size:17px;">
            No incluye automáticamente
        </h2>

        <ul style="padding-left:20px;line-height:1.7;">
            @foreach($payload['exclusions'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif

    @if(!empty($payload['action_url']))
        <p style="margin:28px 0 0;">
            <a
                href="{{ $payload['action_url'] }}"
                style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700;"
            >
                Ir a LAUDA 360
            </a>
        </p>
    @endif

    <p
        style="margin:30px 0 0;color:#667085;font-size:13px;line-height:1.6;"
    >
        LAUDA Transformación Digital 360 · Un proceso, no una instalación de software.
    </p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
