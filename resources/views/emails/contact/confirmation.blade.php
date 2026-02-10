@extends('emails.layouts.lauda')

@section('content')

<!-- Preheader (vista previa en Gmail/Outlook) -->
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Hemos recibido tu mensaje. Nuestro equipo te contactará pronto.
</span>

<p style="margin:0 0 16px 0;">
    Hola {{ $contact->name }},
</p>

<p style="margin:0 0 16px 0;">
    Gracias por contactarnos. Hemos recibido tu mensaje y uno de nuestros especialistas
    se pondrá en contacto contigo lo antes posible.
</p>

<p style="margin:24px 0 12px 0; font-weight:600;">
    Resumen de tu solicitud:
</p>

<table cellpadding="0" cellspacing="0" width="100%" class="email-table"
    style="margin-top:12px; border-collapse:collapse;">
    <tr>
        <td style="padding:8px 0; width:140px; font-weight:600;">Nombre:</td>
        <td>{{ $contact->name }}</td>
    </tr>
    <tr>
        <td style="padding:8px 0; font-weight:600;">Email:</td>
        <td>{{ $contact->email }}</td>
    </tr>

    @if($contact->company)
    <tr>
        <td style="padding:8px 0; font-weight:600;">Empresa:</td>
        <td>{{ $contact->company }}</td>
    </tr>
    @endif

    @if($contact->phone)
    <tr>
        <td style="padding:8px 0; font-weight:600;">Teléfono:</td>
        <td>{{ $contact->phone }}</td>
    </tr>
    @endif

    @if($contact->topic)
    <tr>
        <td style="padding:8px 0; font-weight:600;">Tema:</td>
        <td>{{ $contact->topic }}</td>
    </tr>
    @endif

    @if($contact->message)
    <tr>
        <td style="padding:8px 0; font-weight:600; vertical-align:top;">Mensaje:</td>
        <td>{{ $contact->message }}</td>
    </tr>
    @endif
</table>

<p style="margin-top:32px; color:#64748b;" class="email-muted">
    Si necesitas asistencia inmediata, puedes escribirnos a
    <a href="mailto:soporte@laudaapi.com" style="color:#0ea5e9; text-decoration:none;">
        contacto@laudaapi.com
    </a>.
</p>

<p style="margin-top:24px;">
    — Equipo LaudaAPI
</p>

@endsection