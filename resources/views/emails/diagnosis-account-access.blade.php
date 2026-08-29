<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Diagnóstico 360 · LAUDAAPI</title>
</head>
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 30px;background:#0f172a;color:#ffffff;">
                        <div style="font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fb7185;">LAUDAAPI · Transformación 360</div>
                        <h1 style="margin:10px 0 0;font-size:26px;line-height:1.2;">
                            Solicitud recibida
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <p style="margin:0 0 16px;">Hola <strong>{{ $contact->name }}</strong>,</p>

                        <p style="margin:0 0 18px;line-height:1.6;">
                            Recibimos la solicitud de Diagnóstico 360 para
                            <strong>{{ $contact->company }}</strong>.
                        </p>

                        @if($accountCreated)
                            <p style="margin:0 0 18px;line-height:1.6;">
                                Creamos tu cuenta central de LAUDAAPI. Por seguridad,
                                no enviamos contraseñas temporales por correo.
                                Define tu contraseña mediante el enlace seguro:
                            </p>

                            @if($setupUrl)
                                <p style="margin:24px 0;">
                                    <a href="{{ $setupUrl }}" style="display:inline-block;background:#ef4444;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 20px;border-radius:10px;">
                                        Configurar mi contraseña
                                    </a>
                                </p>
                            @endif
                        @else
                            <p style="margin:0 0 18px;line-height:1.6;">
                                Tu correo ya está asociado a una cuenta LAUDAAPI.
                                No cambiamos tu contraseña ni creamos otra cuenta.
                                Usa tus credenciales actuales para continuar.
                            </p>
                        @endif

                        <p style="margin:0 0 18px;line-height:1.6;">
                            El Diagnóstico Inicial es una evaluación de cortesía.
                            Permanecerá pendiente de confirmación administrativa
                            hasta completar el flujo correspondiente en App Hub.
                        </p>

                        <p style="margin:24px 0;">
                            <a href="{{ $continueUrl }}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 20px;border-radius:10px;">
                                Continuar en App Hub
                            </a>
                        </p>

                        <div style="margin-top:26px;padding:16px;border-radius:12px;background:#f8fafc;font-size:13px;line-height:1.6;color:#475569;">
                            <strong>Reto indicado:</strong>
                            {{ data_get($contact->metadata, 'main_challenge', 'Por definir') }}
                            <br>
                            <strong>Acompañamiento:</strong>
                            {{ data_get($contact->metadata, 'assistance_level', 'Por recomendar') }}
                        </div>

                        <p style="margin:26px 0 0;font-size:12px;line-height:1.6;color:#64748b;">
                            Si no realizaste esta solicitud, puedes ignorar este mensaje.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
