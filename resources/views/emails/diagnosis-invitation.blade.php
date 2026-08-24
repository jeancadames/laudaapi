<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu Diagnóstico LAUDA 360 está listo</title>
</head>
<body style="margin:0;padding:0;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#17171c;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f6f8;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e7e7eb;border-radius:20px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 30px 12px;">
                        <div style="font-size:13px;font-weight:700;letter-spacing:.08em;color:#F5333C;">LAUDA 360</div>
                        <h1 style="margin:10px 0 0;font-size:28px;line-height:1.15;">Tu Diagnóstico LAUDA 360 está listo.</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 30px 30px;font-size:15px;line-height:1.65;color:#555762;">
                        <p>Hola {{ $access->contactRequest?->name ?? $access->user?->name }},</p>

                        <p>
                            Revisamos tu solicitud y habilitamos el acceso privado al diagnóstico de
                            <strong>{{ $access->contactRequest?->company ?? $access->assessment?->organization_name }}</strong>.
                        </p>

                        <p>
                            <strong>El diagnóstico inicial es gratuito.</strong>
                            Sus resultados nos permitirán identificar el nivel de madurez digital, las principales brechas,
                            la capacidad interna y las prioridades generales de transformación.
                        </p>

                        <p style="margin:28px 0;">
                            <a href="{{ $invitationUrl }}"
                               style="display:inline-block;background:#F5333C;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 22px;border-radius:12px;">
                                Comenzar diagnóstico
                            </a>
                        </p>

                        <p style="font-size:13px;color:#767985;">
                            Este enlace de activación estará disponible durante <strong>72 horas</strong>.
                            Una vez activada tu cuenta podrás volver a ingresar desde <strong>Iniciar sesión</strong>.
                        </p>

                        <p style="font-size:13px;color:#767985;">
                            Si el enlace expira antes de activarlo, LAUDA puede enviarte una nueva invitación con 72 horas de vigencia.
                        </p>

                        <p style="font-size:13px;color:#767985;">
                            Después del diagnóstico gratuito podrás decidir si deseas continuar con un Informe Ampliado,
                            un Roadmap Detallado o acompañamiento LAUDA 360. Ningún servicio de pago se activa automáticamente.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
