<?php
//mostrar nombre y tipo de usuario en el dashboard
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit;
}

$nombre = htmlspecialchars($_SESSION["nombre"] ?? "Usuario");
$rol_id = $_SESSION["rol_id"] ?? null;

// Determinar tipo de usuario según rol_id (coincidente con la tabla roles)
switch ($rol_id) {
    case 1:
        $tipo_usuario = "superadmin";
        break;
    case 2:
        $tipo_usuario = "director";
        break;
    case 3:
        $tipo_usuario = "alumno";
        break;
    case 4:
        $tipo_usuario = "ponente";
        break;
    default:
        $tipo_usuario = "usuario";
        break;
}

// Navegación por pestañas mediante parámetro GET (?seccion=...)
$seccion = $_GET['seccion'] ?? 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Plataforma de Cursos</title>
</head>
<body>
    <header>
        <h1>Plataforma de Cursos</h1>
        <p>hola <?= $nombre ?>, eres <?= $tipo_usuario ?></p>
        <nav>
            <ul>
                <li><a href="dashboard.php?seccion=inicio">Dashboard</a></li>
                <li><a href="dashboard.php?seccion=usuarios">Gestión de Usuarios</a></li>
                <li><a href="dashboard.php?seccion=cursos">Mis Cursos</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <?php
        switch ($seccion) {
            case 'usuarios':
                require __DIR__ . '/vistas/usuarios.php';
                break;

            case 'cursos':
                require __DIR__ . '/vistas/cursos.php';
                break;

            case 'curso_detalle':
                require __DIR__ . '/vistas/curso_detalle.php';
                break;

            case 'inicio':
            default:
                ?>
                <section>
                    <h2>Bienvenido al Dashboard</h2>
                    <p>Selecciona una opción del menú de navegación superior para comenzar a explorar la plataforma.</p>
                </section>
                <?php
                break;
        }
        ?>
    </main>
</body>
</html>

</html>