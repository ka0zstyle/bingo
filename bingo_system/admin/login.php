<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Admin Bingo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Hoja base del admin (si la tienes). Nuestra hoja en línea la sobreescribe donde aplique. -->
    <link rel="stylesheet" href="../admin/assets/admin-style.css">

    <!-- Fuente -->
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --bg-start:#3b82f6;
            --bg-end:#6d28d9;
            --card-bg:#ffffff;
            --card-border:#e5e7eb;
            --text:#1f2937;
            --muted:#6b7280;
            --primary:#2563eb;
            --primary-hover:#1d4ed8;
            --input-bg:#f8fafc;
            --input-border:#cbd5e1;
            --input-focus:#7c3aed;
            --error:#e11d48;
            --error-bg:#ffe4e6;
            --shadow:0 10px 30px rgba(0,0,0,.12);
            --radius:14px;
        }

        /* Arregla desbordes: border-box en todo */
        *, *::before, *::after { box-sizing: border-box; }

        body.login-body{
            min-height:100vh;
            margin:0;
            font-family:'Poppins',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,Apple Color Emoji,Segoe UI Emoji,sans-serif;
            color:var(--text);
            /* Fondo con gradiente suave animado */
            background: radial-gradient(1100px 700px at 10% 10%, rgba(255,255,255,.15), transparent 60%) no-repeat,
                        radial-gradient(900px 600px at 90% 0%, rgba(255,255,255,.10), transparent 50%) no-repeat,
                        linear-gradient(135deg,var(--bg-start) 0%, var(--bg-end) 100%);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }

        .login-shell{
            width:100%;
            max-width:440px;
            background: linear-gradient(180deg, rgba(255,255,255,.55), rgba(255,255,255,.35));
            padding:10px;
            border-radius: calc(var(--radius) + 6px);
            box-shadow: var(--shadow);
            backdrop-filter: blur(6px);
        }

        .login-container{
            background:var(--card-bg);
            border:1px solid var(--card-border);
            border-radius:var(--radius);
            padding:28px 22px 26px;
            text-align:center;
            position:relative;
            overflow:hidden; /* Evita cualquier desborde visual */
        }

        .brand{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            margin-bottom:14px;
        }
        .brand-badge{
            width:44px;height:44px;border-radius:10px;
            display:grid;place-items:center;
            color:#fff;font-weight:700;
            background:linear-gradient(135deg,var(--primary),#8b5cf6);
            box-shadow: 0 8px 22px rgba(37,99,235,.35);
        }
        .login-container h2{
            font-size:1.25rem;
            margin:0 0 6px;
            font-weight:700;
            color:#374151;
        }
        .subtitle{
            font-size:.92rem;
            color:var(--muted);
            margin:0 0 18px;
        }

        .form-group{
            margin-bottom:14px;
            text-align:left;
        }
        .form-group label{
            display:block;
            font-weight:600;
            color:#4b5563;
            margin-bottom:8px;
        }

        .input-wrapper{
            position:relative;
        }
        .input-wrapper input{
            width:100%;
            max-width:100%; /* Defensa extra contra estilos externos */
            display:block;
            padding:12px 14px;
            border-radius:10px;
            border:1px solid var(--input-border);
            background:var(--input-bg);
            font-size:1rem;
            color:#111827;
            transition:border-color .15s, box-shadow .15s, background .15s;
            outline:none; /* manejamos focus manualmente */
        }
        .input-wrapper input:focus{
            border-color:var(--input-focus);
            box-shadow:0 0 0 3px rgba(124,58,237,.18);
            background:#fff;
        }

        /* Botón mostrar/ocultar password */
        .toggle-password{
            position:absolute;
            right:8px;
            top:50%;
            transform:translateY(-50%);
            background:transparent;
            border:none;
            color:#6b7280;
            padding:6px 8px;
            border-radius:8px;
            cursor:pointer;
            font-size:.95rem;
            line-height:1;
        }
        .toggle-password:hover{ color:#374151; }
        /* Asegura espacio para el botón dentro del input */
        .input-wrapper.has-toggle input{
            padding-right:44px;
        }

        .login-container button[type="submit"]{
            width:100%;
            background:var(--primary);
            color:#fff;
            padding:12px 14px;
            font-size:1rem;
            border:none;
            border-radius:10px;
            font-weight:700;
            letter-spacing:.02em;
            cursor:pointer;
            margin-top:6px;
            transition: transform .04s ease, background .15s ease, box-shadow .15s ease;
            box-shadow: 0 8px 18px rgba(37,99,235,.35);
        }
        .login-container button[type="submit"]:hover{
            background:var(--primary-hover);
            transform: translateY(-1px);
        }
        .login-container button[type="submit"]:active{
            transform: translateY(0);
            box-shadow: 0 4px 10px rgba(37,99,235,.25);
        }

        .error-message{
            color:var(--error);
            background:var(--error-bg);
            border:1px solid rgba(225,29,72,.25);
            border-radius:10px;
            padding:.85rem 1rem;
            margin:0 0 14px;
            font-weight:600;
            text-align:left;
        }

        /* Responsive */
        @media (max-width: 480px){
            .login-shell{ max-width: 96vw; padding:8px; }
            .login-container{ padding:22px 16px 20px; }
            .login-container h2{ font-size:1.1rem; }
        }
    </style>
</head>
<body class="login-body">
    <div class="login-shell">
        <div class="login-container">
            <div class="brand">
                <div class="brand-badge">AB</div>
                <div>
                    <h2>Acceso al Panel de Control</h2>
                    <p class="subtitle">Administra tu bingo/raffle de forma segura</p>
                </div>
            </div>

            <?php if (isset($_SESSION['login_error'])): ?>
                <p class="error-message"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></p>
            <?php endif; ?>

            <form action="auth.php?action=login" method="POST" autocomplete="off" novalidate>
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper has-toggle">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="toggle-password" aria-label="Mostrar u ocultar contraseña" title="Mostrar/Ocultar">
                            👁️
                        </button>
                    </div>
                </div>

                <button type="submit">Entrar</button>
            </form>
        </div>
    </div>

    <script>
        // Toggle mostrar/ocultar contraseña sin romper layout
        (function () {
            const btn = document.querySelector('.toggle-password');
            const input = document.getElementById('password');
            if (!btn || !input) return;
            btn.addEventListener('click', () => {
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                btn.textContent = isText ? '👁️' : '🙈';
            });
        })();
    </script>
</body>
</html>