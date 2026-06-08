<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Audit System - Sign In</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --blue: #1f5ab4;
            --blue-dark: #174ca2;
            --red: #d8242f;
            --gold: #f4b51b;
            --ink: #17263d;
            --muted: #7b8492;
            --line: #d7dde8;
            --panel: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background: var(--panel);
        }

        button,
        input {
            font: inherit;
        }

        .login {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 62.5%) minmax(420px, 37.5%);
        }

        .banner {
            min-height: 100vh;
            background-image: url("{{ asset('images/deped-marikina-login.jpg') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 72px;
            background: #fff;
        }

        .form {
            width: min(420px, 100%);
        }

        .form h1 {
            margin: 0 0 34px;
            color: #5b606b;
            font-size: 39px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #061a3a;
            font-size: 14px;
        }

        .field {
            position: relative;
            margin-bottom: 22px;
        }

        .field input {
            width: 100%;
            height: 49px;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 0 56px 0 44px;
            color: #596273;
            font-size: 20px;
            outline: 0;
            background: #fff;
        }

        .field input::placeholder {
            color: #6d7481;
        }

        .field input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(31, 90, 180, .12);
        }

        .icon {
            position: absolute;
            left: 15px;
            bottom: 14px;
            color: #a1a8b4;
            font-size: 17px;
            line-height: 1;
        }

        .eye {
            position: absolute;
            right: 15px;
            bottom: 14px;
            border: 0;
            padding: 0;
            color: #7e8796;
            background: transparent;
            cursor: pointer;
            font-size: 13px;
        }

        .submit {
            width: 100%;
            height: 39px;
            margin-top: 6px;
            border: 0;
            border-radius: 5px;
            color: #fff;
            background: var(--blue-dark);
            font-weight: 800;
            cursor: pointer;
        }

        .submit:hover {
            background: #123f87;
        }

        .forgot {
            display: block;
            margin: 18px 0 40px;
            color: var(--blue-dark);
            text-align: center;
            font-size: 14px;
        }

        .logos {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 28px;
            margin-bottom: 28px;
        }

        .deped {
            color: var(--blue);
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 0;
        }

        .deped span {
            color: var(--red);
        }

        .bagong {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #123f87;
            font-size: 11px;
            font-weight: 900;
            line-height: 1.05;
        }

        .ribbon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: conic-gradient(from 220deg, #1f5ab4, #d8242f, #f4b51b, #1f5ab4);
        }

        .footer,
        .demo {
            color: var(--muted);
            text-align: center;
            font-size: 14px;
        }

        .demo {
            margin-top: 16px;
        }

        .error {
            margin-bottom: 18px;
            padding: 10px 12px;
            border: 1px solid #ffc5cd;
            border-radius: 6px;
            color: #b11725;
            background: #ffecef;
            font-size: 14px;
        }

        @media (max-width: 980px) {
            .login {
                grid-template-columns: 1fr;
            }

            .banner {
                min-height: 48vh;
                background-position: center;
            }

            .panel {
                min-height: auto;
                padding: 42px 22px;
            }

            .form h1 {
                font-size: 34px;
            }
        }
    </style>
</head>
<body>
    <main class="login">
        <section class="banner" aria-label="Schools Division Office Marikina City welcome banner"></section>

        <section class="panel">
            <form class="form" method="POST" action="{{ route('login.store') }}">
                @csrf
                <h1>Sign In</h1>

                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif

                <label for="email">DepEd Email</label>
                <div class="field">
                    <span class="icon">@</span>
                    <input id="email" name="email" type="email" value="{{ old('email', 'admin@deped.gov.ph') }}" placeholder="name@deped.gov.ph" required autofocus>
                </div>

                <label for="password">Password</label>
                <div class="field">
                    <span class="icon">#</span>
                    <input id="password" name="password" type="password" placeholder="Password" required>
                    <button class="eye" type="button" data-password-toggle>Show</button>
                </div>

                <button class="submit" type="submit">Sign In</button>
                <a class="forgot" href="#">Forgot your password?</a>

                <div class="logos" aria-label="DepEd and Bagong Pilipinas logos">
                    <div class="deped">Dep<span>ED</span></div>
                    <div class="bagong">
                        <div class="ribbon"></div>
                        <div>BAGONG<br>PILIPINAS</div>
                    </div>
                </div>

                <div class="footer">Developed by SDO - Marikina ICTU</div>
                <div class="demo">Demo account: admin@deped.gov.ph / password</div>
            </form>
        </section>
    </main>

    <script>
        document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
            const button = event.currentTarget;
            const input = document.getElementById('password');
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'Hide' : 'Show';
        });
    </script>
</body>
</html>
