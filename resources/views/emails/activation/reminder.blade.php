@extends('emails.layouts.lauda')

@section('content')

<!-- Preheader -->
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Recordatorio: activa tu prueba gratuita desde tu dashboard.
</span>

<p class="text" style="margin:0 0 16px 0;">
    Hola {{ $activation->name }},
</p>

<p class="text" style="margin:0 0 16px 0;">
    Tu correo ya fue confirmado y tu solicitud fue <strong>aceptada</strong>.
</p>

<p class="text" style="margin:0 0 24px 0;">
    Para comenzar a utilizar LaudaAPI, entra a tu dashboard y activa tu prueba gratuita:
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
    <tr>
        <td align="center"
            style="
                background-color:#f3f4f6;
                border-radius:8px;
                padding:14px 32px;
            ">
            <p class="text" style="margin:28px 0; font-size:17px; font-weight:600;">
                <a href="{{ $dashboardUrl }}"
                    style="color:#dc2626 !important; text-decoration:none; border-bottom:2px solid #dc2626; padding-bottom:2px;">
                    Ir a mi dashboard →
                </a>
            </p>
        </td>
    </tr>
</table>

<p class="muted" style="margin:24px 0 16px 0;">
    Si tienes alguna duda, responde a este correo y con gusto te ayudamos.
</p>

<p class="text" style="margin:32px 0 0 0;">
    — Equipo LaudaAPI
</p>

@endsection