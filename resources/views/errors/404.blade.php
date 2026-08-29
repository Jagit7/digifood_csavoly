<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Digifood – Az oldal nem található</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('home/favicon.png') }}">
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
            background: #f4f5f9;
            color: #1c1d2b;
        }

        .container {
            text-align: center;
            padding: 48px 32px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(20, 20, 40, 0.08);
            max-width: 420px;
            width: 92%;
        }

        .logo {
            font-weight: 700;
            font-size: 22px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 28px;
            color: #1c1d2b;
        }

        .logo span {
            color: #ff8a00;
        }

        .code {
            font-size: 56px;
            font-weight: 700;
            line-height: 1;
            color: #886CC0;
            margin-bottom: 12px;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        p.sub {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 30px;
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
            background: #886CC0;
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(136, 108, 192, 0.35);
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 35px rgba(136, 108, 192, 0.45);
            background: #7a5db3;
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 8px 18px rgba(136, 108, 192, 0.35);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo"><span>DIGI</span>FOOD</div>
        <div class="code">404</div>
        <h1>Ez az oldal nem található</h1>
        <p class="sub">
            A megnyitni kívánt link vagy cím hibás, elavult, vagy időközben megváltozott.
        </p>
        @if(request()->is('szulo*'))
            {{-- A gyermek útvonala a szülői portálra utal - oda küldjük vissza,
                 ne a munkatársi belépőre, hogy a szülő ne tévedjen rossz oldalra. --}}
            <a href="{{ route('parent.login') }}" class="btn">Vissza a szülői bejelentkezéshez</a>
        @else
            {{-- A login.redirect ("/belepes") már bejelentkezett felhasználót a
                 szerepkörének megfelelő felületre irányítja, be nem
                 jelentkezettet pedig a munkatársi belépőre. --}}
            <a href="{{ route('login.redirect') }}" class="btn">Vissza a bejelentkezéshez</a>
        @endif
    </div>
</body>
</html>
