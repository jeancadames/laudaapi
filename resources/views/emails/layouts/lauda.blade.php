<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('subject', 'LaudaAPI')</title>

    <!-- Fuerza light mode -->
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #ffffff !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            color: #111827 !important;
            line-height: 1.4;
        }

        table {
            border-collapse: collapse;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 32px 16px;
        }

        .brand {
            text-align: center;
            margin-bottom: 16px;
        }

        .card {
            background-color: #ffffff !important;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #111827 !important;
            letter-spacing: -0.2px;
        }

        .text {
            font-size: 15px;
            line-height: 22px;
            color: #111827 !important;
        }

        .muted {
            font-size: 13px;
            color: #6b7280 !important;
        }

        a {
            color: #dc2626 !important;
            text-decoration: none;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            border-top: 1px solid #f3f4f6;
            padding-top: 16px;
        }

        .footer-text {
            font-size: 12px;
            color: #9ca3af !important;
        }

        /* Preheader invisible */
        .preheader {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            color: transparent !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }
    </style>
</head>

<body>

    <!-- Preheader global -->
    <span class="preheader">
        @yield('preheader')
    </span>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center"
                style="font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">

                <div class="container">

                    <!-- BRANDING -->
                    <div class="brand">
                        <div style="
                            font-size: 20px;
                            font-weight: 800;
                            color: #dc2626;
                            letter-spacing: -0.3px;
                        ">
                            LaudaAPI
                        </div>
                    </div>

                    <!-- CONTENT WRAPPER -->
                    <div class="card">
                        @yield('content')
                    </div>

                    <!-- FOOTER -->
                    <div class="footer">
                        <p class="footer-text">
                            © {{ date('Y') }} LaudaAPI — laudaapi.com
                        </p>
                    </div>

                </div>

            </td>
        </tr>
    </table>

</body>

</html>