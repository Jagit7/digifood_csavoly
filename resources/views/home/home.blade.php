<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Digifood – Fejlesztés alatt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" property="og:title" content="DIGIFOOD">
	<meta property="og:type" content="Restaurant">
    <link rel="icon" href="{{ asset('home/favicon.png') }}">
	<meta name="image" property="og:image" content="{{ asset('home/favicon.png') }}">
	<meta name="description" property="og:description" content="Menzakezelő program vendéglátó szoftverrel. Iskolák, óvodák étkeztetésének informatikai felülete">
	<meta name="author" content="https://jtkweb.hu">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #2ec5ff 0, #007bff 40%, #001b3a 100%);
            color: #ffffff;
        }

        .container {
            text-align: center;
            padding: 40px 28px;
            background: rgba(0, 0, 0, 0.28);
            border-radius: 18px;
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.35);
            max-width: 420px;
            width: 92%;
        }

        .logo {
            font-weight: 700;
            font-size: 26px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .logo span {
            color: #ffd54a;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        p.sub {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            border-radius: 999px;
            border: none;
            outline: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #ffd54a, #ffb300);
            color: #1a1a1a;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 35px rgba(0, 0, 0, 0.45);
            background: linear-gradient(135deg, #ffe082, #ffc107);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
        }

        .note {
            margin-top: 18px;
            font-size: 11px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><span>DIGI</span>FOOD</div>
        <h1>A Digifood oldala fejlesztés alatt van</h1>
        <p class="sub">
            Dolgozunk rajta, hogy egy még kényelmesebb és modernebb felületet kapjon az iskolai és óvodai étkeztetés.
        </p>
        <a href="{{ route('login.redirect') }}" class="btn">Belépés</a>
        <div class="note">
            Ha már van Digifood hozzáférése, használja a megszokott belépési linket.
        </div>
        <div class="note" style="margin-top: 8px;">
            <a href="{{ route('legal.privacy') }}" style="color: #ffffff; opacity: 0.85; text-decoration: underline;">Adatkezelési tájékoztató</a>
        </div>
    </div>
</body>
</html>