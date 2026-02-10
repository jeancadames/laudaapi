@extends('emails.layouts.lauda')

@section('content')

<!-- Preheader -->
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Activa tu prueba gratuita de 30 días en LaudaAPI.
</span>

<p class="text" style="margin:0 0 16px 0;">
    Hola {{ $activation->name }},
</p>

<p class="text" style="margin:0 0 16px 0;">
    Gracias por solicitar acceso a la prueba gratuita de <strong>30 días</strong> de LaudaAPI.
</p>

<p class="text" style="margin:0 0 24px 0;">
    Para comenzar a utilizar la plataforma y acceder a tus servicios, activa tu prueba haciendo clic en el siguiente botón:
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
                <a href="{{ $activationUrl }}"
                    style="color:#dc2626 !important; text-decoration:none; border-bottom:2px solid #dc2626; padding-bottom:2px;">
                    Activar mi prueba gratuita →
                </a>
            </p>
        </td>
    </tr>
</table>



<p class="muted" style="margin:24px 0 16px 0;">
    Este enlace es válido por <strong>24 horas</strong>.
    Si expira, podrás solicitar uno nuevo desde nuestro sitio.
</p>

<p class="text" style="margin:32px 0 0 0;">
    — Equipo LaudaAPI
</p>

@endsection