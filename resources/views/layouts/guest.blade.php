<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Luwes Pacitan — Login</title>
    <link rel="icon" href="{{ $companyLogo }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 24px;
            background:
                radial-gradient(600px circle at 15% 10%, rgba(20,184,166,.22), transparent 60%),
                radial-gradient(600px circle at 90% 90%, rgba(37,99,235,.20), transparent 60%),
                #f1f5f9;
        }

        .auth-card {
            width: 100%; max-width: 410px; padding: 40px 34px;
            background: #fff; border-radius: 24px;
            box-shadow: 0 30px 70px -28px rgba(15,23,42,.30);
            animation: rise .5s ease both;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        .auth-heading { margin: 0 0 4px; text-align: center; font-size: 28px; font-weight: 800; letter-spacing: -.02em; }
        .auth-brand {
            margin: 0 0 30px; text-align: center; font-size: 17px; font-weight: 700; letter-spacing: .01em;
            background: linear-gradient(135deg, #0f766e, #0891b2 55%, #2563eb);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        .alert-ok {
            margin-bottom: 18px; padding: 12px 14px; border-radius: 12px;
            background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 13.5px;
        }

        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 7px; font-size: 13px; font-weight: 600; color: #334155; }
        .input-wrap { position: relative; }
        .input-wrap .lead {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 15px; pointer-events: none; transition: color .15s;
        }
        .input-wrap input[type=text], .input-wrap input[type=password] {
            width: 100%; height: 50px; padding: 0 46px 0 44px;
            border: 1.5px solid #e2e8f0; border-radius: 14px; background: #f8fafc;
            font: inherit; font-size: 14.5px; color: #0f172a; outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .input-wrap input:focus { border-color: #0891b2; background: #fff; box-shadow: 0 0 0 4px rgba(8,145,178,.14); }
        .input-wrap:focus-within .lead { color: #0891b2; }
        .field.has-error input { border-color: #f43f5e; background: #fff1f2; }
        .field.has-error input:focus { box-shadow: 0 0 0 4px rgba(244,63,94,.14); }
        .field-error { margin-top: 7px; color: #e11d48; font-size: 12.5px; font-weight: 500; }

        .toggle-pw {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            width: 38px; height: 38px; border: 0; background: transparent; border-radius: 10px;
            color: #94a3b8; cursor: pointer; font-size: 16px;
        }
        .toggle-pw:hover { color: #0891b2; background: #f1f5f9; }

        .row-opts { margin: 4px 0 24px; }
        .check { display: inline-flex; align-items: center; gap: 9px; font-size: 13.5px; color: #475569; cursor: pointer; user-select: none; }
        .check input { width: 17px; height: 17px; accent-color: #0891b2; margin: 0; cursor: pointer; }

        .btn-login {
            width: 100%; height: 52px; border: 0; border-radius: 14px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            font: inherit; font-size: 15px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, #0f766e, #0891b2 60%, #2563eb);
            box-shadow: 0 10px 24px -8px rgba(8,145,178,.6);
            transition: transform .15s, box-shadow .15s, filter .15s;
        }
        .btn-login:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .btn-login:active { transform: translateY(0); }
        .btn-login[disabled] { opacity: .75; cursor: progress; transform: none; }

        @media (max-width: 480px) { .auth-card { padding: 32px 22px; } }
        @media (prefers-reduced-motion: reduce) { .auth-card { animation: none; } }
    </style>
</head>
<body>
    <div class="auth-card">
        {{ $slot }}
    </div>
</body>
</html>