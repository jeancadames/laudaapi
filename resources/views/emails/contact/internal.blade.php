@extends('emails.layouts.lauda')

@section('content')

<!-- Preheader (vista previa en Gmail/Outlook) -->
<span class="preheader" style="display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden;">
    Nueva solicitud de contacto recibida en LaudaAPI.
</span>

<style>
    /* Dark mode para clientes modernos */
    @media (prefers-color-scheme: dark) {
        .email-body {
            background-color: #0f172a !important;
            color: #f1f5f9 !important;
        }

        .email-text,
        .email-table td {
            color: #f1f5f9 !important;
        }

        .email-muted {
            color: #cbd5e1 !important;
        }
    }

    /* Gmail dark mode fix */
    u+.body .email-body {
        background-color: #0f172a !important;
    }
</style>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    class="email-body"
    style="background-color:#ffffff; color:#111827;">
    <tr>
        <td style="font-size:16px; line-height:24px;" class="email-text">

            <p style="margin:0 0 16px 0; font-weight:700;">
                Nueva solicitud de contacto recibida
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

            <p style="margin-top:24px;" class="email-muted">
                Puedes responder directamente al remitente desde tu cliente de correo.
            </p>

            @isset($adminUrl)
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                <tr>
                    <td>
                        <a href="{{ $adminUrl }}"
                            style="
                                background-color:#111827;
                                color:#ffffff;
                                padding:12px 24px;
                                border-radius:6px;
                                text-decoration:none;
                                font-weight:600;
                                display:inline-block;
                           ">
                            Ver contacto en el panel
                        </a>
                    </td>
                </tr>
            </table>
            @endisset

        </td>
    </tr>
</table>

@endsection