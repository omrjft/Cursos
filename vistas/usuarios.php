<?php
$conexion = conect();
$busqueda = $_GET['q'] ?? '';
$filtroRol = filter_var($_GET['rol'] ?? 0, FILTER_VALIDATE_INT);
$filtroEstado = $_GET['estado'] ?? '';
$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$filtroRol = $filtroRol === false ? 0 : $filtroRol;
$pagina = $pagina === false || $pagina < 1 ? 1 : $pagina;
$busqueda = is_string($busqueda) ? trim($busqueda) : '';
$filtroEstado = is_string($filtroEstado) && in_array($filtroEstado, ['Activo', 'Inactivo'], true)
    ? $filtroEstado
    : '';
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

$resultadoEstadisticas = $conexion->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(r.nombre = 'alumno'), 0) AS alumnos,
            COALESCE(SUM(r.nombre = 'ponente'), 0) AS docentes
     FROM usuarios u
     LEFT JOIN roles r ON r.id = u.rol_id"
);
$estadisticas = $resultadoEstadisticas->fetch_assoc();

$roles = [];
$resultadoRoles = $conexion->query('SELECT id, nombre FROM roles ORDER BY id');
while ($rol = $resultadoRoles->fetch_assoc()) {
    $roles[] = $rol;
}

$stmtTotalUsuarios = $conexion->prepare(
    "SELECT COUNT(*) AS total FROM usuarios u
     WHERE (? = '' OR u.nombre LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%'))
       AND (? = 0 OR u.rol_id = ?)
       AND (? = '' OR u.estado = ?)"
);
$stmtTotalUsuarios->bind_param(
    'sssiiss',
    $busqueda,
    $busqueda,
    $busqueda,
    $filtroRol,
    $filtroRol,
    $filtroEstado,
    $filtroEstado
);
$stmtTotalUsuarios->execute();
$totalFiltrado = (int)$stmtTotalUsuarios->get_result()->fetch_assoc()['total'];
$totalPaginas = max(1, (int)ceil($totalFiltrado / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stmtUsuarios = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email, u.estado, r.nombre AS rol
     FROM usuarios u
     LEFT JOIN roles r ON r.id = u.rol_id
     WHERE (? = '' OR u.nombre LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%'))
       AND (? = 0 OR u.rol_id = ?)
       AND (? = '' OR u.estado = ?)
     ORDER BY u.id DESC
     LIMIT ? OFFSET ?"
);
$stmtUsuarios->bind_param(
    'sssiissii',
    $busqueda,
    $busqueda,
    $busqueda,
    $filtroRol,
    $filtroRol,
    $filtroEstado,
    $filtroEstado,
    $porPagina,
    $offset
);
$stmtUsuarios->execute();
$resultadoUsuarios = $stmtUsuarios->get_result();
$usuarios = [];
while ($usuario = $resultadoUsuarios->fetch_assoc()) {
    $usuarios[] = $usuario;
}
?>

<section>
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Gestión de Usuarios</h2>
        <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
            Directorio global y administración de cuentas de la plataforma.
        </p>
    </div>

    <details id="nuevo-usuario" class="summary-card" style="display: block; padding: 20px; margin-bottom: 24px;" <?= $mensajeAccion !== '' ? 'open' : '' ?>>
        <summary style="cursor: pointer; font-weight: 800; color: var(--color-charcoal);">Registrar usuario</summary>
        <form method="POST" action="dashboard.php?seccion=usuarios" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="accion" value="crear_usuario">
            <label>Nombre
                <input name="nombre" maxlength="100" required style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px;">
            </label>
            <label>Correo electrónico
                <input type="email" name="email" maxlength="150" required style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px;">
            </label>
            <label>Contraseña (mínimo 10 caracteres)
                <input type="password" name="password" minlength="10" required style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px;">
            </label>
            <label>Rol
                <select name="rol_id" required style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px;">
                    <option value="">Selecciona un rol</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= (int)$rol['id'] ?>"><?= htmlspecialchars(ucfirst($rol['nombre'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div style="align-self: end;">
                <button type="submit" class="welcome-btn">Guardar Usuario</button>
            </div>
        </form>
    </details>

    <!-- Indicadores de volumen de usuarios -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 16px 20px;">
            <span class="card-label" style="font-size: 11px;">Total de Usuarios</span>
            <p style="font-size: 24px; font-weight: 900; color: var(--color-charcoal);"><?= $estadisticas['total'] ?></p>
        </div>
        <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 16px 20px;">
            <span class="card-label" style="font-size: 11px;">Alumnos Registrados</span>
            <p style="font-size: 24px; font-weight: 900; color: var(--color-charcoal);"><?= $estadisticas['alumnos'] ?></p>
        </div>
        <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 16px 20px;">
            <span class="card-label" style="font-size: 11px;">Docentes y Ponentes</span>
            <p style="font-size: 24px; font-weight: 900; color: var(--color-charcoal);"><?= $estadisticas['docentes'] ?></p>
        </div>
    </div>

    <!-- Filtros de búsqueda sobre los registros de MySQL -->
    <article class="summary-card" style="display: block; padding: 20px; margin-bottom: 24px;">
        <form method="GET" action="dashboard.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <input type="hidden" name="seccion" value="usuarios">

            <!-- 1. Barra de búsqueda por nombre/email -->
            <div style="flex: 2; min-width: 240px;">
                <label for="buscar_usuario" style="display: block; font-size: 12px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">
                    Buscar por Nombre / Email
                </label>
                <input 
                    type="text" 
                    id="buscar_usuario" 
                    name="q"
                    value="<?= htmlspecialchars($busqueda) ?>"
                    placeholder="Nombre o correo electrónico"
                    style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 14px; font-family: var(--font-family); font-size: 14px;"
                >
            </div>

            <!-- 2. Selector para filtrar por Rol -->
            <div style="flex: 1; min-width: 160px;">
                <label for="filtro_rol" style="display: block; font-size: 12px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">
                    Rol
                </label>
                <select id="filtro_rol" name="rol" style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px; font-family: var(--font-family); font-size: 14px; font-weight: 700; color: var(--color-charcoal); background-color: var(--color-paper-white);">
                    <option value="0">Todos los Roles</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= (int)$rol['id'] ?>" <?= $filtroRol === (int)$rol['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($rol['nombre'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Selector para filtrar por Estado -->
            <div style="flex: 1; min-width: 160px;">
                <label for="filtro_estado" style="display: block; font-size: 12px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">
                    Estado
                </label>
                <select id="filtro_estado" name="estado" style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px; font-family: var(--font-family); font-size: 14px; font-weight: 700; color: var(--color-charcoal); background-color: var(--color-paper-white);">
                    <option value="">Todos los Estados</option>
                    <option value="Activo" <?= $filtroEstado === 'Activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= $filtroEstado === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>

            <!-- Botón de Filtrar -->
            <div style="min-width: 100px;">
                <button type="submit" class="action-btn" style="height: 42px; width: 100%; border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                    Filtrar
                </button>
            </div>
        </form>
    </article>

    <!-- Tabla de Usuarios -->
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="min-width: 180px;">Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th style="text-align: right; min-width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 24px;">
                            No hay usuarios registrados en la base de datos.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--color-charcoal);">
                            <?= htmlspecialchars($usuario['nombre']) ?>
                        </td>
                        <td style="color: var(--color-pencil-gray);">
                            <?= htmlspecialchars($usuario['email']) ?>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; background-color: #f0f0f0; color: var(--color-charcoal);">
                                <?= htmlspecialchars($usuario['rol']) ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                $esActivo = ($usuario['estado'] === 'Activo');
                                $esInactivo = ($usuario['estado'] === 'Inactivo');
                            ?>
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; <?= $esActivo ? 'background-color: var(--color-storybook-green); color: #2e7a00;' : ($esInactivo ? 'background-color: #ffdfe0; color: #ea2b2b;' : 'background-color: #fff4e5; color: #d97706;') ?>">
                                <?= htmlspecialchars($usuario['estado']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ((int)$usuario['id'] !== $usuario_id): ?>
                                <form method="POST" action="dashboard.php?seccion=usuarios" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="accion" value="cambiar_estado_usuario">
                                    <input type="hidden" name="usuario_id" value="<?= (int)$usuario['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= $usuario['estado'] === 'Activo' ? 'Inactivo' : 'Activo' ?>">
                                    <button type="submit" class="action-btn" style="<?= $usuario['estado'] === 'Activo' ? 'border-color: #ea2b2b; color: #ea2b2b;' : 'border-color: var(--color-eager-green); color: #2e7a00;' ?>">
                                        <?= $usuario['estado'] === 'Activo' ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
        <span>Mostrando <?= count($usuarios) ?> de <?= $totalFiltrado ?> usuarios</span>
        <div style="display: flex; gap: 8px;">
            <?php if ($pagina > 1): ?>
                <a class="action-btn" href="dashboard.php?seccion=usuarios&amp;q=<?= urlencode($busqueda) ?>&amp;rol=<?= $filtroRol ?>&amp;estado=<?= urlencode($filtroEstado) ?>&amp;pagina=<?= $pagina - 1 ?>">Anterior</a>
            <?php endif; ?>
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a class="action-btn" href="dashboard.php?seccion=usuarios&amp;q=<?= urlencode($busqueda) ?>&amp;rol=<?= $filtroRol ?>&amp;estado=<?= urlencode($filtroEstado) ?>&amp;pagina=<?= $pagina + 1 ?>">Siguiente</a>
            <?php endif; ?>
        </div>
    </div>
</section>
