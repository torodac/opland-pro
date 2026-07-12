<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin acceso — Opland</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f9fafb;
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 56px;
            height: 56px;
            background: #fff7ed;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        h1 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #111827;
        }
        p {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }
        .divider {
            border: none;
            border-top: 1px solid #f3f4f6;
            margin: 28px 0;
        }
        .back {
            display: inline-block;
            font-size: 13px;
            color: #9ca3af;
            text-decoration: none;
        }
        .back:hover { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#ea580c" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
        </div>
        <h1>Sin acceso a la aplicación</h1>
        <p>Tu usuario no tiene permisos para acceder a la plataforma web.<br>Contacta con tu administrador para solicitar acceso.</p>
        <hr class="divider">
        <a href="{{ route('logout') }}" class="back"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            Cerrar sesión
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
            @csrf
        </form>
    </div>
</body>
</html>
