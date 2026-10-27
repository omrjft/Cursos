<?php
require_once __DIR__ . '/database.php';

$error = "";
$emailInputValue = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $emailEntrada = $_POST["email"] ?? null;
    $passwordEntrada = $_POST["password"] ?? null;

    if (!is_string($emailEntrada) || !is_string($passwordEntrada)) {
        $error = "Los datos enviados no son válidos. Inténtalo de nuevo.";
    } else {
        $email = trim($emailEntrada);
        $password = $passwordEntrada;
        $emailInputValue = $email;

        if ($email === "" || $password === "") {
            $error = "Por favor completa todos los campos.";
        } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato de correo no es válido.";
        } else {
            try {
                $conexion = conect();
                $stmt = $conexion->prepare(
                    "SELECT id, nombre, rol_id, password_hash FROM usuarios WHERE email = ?"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $resultado = $stmt->get_result();
                $usuarioEncontrado = $resultado->fetch_assoc();
                $stmt->close();
                $conexion->close();

                $hashGuardado = $usuarioEncontrado["password_hash"] ?? null;
                $esValida = is_string($hashGuardado)
                    && (password_verify($password, $hashGuardado) || hash_equals($hashGuardado, $password));

                if (
                    $esValida
                    && isset($usuarioEncontrado["id"], $usuarioEncontrado["nombre"], $usuarioEncontrado["rol_id"])
                ) {
                    session_start();
                    session_regenerate_id(true);
                    $_SESSION["id"] = $usuarioEncontrado["id"];
                    $_SESSION["nombre"] = $usuarioEncontrado["nombre"];
                    $_SESSION["rol_id"] = $usuarioEncontrado["rol_id"];
                    header("Location: dashboard.php");
                    exit;
                }

                $error = "Credenciales incorrectas.";
            } catch (mysqli_sql_exception $exception) {
                error_log("Error en el inicio de sesión: " . $exception->getMessage());
                $error = "No fue posible iniciar sesión en este momento. Inténtalo de nuevo.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos Online — Aprender, Enseñar, Crecer</title>
    <!-- Google Fonts: Nunito para recrear la tipografía redondeada estilo Duolingo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Colores de DESIGN.md */
            --color-eager-green: #58cc02;
            --color-storybook-green: #d7ffb8;
            --color-spark-blue: #1cb0f6;
            --color-fresh-leaf: #a5ed6e;
            --color-night-ink: #000437;
            --color-paper-white: #ffffff;
            --color-charcoal: #4b4b4b;
            --color-pencil-gray: #777777;
            --color-faded-gray: #afafaf;
            --color-border-gray: #e5e5e5;
            --color-error: #ea2b2b;
            --color-error-bg: #ffdfe0;

            /* Tipografía */
            --font-family: 'Nunito', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --radius-btn: 12px;
            --radius-card: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--color-paper-white);
            color: var(--color-charcoal);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--color-border-gray);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-eager-green);
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .navbar-brand svg {
            width: 32px;
            height: 32px;
            fill: var(--color-eager-green);
        }

        .navbar-nav {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 28px;
        }

        .navbar-nav a {
            text-decoration: none;
            color: var(--color-pencil-gray);
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.053em;
            transition: color 0.15s ease;
        }

        .navbar-nav a:hover {
            color: var(--color-charcoal);
        }

        .btn-nav-login {
            background: transparent;
            color: var(--color-spark-blue);
            font-family: var(--font-family);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.053em;
            padding: 10px 18px;
            border-radius: var(--radius-btn);
            border: 2px solid var(--color-faded-gray);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .btn-nav-login:hover {
            border-color: var(--color-spark-blue);
            background-color: rgba(28, 176, 246, 0.08);
        }

        /* Contenedor principal Hero */
        .hero-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 48px 24px 64px 24px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 48px;
            align-items: center;
            flex: 1;
        }

        /* Sección Hero Izquierda */
        .hero-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .hero-badge {
            align-self: flex-start;
            color: var(--color-eager-green);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background-color: var(--color-storybook-green);
            padding: 6px 14px;
            border-radius: 999px;
        }

        .hero-title {
            font-size: 44px;
            font-weight: 900;
            line-height: 1.15;
            color: var(--color-charcoal);
            letter-spacing: -0.02em;
        }

        .hero-title span {
            color: var(--color-eager-green);
        }

        .hero-description {
            font-size: 18px;
            line-height: 1.45;
            color: var(--color-pencil-gray);
            font-weight: 500;
            max-width: 520px;
        }

        /* Formulario de Login (Derecha) */
        .login-card {
            background-color: var(--color-paper-white);
            border: 2px solid var(--color-faded-gray);
            border-radius: var(--radius-card);
            padding: 36px 32px;
            box-shadow: 0 4px 0 #e5e5e5;
        }

        .login-card-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--color-charcoal);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .login-card-subtitle {
            font-size: 15px;
            color: var(--color-pencil-gray);
            margin-bottom: 24px;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: var(--radius-btn);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: var(--color-error-bg);
            color: var(--color-error);
            border: 1px solid var(--color-error);
        }

        .form-group {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-charcoal);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Input con ícono interno */
        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: var(--color-pencil-gray);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-input {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 44px;
            font-family: var(--font-family);
            font-size: 16px;
            font-weight: 500;
            color: var(--color-charcoal);
            background-color: #f7f7f7;
            border: 2px solid var(--color-border-gray);
            border-radius: var(--radius-btn);
            outline: none;
            transition: all 0.15s ease;
        }

        .form-input:focus {
            background-color: var(--color-paper-white);
            border-color: var(--color-spark-blue);
        }

        .form-input.input-invalid {
            border-color: var(--color-error);
            background-color: #fff9f9;
        }

        /* Botón de ojo para contraseña */
        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-pencil-gray);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: color 0.15s ease;
        }

        .toggle-password-btn:hover {
            color: var(--color-charcoal);
        }

        .field-error-msg {
            color: var(--color-error);
            font-size: 12px;
            font-weight: 700;
            display: none;
            margin-top: 2px;
        }

        /* Recordarme y Olvidé contraseña */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 14px 0 20px 0;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--color-charcoal);
            user-select: none;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--color-eager-green);
            cursor: pointer;
        }

        .forgot-password-link {
            text-decoration: none;
            color: var(--color-spark-blue);
            font-weight: 700;
            transition: text-decoration 0.15s;
        }

        .forgot-password-link:hover {
            text-decoration: underline;
        }

        /* Botones de acción */
        .btn-submit {
            width: 100%;
            height: 50px;
            background-color: var(--color-eager-green);
            color: var(--color-paper-white);
            font-family: var(--font-family);
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: none;
            border-radius: var(--radius-btn);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 0 #46a302;
            transition: all 0.1s ease;
        }

        .btn-submit:hover {
            filter: brightness(1.05);
        }

        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #46a302;
        }

        /* Divisor visual */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: var(--color-pencil-gray);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 2px solid var(--color-border-gray);
        }

        .divider span {
            padding: 0 14px;
        }

        .btn-register {
            width: 100%;
            height: 48px;
            background-color: transparent;
            color: var(--color-spark-blue);
            font-family: var(--font-family);
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 2px solid var(--color-faded-gray);
            border-radius: var(--radius-btn);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-register:hover {
            border-color: var(--color-spark-blue);
            background-color: rgba(28, 176, 246, 0.05);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
                padding-top: 24px;
            }

            .navbar-nav {
                display: none;
            }

            .hero-title {
                font-size: 34px;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Navbar -->
    <header class="navbar">
        <a href="index.php" class="navbar-brand">
            <svg viewBox="0 0 24 24">
                <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
            </svg>
            Cursos Online
        </a>

        <nav>
            <ul class="navbar-nav">
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#como-funciona">¿Cómo funciona?</a></li>
                <li><a href="#contacto">Contacto</a></li>
            </ul>
        </nav>

        <a href="#login-form" class="btn-nav-login">Iniciar sesión</a>
    </header>

    <!-- Contenedor Principal: Hero + Formulario -->
    <main class="hero-container">

        <!-- 2. Sección Hero (Izquierda) -->
        <section class="hero-left" id="inicio">
            <div class="hero-badge">APRENDER - ENSEÑAR - CRECER</div>
            <h1 class="hero-title">
                La forma divertida, efectiva y personalizada de <span>aprender online</span>.
            </h1>
            <p class="hero-description">
                Únete a nuestra comunidad de aprendizaje interactivo. Cursos diseñados paso a paso para dominar habilidades reales con proyectos prácticos.
            </p>

        </section>

        <!-- 3. Formulario de Login (Derecha) -->
        <section class="login-card" id="login-form">
            <h2 class="login-card-title">Bienvenido de nuevo</h2>
            <p class="login-card-subtitle">Ingresa a tu cuenta para continuar con tus cursos</p>

            <!-- Mensaje de error dinámico en el DOM -->
            <div id="general-error" class="alert-box alert-error" style="<?= empty($error) ? 'display: none;' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <span id="general-error-text"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
            </div>

            <form id="loginForm" method="POST" novalidate>
                <!-- Campo: Correo electrónico -->
                <div class="form-group">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon-left" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="ejemplo@correo.com"
                            value="<?= htmlspecialchars($emailInputValue, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"
                            maxlength="254"
                            autocomplete="username"
                            required
                        >
                    </div>
                    <span id="email-error" class="field-error-msg"></span>
                </div>

                <!-- Campo: Contraseña -->
                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon-left" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <!-- Botón para mostrar/ocultar contraseña (ícono de ojo) -->
                        <button type="button" id="togglePasswordBtn" class="toggle-password-btn" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="password">
                            <svg id="eyeIconOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg id="eyeIconClosed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <span id="password-error" class="field-error-msg"></span>
                </div>

                <!-- Recordarme y Olvidé Contraseña -->
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        Recordarme
                    </label>
                    <a href="#olvide-contrasena" class="forgot-password-link">¿Olvidaste tu contraseña?</a>
                </div>

                <!-- Botón Submit -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    Iniciar sesión &rarr;
                </button>

                <!-- Divisor visual -->
                <div class="divider">
                    <span>o</span>
                </div>

                <!-- Botón Registrarme -->
                <a href="#registro" class="btn-register">
                    Registrarme
                </a>
            </form>
        </section>
    </main>

    <!-- 4. Validación Frontend (JS) y Funcionalidad -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const eyeIconOpen = document.getElementById('eyeIconOpen');
            const eyeIconClosed = document.getElementById('eyeIconClosed');

            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            const generalError = document.getElementById('general-error');
            const generalErrorText = document.getElementById('general-error-text');

            // 1. Funcionalidad: Mostrar / Ocultar contraseña
            togglePasswordBtn.addEventListener('click', function () {
                const mostrarPassword = passwordInput.type === 'password';
                passwordInput.type = mostrarPassword ? 'text' : 'password';
                eyeIconOpen.style.display = mostrarPassword ? 'none' : 'block';
                eyeIconClosed.style.display = mostrarPassword ? 'block' : 'none';
                togglePasswordBtn.setAttribute('aria-label', mostrarPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                togglePasswordBtn.setAttribute('aria-pressed', String(mostrarPassword));
            });

            // Función para limpiar errores visuales
            function resetErrors() {
                emailError.textContent = '';
                emailError.style.display = 'none';
                emailInput.classList.remove('input-invalid');

                passwordError.textContent = '';
                passwordError.style.display = 'none';
                passwordInput.classList.remove('input-invalid');

                generalError.style.display = 'none';
                generalErrorText.textContent = '';
            }

            // Limpieza reactiva al escribir
            emailInput.addEventListener('input', function () {
                emailInput.classList.remove('input-invalid');
                emailError.style.display = 'none';
            });

            passwordInput.addEventListener('input', function () {
                passwordInput.classList.remove('input-invalid');
                passwordError.style.display = 'none';
            });

            // 2. Validación Frontend (JS) en evento submit
            loginForm.addEventListener('submit', function (event) {
                resetErrors();

                const emailVal = emailInput.value.trim();
                const passwordVal = passwordInput.value;
                let hasError = false;

                // a) Validación de campos vacíos
                if (!emailVal) {
                    emailError.textContent = 'El correo electrónico es obligatorio.';
                    emailError.style.display = 'block';
                    emailInput.classList.add('input-invalid');
                    hasError = true;
                } else if (emailVal.length > 254 || emailInput.validity.typeMismatch) {
                    // b) Validación de formato de correo inválido
                    emailError.textContent = 'Ingresa un formato de correo válido (ej: usuario@correo.com).';
                    emailError.style.display = 'block';
                    emailInput.classList.add('input-invalid');
                    hasError = true;
                }

                if (!passwordVal) {
                    passwordError.textContent = 'La contraseña es obligatoria.';
                    passwordError.style.display = 'block';
                    passwordInput.classList.add('input-invalid');
                    hasError = true;
                }

                // Si hay campos vacíos o email inválido, detenemos el envío
                if (hasError) {
                    event.preventDefault();
                    return;
                }

                // En caso contrario, el formulario procede con el submit regular de PHP hacia el backend
            });
        });
    </script>
</body>
</html>