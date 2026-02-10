@extends('emails.layouts.lauda')

@section('content')

<!-- Preheader -->
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Nueva activación de prueba registrada en LaudaAPI.
</span>

<p class="text" style="margin:0 0 16px 0; font-weight:700;">
    Nueva activación de prueba registrada
</p>

<table cellpadding="0" cellspacing="0" width="100%" style="margin-top:12px; border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0; width:140px; font-weight:600;">Nombre:</td>
        <td class="text">{{ $activation->name }}</td>
    </tr>

    <tr>
        <td style="padding:8px 0; font-weight:600;">Email:</td>
        <td class="text">{{ $activation->email }}</td>
    </tr>

    <tr>
        <td style="padding:8px 0; font-weight:600;">Empresa:</td>
        <td class="text">{{ $activation->company }}</td>
    </tr>

    @if($activation->phone)
    <tr>
        <td style="padding:8px 0; font-weight:600;">Teléfono:</td>
        <td class="text">{{ $activation->phone }}</td>
    </tr>
    @endif
</table>

<p class="muted" style="margin-top:24px;">
    Esta activación requiere seguimiento por parte del equipo comercial.
</p>

@isset($adminUrl)
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
    <tr>
        <td>
            <p class="text" style="margin:28px 0; font-size:17px; font-weight:600;">
                <a href="{{ $adminUrl }}"
                    style="color:#dc2626 !important; text-decoration:none; border-bottom:2px solid #dc2626; padding-bottom:2px;">
                    Ver activación en el panel →
                </a>
            </p>
        </td>
    </tr>
</table>
@endisset

@endsection