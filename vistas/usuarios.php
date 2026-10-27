<?php
$conexion = conect();

$resultadoEstadisticas = $conexion->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(rol_id = 3), 0) AS alumnos,
            COALESCE(SUM(rol_id IN (2, 4)), 0) AS docentes
     FROM usuarios"
);
$estadisticas = $resultadoEstadisticas->fetch_assoc();

$resultadoUsuarios = $conexion->query(
    "SELECT u.id, u.nombre, u.email, u.estado, r.nombre AS rol
     FROM usuarios u
     LEFT JOIN roles r ON r.id = u.rol_id
     ORDER BY u.id DESC"
);
$usuarios = [];
while ($usuario = $resultadoUsuarios->fetch_assoc()) {
    $usuarios[] = $usuario;
}
?>

<section>
    <!-- Encabezado con Acción Principal: "+ Nuevo Usuario" -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Gestión de Usuarios</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Directorio global y administración de cuentas de la plataforma.
            </p>
        </div>

        <button type="button" class="welcome-btn" onclick="alert('Formulario de registro manual de nuevo usuario.');" style="font-size: 13px; padding: 10px 18px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            + Nuevo Usuario
        </button>
    </div>

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

    <!-- Filtros de Búsqueda (Formulario HTML inactivo para alto volumen) -->
    <article class="summary-card" style="display: block; padding: 20px; margin-bottom: 24px;">
        <form method="GET" action="dashboard.php" onsubmit="return false;" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
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
                    placeholder="Ej. Carlos Mendoza o usuario@correo.com" 
                    style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 14px; font-family: var(--font-family); font-size: 14px;"
                >
            </div>

            <!-- 2. Selector para filtrar por Rol -->
            <div style="flex: 1; min-width: 160px;">
                <label for="filtro_rol" style="display: block; font-size: 12px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">
                    Rol
                </label>
                <select id="filtro_rol" name="rol" style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px; font-family: var(--font-family); font-size: 14px; font-weight: 700; color: var(--color-charcoal); background-color: var(--color-paper-white);">
                    <option value="">Todos los Roles</option>
                    <option value="superadmin">Superadmin</option>
                    <option value="director">Director</option>
                    <option value="ponente">Docente / Ponente</option>
                    <option value="alumno">Alumno</option>
                </select>
            </div>

            <!-- 3. Selector para filtrar por Estado -->
            <div style="flex: 1; min-width: 160px;">
                <label for="filtro_estado" style="display: block; font-size: 12px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">
                    Estado
                </label>
                <select id="filtro_estado" name="estado" style="width: 100%; height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 12px; font-family: var(--font-family); font-size: 14px; font-weight: 700; color: var(--color-charcoal); background-color: var(--color-paper-white);">
                    <option value="">Todos los Estados</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                    <option value="pendiente">Pendiente</option>
                </select>
            </div>

            <!-- Botón de Filtrar -->
            <div style="min-width: 100px;">
                <button type="button" class="action-btn" onclick="alert('Filtrado simulado.');" style="height: 42px; width: 100%; border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
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
                            <button type="button" class="action-btn" onclick="alert('Editar usuario: <?= htmlspecialchars($usuario['nombre']) ?>');" style="margin-right: 6px;">
                                Editar
                            </button>
                            <button type="button" class="action-btn" onclick="alert('Eliminar usuario: <?= htmlspecialchars($usuario['nombre']) ?>');" style="border-color: #ea2b2b; color: #ea2b2b;">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
