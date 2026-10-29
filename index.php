<?php
require_once __DIR__ . '/database.php';

session_start();
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$registroError = "";
$registroMensaje = "";
$emailInputValue = "";
$nombreRegistro = "";
$emailRegistro = "";
$nombrePostulacion = "";
$emailPostulacion = "";
$experienciaPostulacion = "";
$nombrePostulacionDirector = "";
$emailPostulacionDirector = "";
$experienciaPostulacionDirector = "";
$nombrePostulacionSuperadmin = "";
$emailPostulacionSuperadmin = "";
$experienciaPostulacionSuperadmin = "";
$mostrarRegistro = false;
$mostrarPostulacion = false;
$mostrarPostulacionDirector = false;
$mostrarPostulacionSuperadmin = false;
$postulacionError = "";
$postulacionMensaje = "";
$postulacionDirectorError = "";
$postulacionDirectorMensaje = "";
$postulacionSuperadminError = "";
$postulacionSuperadminMensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST['accion'] ?? 'login';
    $csrfEntrada = $_POST['csrf_token'] ?? null;
    if (
        !is_string($csrfEntrada)
        || !hash_equals($_SESSION['csrf_token'], $csrfEntrada)
    ) {
        $error = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
        $mostrarRegistro = $accion === 'registro';
        $mostrarPostulacion = $accion === 'postulacion_ponente';
        $mostrarPostulacionDirector = $accion === 'postulacion_director';
        $mostrarPostulacionSuperadmin = $accion === 'postulacion_superadmin';
    } elseif ($accion === 'registro') {
        $mostrarRegistro = true;
        $nombreEntrada = $_POST['nombre'] ?? null;
        $emailEntrada = $_POST['email'] ?? null;
        $passwordEntrada = $_POST['password'] ?? null;
        $confirmacionEntrada = $_POST['confirmar_password'] ?? null;

        if (
            !is_string($nombreEntrada)
            || !is_string($emailEntrada)
            || !is_string($passwordEntrada)
            || !is_string($confirmacionEntrada)
        ) {
            $registroError = 'Los datos enviados no son válidos. Inténtalo de nuevo.';
        } else {
            $nombreRegistro = trim($nombreEntrada);
            $emailRegistro = trim($emailEntrada);

            if ($nombreRegistro === '' || strlen($nombreRegistro) > 100) {
                $registroError = 'Ingresa tu nombre (máximo 100 caracteres).';
            } elseif (
                $emailRegistro === ''
                || strlen($emailRegistro) > 150
                || !filter_var($emailRegistro, FILTER_VALIDATE_EMAIL)
            ) {
                $registroError = 'Ingresa un correo válido (máximo 150 caracteres).';
            } elseif (strlen($passwordEntrada) < 10) {
                $registroError = 'La contraseña debe tener al menos 10 caracteres.';
            } elseif ($passwordEntrada !== $confirmacionEntrada) {
                $registroError = 'Las contraseñas no coinciden.';
            } else {
                try {
                    $conexion = conect();
                    $stmtRol = $conexion->prepare("SELECT id FROM roles WHERE nombre = 'alumno' LIMIT 1");
                    $stmtRol->execute();
                    $rolAlumno = $stmtRol->get_result()->fetch_assoc();
                    $stmtRol->close();

                    if (!$rolAlumno) {
                        throw new RuntimeException('No existe el rol de alumno en la base de datos.');
                    }

                    $hashNuevo = password_hash($passwordEntrada, PASSWORD_DEFAULT);
                    $rolId = (int)$rolAlumno['id'];
                    $stmtCrear = $conexion->prepare(
                        "INSERT INTO usuarios (rol_id, nombre, email, password_hash, estado)
                         VALUES (?, ?, ?, ?, 'Activo')"
                    );
                    $stmtCrear->bind_param('isss', $rolId, $nombreRegistro, $emailRegistro, $hashNuevo);
                    $stmtCrear->execute();
                    $stmtCrear->close();
                    $conexion->close();

                    $registroMensaje = 'Tu cuenta fue creada. Ya puedes iniciar sesión.';
                    $emailInputValue = $emailRegistro;
                    $nombreRegistro = '';
                    $emailRegistro = '';
                    $mostrarRegistro = false;
                } catch (mysqli_sql_exception $exception) {
                    if (isset($conexion) && $conexion instanceof mysqli) {
                        $conexion->close();
                    }
                    if ($exception->getCode() === 1062) {
                        $registroError = 'Ya existe una cuenta con ese correo.';
                    } else {
                        error_log('Error al registrar alumno: ' . $exception->getMessage());
                        $registroError = 'No fue posible crear la cuenta en este momento. Inténtalo de nuevo.';
                    }
                } catch (RuntimeException $exception) {
                    if (isset($conexion) && $conexion instanceof mysqli) {
                        $conexion->close();
                    }
                    error_log('Error al registrar alumno: ' . $exception->getMessage());
                    $registroError = 'No fue posible crear la cuenta en este momento. Inténtalo de nuevo.';
                }
            }
        }
    } elseif ($accion === 'postulacion_ponente') {
        $mostrarPostulacion = true;
        $nombreEntrada = $_POST['nombre'] ?? null;
        $emailEntrada = $_POST['email'] ?? null;
        $passwordEntrada = $_POST['password'] ?? null;
        $confirmacionEntrada = $_POST['confirmar_password'] ?? null;
        $experienciaEntrada = $_POST['experiencia'] ?? null;

        if (
            !is_string($nombreEntrada)
            || !is_string($emailEntrada)
            || !is_string($passwordEntrada)
            || !is_string($confirmacionEntrada)
            || !is_string($experienciaEntrada)
        ) {
            $postulacionError = 'Los datos enviados no son válidos. Inténtalo de nuevo.';
        } else {
            $nombrePostulacion = trim($nombreEntrada);
            $emailPostulacion = trim($emailEntrada);
            $experienciaPostulacion = trim($experienciaEntrada);

            if ($nombrePostulacion === '' || strlen($nombrePostulacion) > 100) {
                $postulacionError = 'Ingresa tu nombre (máximo 100 caracteres).';
            } elseif (
                $emailPostulacion === ''
                || strlen($emailPostulacion) > 150
                || !filter_var($emailPostulacion, FILTER_VALIDATE_EMAIL)
            ) {
                $postulacionError = 'Ingresa un correo válido (máximo 150 caracteres).';
            } elseif (strlen($passwordEntrada) < 10) {
                $postulacionError = 'La contraseña debe tener al menos 10 caracteres.';
            } elseif ($passwordEntrada !== $confirmacionEntrada) {
                $postulacionError = 'Las contraseñas no coinciden.';
            } elseif (
                strlen($experienciaPostulacion) < 20
                || strlen($experienciaPostulacion) > 2000
            ) {
                $postulacionError = 'Describe brevemente tu experiencia o especialidad (de 20 a 2000 caracteres).';
            } else {
                try {
                    $conexion = conect();
                    $stmtCuentaExistente = $conexion->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $stmtCuentaExistente->bind_param('s', $emailPostulacion);
                    $stmtCuentaExistente->execute();
                    $cuentaExistente = $stmtCuentaExistente->get_result()->fetch_assoc();
                    $stmtCuentaExistente->close();

                    if ($cuentaExistente) {
                        $postulacionError = 'Ya existe una cuenta con ese correo.';
                    } else {
                        $stmtPendiente = $conexion->prepare(
                            "SELECT id FROM postulaciones_ponentes
                             WHERE email = ? AND estado = 'pendiente' LIMIT 1"
                        );
                        $stmtPendiente->bind_param('s', $emailPostulacion);
                        $stmtPendiente->execute();
                        $solicitudPendiente = $stmtPendiente->get_result()->fetch_assoc();
                        $stmtPendiente->close();

                        if ($solicitudPendiente) {
                            $postulacionError = 'Ya existe una postulación pendiente con ese correo.';
                        } else {
                            $passwordHash = password_hash($passwordEntrada, PASSWORD_DEFAULT);
                            $stmtPostulacion = $conexion->prepare(
                                "INSERT INTO postulaciones_ponentes
                                    (nombre, email, password_hash, experiencia)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $stmtPostulacion->bind_param(
                                'ssss',
                                $nombrePostulacion,
                                $emailPostulacion,
                                $passwordHash,
                                $experienciaPostulacion
                            );
                            $stmtPostulacion->execute();
                            $stmtPostulacion->close();
                            $postulacionMensaje = 'Tu postulación fue enviada. Un director revisará tus datos; podrás iniciar sesión si es aprobada.';
                            $nombrePostulacion = '';
                            $emailPostulacion = '';
                            $experienciaPostulacion = '';
                            $mostrarPostulacion = false;
                        }
                    }

                    $conexion->close();
                } catch (mysqli_sql_exception $exception) {
                    if (isset($conexion) && $conexion instanceof mysqli) {
                        $conexion->close();
                    }
                    if ($exception->getCode() === 1062) {
                        $postulacionError = 'Ya existe una postulación pendiente con ese correo.';
                    } else {
                        error_log('Error al registrar postulación de ponente: ' . $exception->getMessage());
                        $postulacionError = 'No fue posible enviar la postulación en este momento. Inténtalo de nuevo.';
                    }
                }
            }
        }
    } elseif ($accion === 'postulacion_director') {
        $mostrarPostulacionDirector = true;
        $nombreEntrada = $_POST['nombre'] ?? null;
        $emailEntrada = $_POST['email'] ?? null;
        $passwordEntrada = $_POST['password'] ?? null;
        $confirmacionEntrada = $_POST['confirmar_password'] ?? null;
        $experienciaEntrada = $_POST['experiencia'] ?? null;

        if (
            !is_string($nombreEntrada)
            || !is_string($emailEntrada)
            || !is_string($passwordEntrada)
            || !is_string($confirmacionEntrada)
            || !is_string($experienciaEntrada)
        ) {
            $postulacionDirectorError = 'Los datos enviados no son válidos. Inténtalo de nuevo.';
        } else {
            $nombrePostulacionDirector = trim($nombreEntrada);
            $emailPostulacionDirector = trim($emailEntrada);
            $experienciaPostulacionDirector = trim($experienciaEntrada);

            if ($nombrePostulacionDirector === '' || strlen($nombrePostulacionDirector) > 100) {
                $postulacionDirectorError = 'Ingresa tu nombre (máximo 100 caracteres).';
            } elseif (
                $emailPostulacionDirector === ''
                || strlen($emailPostulacionDirector) > 150
                || !filter_var($emailPostulacionDirector, FILTER_VALIDATE_EMAIL)
            ) {
                $postulacionDirectorError = 'Ingresa un correo válido (máximo 150 caracteres).';
            } elseif (strlen($passwordEntrada) < 10) {
                $postulacionDirectorError = 'La contraseña debe tener al menos 10 caracteres.';
            } elseif ($passwordEntrada !== $confirmacionEntrada) {
                $postulacionDirectorError = 'Las contraseñas no coinciden.';
            } elseif (
                strlen($experienciaPostulacionDirector) < 20
                || strlen($experienciaPostulacionDirector) > 2000
            ) {
                $postulacionDirectorError = 'Describe brevemente tu experiencia o especialidad (de 20 a 2000 caracteres).';
            } else {
                try {
                    $conexion = conect();
                    $stmtCuentaExistente = $conexion->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $stmtCuentaExistente->bind_param('s', $emailPostulacionDirector);
                    $stmtCuentaExistente->execute();
                    $cuentaExistente = $stmtCuentaExistente->get_result()->fetch_assoc();
                    $stmtCuentaExistente->close();

                    if ($cuentaExistente) {
                        $postulacionDirectorError = 'Ya existe una cuenta con ese correo.';
                    } else {
                        $stmtPendiente = $conexion->prepare(
                            "SELECT id FROM postulaciones_directores
                             WHERE email = ? AND estado = 'pendiente' LIMIT 1"
                        );
                        $stmtPendiente->bind_param('s', $emailPostulacionDirector);
                        $stmtPendiente->execute();
                        $solicitudPendiente = $stmtPendiente->get_result()->fetch_assoc();
                        $stmtPendiente->close();

                        if ($solicitudPendiente) {
                            $postulacionDirectorError = 'Ya existe una postulación pendiente con ese correo.';
                        } else {
                            $passwordHash = password_hash($passwordEntrada, PASSWORD_DEFAULT);
                            $stmtPostulacion = $conexion->prepare(
                                "INSERT INTO postulaciones_directores
                                    (nombre, email, password_hash, experiencia)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $stmtPostulacion->bind_param(
                                'ssss',
                                $nombrePostulacionDirector,
                                $emailPostulacionDirector,
                                $passwordHash,
                                $experienciaPostulacionDirector
                            );
                            $stmtPostulacion->execute();
                            $stmtPostulacion->close();
                            $postulacionDirectorMensaje = 'Tu postulación fue enviada. Un superadmin revisará tus datos; podrás iniciar sesión si es aprobada.';
                            $nombrePostulacionDirector = '';
                            $emailPostulacionDirector = '';
                            $experienciaPostulacionDirector = '';
                            $mostrarPostulacionDirector = false;
                        }
                    }

                    $conexion->close();
                } catch (mysqli_sql_exception $exception) {
                    if (isset($conexion) && $conexion instanceof mysqli) {
                        $conexion->close();
                    }
                    if ($exception->getCode() === 1062) {
                        $postulacionDirectorError = 'Ya existe una postulación pendiente con ese correo.';
                    } else {
                        error_log('Error al registrar postulación de director: ' . $exception->getMessage());
                        $postulacionDirectorError = 'No fue posible enviar la postulación en este momento. Inténtalo de nuevo.';
                    }
                }
            }
        }
    } elseif ($accion === 'postulacion_superadmin') {
        $mostrarPostulacionSuperadmin = true;
        $nombreEntrada = $_POST['nombre'] ?? null;
        $emailEntrada = $_POST['email'] ?? null;
        $passwordEntrada = $_POST['password'] ?? null;
        $confirmacionEntrada = $_POST['confirmar_password'] ?? null;
        $experienciaEntrada = $_POST['experiencia'] ?? null;

        if (
            !is_string($nombreEntrada)
            || !is_string($emailEntrada)
            || !is_string($passwordEntrada)
            || !is_string($confirmacionEntrada)
            || !is_string($experienciaEntrada)
        ) {
            $postulacionSuperadminError = 'Los datos enviados no son válidos. Inténtalo de nuevo.';
        } else {
            $nombrePostulacionSuperadmin = trim($nombreEntrada);
            $emailPostulacionSuperadmin = trim($emailEntrada);
            $experienciaPostulacionSuperadmin = trim($experienciaEntrada);

            if ($nombrePostulacionSuperadmin === '' || strlen($nombrePostulacionSuperadmin) > 100) {
                $postulacionSuperadminError = 'Ingresa tu nombre (máximo 100 caracteres).';
            } elseif (
                $emailPostulacionSuperadmin === ''
                || strlen($emailPostulacionSuperadmin) > 150
                || !filter_var($emailPostulacionSuperadmin, FILTER_VALIDATE_EMAIL)
            ) {
                $postulacionSuperadminError = 'Ingresa un correo válido (máximo 150 caracteres).';
            } elseif (strlen($passwordEntrada) < 10) {
                $postulacionSuperadminError = 'La contraseña debe tener al menos 10 caracteres.';
            } elseif ($passwordEntrada !== $confirmacionEntrada) {
                $postulacionSuperadminError = 'Las contraseñas no coinciden.';
            } elseif (
                strlen($experienciaPostulacionSuperadmin) < 20
                || strlen($experienciaPostulacionSuperadmin) > 2000
            ) {
                $postulacionSuperadminError = 'Describe brevemente tu experiencia o especialidad (de 20 a 2000 caracteres).';
            } else {
                try {
                    $conexion = conect();
                    $stmtCuentaExistente = $conexion->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $stmtCuentaExistente->bind_param('s', $emailPostulacionSuperadmin);
                    $stmtCuentaExistente->execute();
                    $cuentaExistente = $stmtCuentaExistente->get_result()->fetch_assoc();
                    $stmtCuentaExistente->close();

                    if ($cuentaExistente) {
                        $postulacionSuperadminError = 'Ya existe una cuenta con ese correo.';
                    } else {
                        $stmtPendiente = $conexion->prepare(
                            "SELECT id FROM postulaciones_superadmins
                             WHERE email = ? AND estado = 'pendiente' LIMIT 1"
                        );
                        $stmtPendiente->bind_param('s', $emailPostulacionSuperadmin);
                        $stmtPendiente->execute();
                        $solicitudPendiente = $stmtPendiente->get_result()->fetch_assoc();
                        $stmtPendiente->close();

                        if ($solicitudPendiente) {
                            $postulacionSuperadminError = 'Ya existe una postulación pendiente con ese correo.';
                        } else {
                            $passwordHash = password_hash($passwordEntrada, PASSWORD_DEFAULT);
                            $stmtPostulacion = $conexion->prepare(
                                "INSERT INTO postulaciones_superadmins
                                    (nombre, email, password_hash, experiencia)
                                 VALUES (?, ?, ?, ?)"
                            );
                            $stmtPostulacion->bind_param(
                                'ssss',
                                $nombrePostulacionSuperadmin,
                                $emailPostulacionSuperadmin,
                                $passwordHash,
                                $experienciaPostulacionSuperadmin
                            );
                            $stmtPostulacion->execute();
                            $stmtPostulacion->close();
                            $postulacionSuperadminMensaje = 'Tu postulación fue enviada. Un superadmin existente revisará tus datos; podrás iniciar sesión si es aprobada.';
                            $nombrePostulacionSuperadmin = '';
                            $emailPostulacionSuperadmin = '';
                            $experienciaPostulacionSuperadmin = '';
                            $mostrarPostulacionSuperadmin = false;
                        }
                    }

                    $conexion->close();
                } catch (mysqli_sql_exception $exception) {
                    if (isset($conexion) && $conexion instanceof mysqli) {
                        $conexion->close();
                    }
                    if ($exception->getCode() === 1062) {
                        $postulacionSuperadminError = 'Ya existe una postulación pendiente con ese correo.';
                    } else {
                        error_log('Error al registrar postulación de superadmin: ' . $exception->getMessage());
                        $postulacionSuperadminError = 'No fue posible enviar la postulación en este momento. Inténtalo de nuevo.';
                    }
                }
            }
        }
    } elseif ($accion === 'login') {
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
                        "SELECT id, nombre, rol_id, password_hash, estado FROM usuarios WHERE email = ?"
                    );
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $resultado = $stmt->get_result();
                    $usuarioEncontrado = $resultado->fetch_assoc();
                    $stmt->close();
                    $conexion->close();

                    $hashGuardado = $usuarioEncontrado["password_hash"] ?? null;
                    $esValida = is_string($hashGuardado)
                        && password_verify($password, $hashGuardado);

                    if (
                        $esValida
                        && isset($usuarioEncontrado["id"], $usuarioEncontrado["nombre"], $usuarioEncontrado["rol_id"])
                        && ($usuarioEncontrado["estado"] ?? '') === 'Activo'
                    ) {
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
    } else {
        $error = 'La acción solicitada no es válida.';
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
            min-height: 100svh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            width: 100%;
            padding: 20px clamp(20px, 5vw, 72px);
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
            width: 100%;
            min-height: calc(100vh - 74px);
            min-height: calc(100svh - 74px);
            padding: clamp(32px, 6vh, 72px) clamp(24px, 8vw, 120px);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(360px, 0.9fr);
            gap: clamp(32px, 7vw, 104px);
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

        .roles-section {
            margin-top: 12px;
        }

        .roles-heading {
            margin-bottom: 14px;
            color: var(--color-charcoal);
            font-size: 19px;
            font-weight: 900;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .role-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            padding: 16px;
            background: var(--color-paper-white);
            border: 2px solid var(--color-border-gray);
            border-radius: var(--radius-card);
        }

        .role-card h3 {
            color: var(--color-charcoal);
            font-size: 16px;
            font-weight: 900;
        }

        .role-card p {
            flex: 1;
            color: var(--color-pencil-gray);
            font-size: 13px;
            line-height: 1.45;
        }

        .role-apply-button {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 2px solid var(--color-faded-gray);
            border-radius: 10px;
            background: var(--color-paper-white);
            color: var(--color-spark-blue);
            font: 800 12px var(--font-family);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .role-apply-button:hover {
            border-color: var(--color-spark-blue);
            background: rgba(28, 176, 246, 0.05);
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

        .info-section {
            width: min(1120px, calc(100% - 48px));
            margin: 0 auto;
            padding: 64px 0;
            scroll-margin-top: 24px;
        }

        .info-section-heading {
            max-width: 700px;
            margin-bottom: 28px;
        }

        .info-section-heading h2 {
            color: var(--color-charcoal);
            font-size: 32px;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 10px;
        }

        .info-section-heading p {
            color: var(--color-pencil-gray);
            font-size: 17px;
            line-height: 1.5;
        }

        .feature-grid,
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
            gap: 18px;
        }

        .info-card {
            background: var(--color-paper-white);
            border: 2px solid var(--color-border-gray);
            border-radius: var(--radius-card);
            padding: 24px;
        }

        .info-card h3 {
            color: var(--color-charcoal);
            font-size: 18px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .info-card p {
            color: var(--color-pencil-gray);
            font-size: 15px;
            line-height: 1.55;
        }

        .info-card-number {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            border-radius: 50%;
            background: var(--color-storybook-green);
            color: #2e7a00;
            font-weight: 900;
        }

        .info-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 12px auto 56px;
            padding: 28px;
            width: min(1120px, calc(100% - 48px));
            border-radius: var(--radius-card);
            background: #f2fbe9;
        }

        .info-cta p {
            color: var(--color-charcoal);
            font-size: 18px;
            font-weight: 800;
        }

        .info-cta .btn-nav-login {
            flex: 0 0 auto;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
                padding: 32px clamp(24px, 6vw, 56px) 48px;
            }

            .navbar-nav {
                display: none;
            }

            .hero-title {
                font-size: 34px;
            }

            .roles-grid {
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
            }
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 16px;
            }

            .navbar-brand {
                font-size: 22px;
            }

            .hero-container {
                min-height: calc(100vh - 66px);
                min-height: calc(100svh - 66px);
                padding: 28px 16px 40px;
                gap: 28px;
            }

            .login-card {
                padding: 28px 20px;
            }

            .info-section {
                padding: 42px 0;
            }

            .info-section-heading h2 {
                font-size: 27px;
            }

            .info-cta {
                align-items: stretch;
                flex-direction: column;
                width: min(1120px, calc(100% - 32px));
                padding: 22px;
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
                <li><a href="#features">Features</a></li>
                <li><a href="#como-funciona">¿Cómo funciona?</a></li>
                <li><a href="https://omrjft.github.io/" target="_blank" rel="noopener noreferrer">Contacto</a></li>
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

            <section class="roles-section" aria-labelledby="roles-heading">
                <h2 class="roles-heading" id="roles-heading">Forma parte de la plataforma</h2>
                <div class="roles-grid">
                    <article class="role-card">
                        <h3>Administrador</h3>
                        <p>Administra usuarios, cursos y la configuración general de la plataforma.</p>
                        <button type="button" class="role-apply-button" id="showSuperadminApplication">
                            Postularme como superadmin
                        </button>
                    </article>
                    <article class="role-card">
                        <h3>Director</h3>
                        <p>Supervisa la actividad académica y consulta reportes de cursos y alumnos.</p>
                        <button type="button" class="role-apply-button" id="showDirectorApplication">
                            Postularme como director
                        </button>
                    </article>
                    <article class="role-card">
                        <h3>Ponente</h3>
                        <p>Crea cursos, organiza temarios y acompaña el avance de los alumnos.</p>
                        <button type="button" class="role-apply-button" id="showLecturerApplication">
                            Postularme como ponente
                        </button>
                    </article>
                </div>
            </section>

        </section>

        <!-- 3. Formulario de Login (Derecha) -->
        <section class="login-card" id="login-form">
            <div id="loginPanel" <?= ($mostrarRegistro || $mostrarPostulacion || $mostrarPostulacionDirector || $mostrarPostulacionSuperadmin) ? 'hidden' : '' ?>>
                <h2 class="login-card-title">Bienvenido de nuevo</h2>
                <p class="login-card-subtitle">Ingresa a tu cuenta para continuar con tus cursos</p>

                <?php if ($registroMensaje !== ''): ?>
                    <div class="alert-box" role="status"><?= htmlspecialchars($registroMensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($postulacionMensaje !== ''): ?>
                    <div class="alert-box" role="status"><?= htmlspecialchars($postulacionMensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($postulacionDirectorMensaje !== ''): ?>
                    <div class="alert-box" role="status"><?= htmlspecialchars($postulacionDirectorMensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($postulacionSuperadminMensaje !== ''): ?>
                    <div class="alert-box" role="status"><?= htmlspecialchars($postulacionSuperadminMensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>

                <!-- Mensaje de error dinámico en el DOM -->
                <div id="general-error" class="alert-box alert-error" style="<?= empty($error) || $mostrarRegistro ? 'display: none;' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <span id="general-error-text"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></span>
                </div>

                <form id="loginForm" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="login">
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

                <!-- Botón Submit -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    Iniciar sesión &rarr;
                </button>

                <!-- Divisor visual -->
                <div class="divider">
                    <span>o</span>
                </div>

                <!-- Botón Registrarme -->
                <button type="button" class="btn-register" id="showRegistration">
                    Registrarme
                </button>
                </form>
            </div>

            <div id="registrationPanel" <?= $mostrarRegistro ? '' : 'hidden' ?>>
                <h2 class="login-card-title">Crear cuenta de alumno</h2>
                <p class="login-card-subtitle">Regístrate para inscribirte a cursos y dar seguimiento a tu aprendizaje.</p>

                <?php
                $mensajeErrorRegistro = $registroError !== '' ? $registroError : ($mostrarRegistro ? $error : '');
                ?>
                <?php if ($mensajeErrorRegistro !== ''): ?>
                    <div class="alert-box alert-error" role="alert">
                        <?= htmlspecialchars($mensajeErrorRegistro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="registro">

                    <div class="form-group">
                        <label for="nombreRegistro" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            id="nombreRegistro"
                            name="nombre"
                            class="form-input"
                            value="<?= htmlspecialchars($nombreRegistro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="emailRegistro" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            id="emailRegistro"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($emailRegistro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="passwordRegistro" class="form-label">Contraseña (mínimo 10 caracteres)</label>
                        <input
                            type="password"
                            id="passwordRegistro"
                            name="password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirmarPasswordRegistro" class="form-label">Confirmar contraseña</label>
                        <input
                            type="password"
                            id="confirmarPasswordRegistro"
                            name="confirmar_password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn-submit">Crear cuenta</button>
                    <button type="button" class="btn-register" id="showLogin">Volver al inicio de sesión</button>
                </form>
            </div>

            <div id="lecturerApplicationPanel" <?= $mostrarPostulacion ? '' : 'hidden' ?>>
                <h2 class="login-card-title">Postulación como ponente</h2>
                <p class="login-card-subtitle">Comparte tus datos y experiencia. Tu cuenta se habilitará solo si un director aprueba la solicitud.</p>

                <?php
                $mensajeErrorPostulacion = $postulacionError !== ''
                    ? $postulacionError
                    : ($mostrarPostulacion ? $error : '');
                ?>
                <?php if ($mensajeErrorPostulacion !== ''): ?>
                    <div class="alert-box alert-error" role="alert">
                        <?= htmlspecialchars($mensajeErrorPostulacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form id="lecturerApplicationForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="postulacion_ponente">

                    <div class="form-group">
                        <label for="nombrePostulacion" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            id="nombrePostulacion"
                            name="nombre"
                            class="form-input"
                            value="<?= htmlspecialchars($nombrePostulacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="emailPostulacion" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            id="emailPostulacion"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($emailPostulacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="passwordPostulacion" class="form-label">Contraseña (mínimo 10 caracteres)</label>
                        <input
                            type="password"
                            id="passwordPostulacion"
                            name="password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirmarPasswordPostulacion" class="form-label">Confirmar contraseña</label>
                        <input
                            type="password"
                            id="confirmarPasswordPostulacion"
                            name="confirmar_password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="experienciaPostulacion" class="form-label">Experiencia o especialidad</label>
                        <textarea
                            id="experienciaPostulacion"
                            name="experiencia"
                            class="form-input"
                            rows="4"
                            minlength="20"
                            maxlength="2000"
                            required
                            style="height: auto; min-height: 110px; padding: 12px; resize: vertical;"
                        ><?= htmlspecialchars($experienciaPostulacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Enviar postulación</button>
                    <button type="button" class="btn-register" id="showLoginFromApplication">Volver al inicio de sesión</button>
                </form>
            </div>

            <div id="directorApplicationPanel" <?= $mostrarPostulacionDirector ? '' : 'hidden' ?>>
                <h2 class="login-card-title">Postulación como director</h2>
                <p class="login-card-subtitle">Comparte tus datos y experiencia. Tu cuenta se habilitará solo si un superadmin aprueba la solicitud.</p>

                <?php
                $mensajeErrorPostulacionDirector = $postulacionDirectorError !== ''
                    ? $postulacionDirectorError
                    : ($mostrarPostulacionDirector ? $error : '');
                ?>
                <?php if ($mensajeErrorPostulacionDirector !== ''): ?>
                    <div class="alert-box alert-error" role="alert">
                        <?= htmlspecialchars($mensajeErrorPostulacionDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form id="directorApplicationForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="postulacion_director">

                    <div class="form-group">
                        <label for="nombrePostulacionDirector" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            id="nombrePostulacionDirector"
                            name="nombre"
                            class="form-input"
                            value="<?= htmlspecialchars($nombrePostulacionDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="emailPostulacionDirector" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            id="emailPostulacionDirector"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($emailPostulacionDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="passwordPostulacionDirector" class="form-label">Contraseña (mínimo 10 caracteres)</label>
                        <input
                            type="password"
                            id="passwordPostulacionDirector"
                            name="password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirmarPasswordPostulacionDirector" class="form-label">Confirmar contraseña</label>
                        <input
                            type="password"
                            id="confirmarPasswordPostulacionDirector"
                            name="confirmar_password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="experienciaPostulacionDirector" class="form-label">Experiencia o especialidad</label>
                        <textarea
                            id="experienciaPostulacionDirector"
                            name="experiencia"
                            class="form-input"
                            rows="4"
                            minlength="20"
                            maxlength="2000"
                            required
                            style="height: auto; min-height: 110px; padding: 12px; resize: vertical;"
                        ><?= htmlspecialchars($experienciaPostulacionDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Enviar postulación</button>
                    <button type="button" class="btn-register" id="showLoginFromDirectorApplication">Volver al inicio de sesión</button>
                </form>
            </div>

            <div id="superadminApplicationPanel" <?= $mostrarPostulacionSuperadmin ? '' : 'hidden' ?>>
                <h2 class="login-card-title">Postulación como superadmin</h2>
                <p class="login-card-subtitle">Comparte tus datos y experiencia. Solo un superadmin ya existente puede aprobar tu solicitud.</p>

                <?php
                $mensajeErrorPostulacionSuperadmin = $postulacionSuperadminError !== ''
                    ? $postulacionSuperadminError
                    : ($mostrarPostulacionSuperadmin ? $error : '');
                ?>
                <?php if ($mensajeErrorPostulacionSuperadmin !== ''): ?>
                    <div class="alert-box alert-error" role="alert">
                        <?= htmlspecialchars($mensajeErrorPostulacionSuperadmin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form id="superadminApplicationForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="accion" value="postulacion_superadmin">

                    <div class="form-group">
                        <label for="nombrePostulacionSuperadmin" class="form-label">Nombre completo</label>
                        <input
                            type="text"
                            id="nombrePostulacionSuperadmin"
                            name="nombre"
                            class="form-input"
                            value="<?= htmlspecialchars($nombrePostulacionSuperadmin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="emailPostulacionSuperadmin" class="form-label">Correo electrónico</label>
                        <input
                            type="email"
                            id="emailPostulacionSuperadmin"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($emailPostulacionSuperadmin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="passwordPostulacionSuperadmin" class="form-label">Contraseña (mínimo 10 caracteres)</label>
                        <input
                            type="password"
                            id="passwordPostulacionSuperadmin"
                            name="password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirmarPasswordPostulacionSuperadmin" class="form-label">Confirmar contraseña</label>
                        <input
                            type="password"
                            id="confirmarPasswordPostulacionSuperadmin"
                            name="confirmar_password"
                            class="form-input"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="experienciaPostulacionSuperadmin" class="form-label">Experiencia o especialidad</label>
                        <textarea
                            id="experienciaPostulacionSuperadmin"
                            name="experiencia"
                            class="form-input"
                            rows="4"
                            minlength="20"
                            maxlength="2000"
                            required
                            style="height: auto; min-height: 110px; padding: 12px; resize: vertical;"
                        ><?= htmlspecialchars($experienciaPostulacionSuperadmin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Enviar postulación</button>
                    <button type="button" class="btn-register" id="showLoginFromSuperadminApplication">Volver al inicio de sesión</button>
                </form>
            </div>
        </section>
    </main>

    <section class="info-section" id="features" aria-labelledby="features-heading">
        <div class="info-section-heading">
            <h2 id="features-heading">Aprende con objetivos claros</h2>
            <p>Una experiencia para avanzar tema por tema, con acompañamiento del ponente y seguimiento de tu progreso.</p>
        </div>
        <div class="feature-grid">
            <article class="info-card">
                <h3>Cursos organizados por temas</h3>
                <p>Explora cursos publicados y encuentra el contenido organizado en bloques para seguir una ruta de aprendizaje.</p>
            </article>
            <article class="info-card">
                <h3>Revisión con acompañamiento</h3>
                <p>Solicita la revisión de cada tema. El ponente evalúa tu avance y comparte observaciones para ayudarte a mejorar.</p>
            </article>
            <article class="info-card">
                <h3>Progreso basado en aprobaciones</h3>
                <p>Consulta tu avance y distingue los temas pendientes, los que requieren corrección y los que ya fueron aprobados.</p>
            </article>
        </div>
    </section>

    <section class="info-section" id="como-funciona" aria-labelledby="como-funciona-heading">
        <div class="info-section-heading">
            <h2 id="como-funciona-heading">¿Cómo funciona?</h2>
            <p>Empieza a aprender en unos pocos pasos y recibe retroalimentación durante el curso.</p>
        </div>
        <div class="steps-grid">
            <article class="info-card">
                <span class="info-card-number" aria-hidden="true">1</span>
                <h3>Explora e inscríbete</h3>
                <p>Inicia sesión, revisa los cursos disponibles y únete a los que te interesen.</p>
            </article>
            <article class="info-card">
                <span class="info-card-number" aria-hidden="true">2</span>
                <h3>Estudia cada tema</h3>
                <p>Avanza por el temario y consulta los materiales que el curso tiene disponibles.</p>
            </article>
            <article class="info-card">
                <span class="info-card-number" aria-hidden="true">3</span>
                <h3>Solicita una revisión</h3>
                <p>Cuando estés listo, envía el tema al ponente para recibir una calificación y observaciones.</p>
            </article>
            <article class="info-card">
                <span class="info-card-number" aria-hidden="true">4</span>
                <h3>Mejora y continúa</h3>
                <p>Si necesitas corregir algo, revisa los comentarios y solicita una nueva evaluación. Las aprobaciones actualizan tu progreso.</p>
            </article>
        </div>
    </section>

    <aside class="info-cta">
        <p>¿Listo para comenzar tu siguiente curso?</p>
        <a href="#login-form" class="btn-nav-login">Iniciar sesión &rarr;</a>
    </aside>

    <!-- 4. Validación Frontend (JS) y Funcionalidad -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const loginPanel = document.getElementById('loginPanel');
            const registrationPanel = document.getElementById('registrationPanel');
            const lecturerApplicationPanel = document.getElementById('lecturerApplicationPanel');
            const directorApplicationPanel = document.getElementById('directorApplicationPanel');
            const superadminApplicationPanel = document.getElementById('superadminApplicationPanel');
            const showRegistrationButton = document.getElementById('showRegistration');
            const showLoginButton = document.getElementById('showLogin');
            const showLecturerApplicationButton = document.getElementById('showLecturerApplication');
            const showLoginFromApplicationButton = document.getElementById('showLoginFromApplication');
            const showDirectorApplicationButton = document.getElementById('showDirectorApplication');
            const showLoginFromDirectorApplicationButton = document.getElementById('showLoginFromDirectorApplication');
            const showSuperadminApplicationButton = document.getElementById('showSuperadminApplication');
            const showLoginFromSuperadminApplicationButton = document.getElementById('showLoginFromSuperadminApplication');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const eyeIconOpen = document.getElementById('eyeIconOpen');
            const eyeIconClosed = document.getElementById('eyeIconClosed');

            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            const generalError = document.getElementById('general-error');
            const generalErrorText = document.getElementById('general-error-text');

            showRegistrationButton.addEventListener('click', function () {
                loginPanel.hidden = true;
                registrationPanel.hidden = false;
                lecturerApplicationPanel.hidden = true;
                directorApplicationPanel.hidden = true;
                superadminApplicationPanel.hidden = true;
                document.getElementById('nombreRegistro').focus();
            });

            showLoginButton.addEventListener('click', function () {
                registrationPanel.hidden = true;
                lecturerApplicationPanel.hidden = true;
                directorApplicationPanel.hidden = true;
                superadminApplicationPanel.hidden = true;
                loginPanel.hidden = false;
                emailInput.focus();
            });

            showLecturerApplicationButton.addEventListener('click', function () {
                loginPanel.hidden = true;
                registrationPanel.hidden = true;
                lecturerApplicationPanel.hidden = false;
                directorApplicationPanel.hidden = true;
                superadminApplicationPanel.hidden = true;
                document.getElementById('nombrePostulacion').focus();
            });

            showDirectorApplicationButton.addEventListener('click', function () {
                loginPanel.hidden = true;
                registrationPanel.hidden = true;
                lecturerApplicationPanel.hidden = true;
                directorApplicationPanel.hidden = false;
                superadminApplicationPanel.hidden = true;
                document.getElementById('nombrePostulacionDirector').focus();
            });

            showLoginFromApplicationButton.addEventListener('click', function () {
                lecturerApplicationPanel.hidden = true;
                loginPanel.hidden = false;
                emailInput.focus();
            });

            showLoginFromDirectorApplicationButton.addEventListener('click', function () {
                directorApplicationPanel.hidden = true;
                loginPanel.hidden = false;
                emailInput.focus();
            });

            showSuperadminApplicationButton.addEventListener('click', function () {
                loginPanel.hidden = true;
                registrationPanel.hidden = true;
                lecturerApplicationPanel.hidden = true;
                directorApplicationPanel.hidden = true;
                superadminApplicationPanel.hidden = false;
                document.getElementById('nombrePostulacionSuperadmin').focus();
            });

            showLoginFromSuperadminApplicationButton.addEventListener('click', function () {
                superadminApplicationPanel.hidden = true;
                loginPanel.hidden = false;
                emailInput.focus();
            });

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