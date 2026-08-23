<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resultado Diagnóstico LAUDA 360</title>
</head>
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#F53003;">
                LAUDA Transformación Digital 360
            </div>

            <h1 style="margin:12px 0 0;font-size:24px;line-height:1.25;">
                Su resultado está disponible
            </h1>

            <p style="margin:16px 0 0;font-size:15px;line-height:1.7;color:#475569;">
                Hemos completado la revisión del Diagnóstico LAUDA 360 de
                <strong>{{ $assessment->organization_name }}</strong>.
            </p>

            <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#475569;">
                Ya puede consultar su nivel de madurez digital, capacidad interna,
                urgencia, prioridades y modalidad de acompañamiento recomendada.
            </p>

            <div style="margin-top:24px;">
                <a href="{{ $resultUrl }}"
                   style="display:inline-block;background:#F53003;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:10px;">
                    Ver resultado
                </a>
            </div>

            <p style="margin:24px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;">
                Este resultado corresponde al Diagnóstico LAUDA 360. El Informe
                Ampliado y el Roadmap Detallado son entregables posteriores.
            </p>
        </div>
    </div>
</body>
</html>
