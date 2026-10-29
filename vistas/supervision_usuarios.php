<?php
// Vista: Supervisión de Usuarios (Director)
// Columnas: Nombre, Rol, Cursos Asignados, Progreso Medio.
// Filtros (Simulados): Menú desplegable (<select>) para filtrar por "Alumnos" o "Docentes".
// Acciones Restringidas: Botones de "Ver Detalles" o "Asignar Curso". SIN botones de "Eliminar Usuario" ni "Cambiar Rol".

$opcionesFiltro = ['todos', 'alumnos', 'docentes'];
$filtroRol = $_GET['filtro_rol'] ?? 'todos';
if (!is_string($filtroRol) || !in_array($filtroRol, $opcionesFiltro, true)) {
    $filtroRol = 'todos';
}

$condicionRol = match ($filtroRol) {
    'alumnos' => "r.nombre = 'alumno'",
    'docentes' => "r.nombre = 'ponente'",
    default => "r.nombre IN ('alumno', 'ponente')",
};

$conexion = conect();
$resultadoSupervision = $conexion->query(
    "SELECT u.id, u.nombre, u.email,
            CASE r.nombre WHEN 'alumno' THEN 'Alumno' ELSE 'Docente' END AS rol,
            COALESCE(GROUP_CONCAT(DISTINCT c.titulo ORDER BY c.titulo SEPARATOR ', '), 'Sin cursos asignados') AS cursos_asignados,
            AVG(m.progreso) AS progreso_medio
     FROM usuarios u
     JOIN roles r ON r.id = u.rol_id
     LEFT JOIN cursos c
       ON (u.rol_id = 3 AND c.id IN (
            SELECT matricula.curso_id
            FROM matriculas matricula
            JOIN cursos curso ON curso.id = matricula.curso_id
            JOIN usuarios ponente ON ponente.id = curso.docente_id AND ponente.estado = 'Activo'
            WHERE matricula.usuario_id = u.id
       ))
       OR (u.rol_id = 4 AND c.docente_id = u.id AND c.docente_id IN (
            SELECT docente.id FROM usuarios docente WHERE docente.estado = 'Activo'
       ))
     LEFT JOIN (
       SELECT matricula.usuario_id, matricula.curso_id,
              CASE WHEN COUNT(DISTINCT tema.id) = 0 THEN 0
                   ELSE ROUND(COUNT(DISTINCT progreso.tema_id) * 100 / COUNT(DISTINCT tema.id), 2)
              END AS progreso
       FROM matriculas matricula
       JOIN usuarios alumno ON alumno.id = matricula.usuario_id AND alumno.estado = 'Activo'
       JOIN cursos cursoActivo ON cursoActivo.id = matricula.curso_id
       JOIN usuarios ponenteActivo ON ponenteActivo.id = cursoActivo.docente_id AND ponenteActivo.estado = 'Activo'
       LEFT JOIN bloques bloque ON bloque.curso_id = matricula.curso_id
       LEFT JOIN temas tema ON tema.bloque_id = bloque.id
       LEFT JOIN progreso_temas progreso
         ON progreso.tema_id = tema.id AND progreso.usuario_id = matricula.usuario_id
       GROUP BY matricula.usuario_id, matricula.curso_id
     ) m
       ON m.curso_id = c.id AND (u.rol_id = 4 OR m.usuario_id = u.id)
     WHERE u.estado = 'Activo' AND {$condicionRol}
     GROUP BY u.id, u.nombre, u.email, u.rol_id
     ORDER BY u.nombre ASC"
);
$usuariosFiltrados = [];
while ($usuario = $resultadoSupervision->fetch_assoc()) {
    $usuariosFiltrados[] = $usuario;
}
?>

<section>
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Supervisión de Usuarios</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Directorio institucional para el seguimiento académico de alumnos y docentes.
            </p>
        </div>

        <!-- Filtros (Simulados con <select>) -->
        <form method="GET" action="dashboard.php" style="display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="seccion" value="supervision_usuarios">
            <label for="filtro_rol" style="font-size: 13px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase;">Filtrar por:</label>
            <select name="filtro_rol" id="filtro_rol" onchange="this.form.submit()" style="height: 42px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 16px; font-family: var(--font-family); font-size: 14px; font-weight: 700; color: var(--color-charcoal); background-color: var(--color-paper-white); cursor: pointer;">
                <option value="todos" <?= ($filtroRol === 'todos') ? 'selected' : '' ?>>Todos los Usuarios</option>
                <option value="alumnos" <?= ($filtroRol === 'alumnos') ? 'selected' : '' ?>>Alumnos</option>
                <option value="docentes" <?= ($filtroRol === 'docentes') ? 'selected' : '' ?>>Docentes</option>
            </select>
        </form>
    </div>

    <!-- Tabla de Usuarios -->
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="min-width: 180px;">Nombre</th>
                    <th>Rol</th>
                    <th style="min-width: 250px;">Cursos Asignados</th>
                    <th style="text-align: center;">Progreso Medio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuariosFiltrados)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--color-pencil-gray); padding: 24px;">
                            No hay usuarios para mostrar con este filtro.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($usuariosFiltrados as $u): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--color-charcoal); font-size: 15px;">
                                <?= htmlspecialchars($u['nombre']) ?>
                            </strong>
                            <div style="color: var(--color-pencil-gray); font-size: 13px;">
                                <?= htmlspecialchars($u['email']) ?>
                            </div>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; <?= ($u['rol'] === 'Docente') ? 'background-color: #162e46; color: var(--color-spark-blue);' : 'background-color: var(--color-storybook-green); color: var(--color-spark-blue);' ?>">
                                <?= htmlspecialchars($u['rol']) ?>
                            </span>
                        </td>
                        <td style="color: var(--color-charcoal); font-size: 14px;">
                            <?= htmlspecialchars($u['cursos_asignados']) ?>
                        </td>
                        <td style="text-align: center; font-weight: 800; color: var(--color-charcoal); font-size: 14px;">
                            <?php if ($u['progreso_medio'] === null): ?>
                                Sin datos
                            <?php else: ?>
                                <?= number_format((float)$u['progreso_medio'], 1) ?>%<?= ($u['rol'] === 'Docente') ? ' (promedio alumnos)' : '' ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
