<?php
// Vista: Reportes Académicos con datos calculados desde MySQL.
$conexion = conect();

$consultaMejor = $conexion->query(
    "SELECT u.nombre, c.titulo AS curso, AVG(e.calificacion) AS promedio,
            MAX(m.progreso_global) AS progreso
     FROM entregas_tareas e
     JOIN tareas t ON e.tarea_id = t.id
     JOIN cursos c ON t.curso_id = c.id
     JOIN usuarios u ON e.usuario_id = u.id
     LEFT JOIN matriculas m ON m.usuario_id = u.id AND m.curso_id = c.id
     WHERE e.calificacion IS NOT NULL
     GROUP BY u.id, u.nombre, c.id, c.titulo
     ORDER BY promedio DESC
     LIMIT 3"
);
$alumnosMejor = [];
while ($fila = $consultaMejor->fetch_assoc()) {
    $fila['posicion'] = count($alumnosMejor) + 1;
    $fila['promedio'] = number_format((float)$fila['promedio'], 1) . ' / 10';
    $fila['progreso'] = number_format((float)($fila['progreso'] ?? 0), 1) . '%';
    $alumnosMejor[] = $fila;
}

$consultaPeor = $conexion->query(
    "SELECT u.nombre, c.titulo AS curso, AVG(e.calificacion) AS promedio,
            MAX(m.progreso_global) AS progreso
     FROM entregas_tareas e
     JOIN tareas t ON e.tarea_id = t.id
     JOIN cursos c ON t.curso_id = c.id
     JOIN usuarios u ON e.usuario_id = u.id
     LEFT JOIN matriculas m ON m.usuario_id = u.id AND m.curso_id = c.id
     WHERE e.calificacion IS NOT NULL
     GROUP BY u.id, u.nombre, c.id, c.titulo
     ORDER BY promedio ASC
     LIMIT 3"
);
$alumnosPeor = [];
while ($fila = $consultaPeor->fetch_assoc()) {
    $promedio = (float)$fila['promedio'];
    $fila['posicion'] = count($alumnosPeor) + 1;
    $fila['promedio'] = number_format($promedio, 1) . ' / 10';
    $fila['progreso'] = number_format((float)($fila['progreso'] ?? 0), 1) . '%';
    $fila['estado'] = $promedio < 7 ? 'En riesgo' : 'Regular';
    $alumnosPeor[] = $fila;
}

$consultaCursos = $conexion->query(
    "SELECT c.id, c.titulo AS curso, COALESCE(u.nombre, 'Sin asignar') AS docente,
            (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*)
             FROM entregas_tareas e
             JOIN tareas t ON e.tarea_id = t.id
             WHERE t.curso_id = c.id AND e.calificacion IS NOT NULL) AS calificaciones,
            (SELECT COUNT(*)
             FROM entregas_tareas e
             JOIN tareas t ON e.tarea_id = t.id
             WHERE t.curso_id = c.id AND e.calificacion < 7) AS reprobados,
            (SELECT AVG(e.calificacion)
             FROM entregas_tareas e
             JOIN tareas t ON e.tarea_id = t.id
             WHERE t.curso_id = c.id AND e.calificacion IS NOT NULL) AS promedio_general
     FROM cursos c
     LEFT JOIN usuarios u ON c.docente_id = u.id
     ORDER BY c.titulo ASC"
);
$cursosReprobacion = [];
while ($fila = $consultaCursos->fetch_assoc()) {
    $totalCalificaciones = (int)$fila['calificaciones'];
    $fila['tasa_reprobacion'] = $totalCalificaciones > 0
        ? number_format((int)$fila['reprobados'] * 100 / $totalCalificaciones, 1) . '%'
        : '—';
    $fila['promedio_general'] = $fila['promedio_general'] === null
        ? '—'
        : number_format((float)$fila['promedio_general'], 1);
    $cursosReprobacion[] = $fila;
}
?>

<section>
    <!-- Encabezado con Botón de Exportación -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Reportes Académicos</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Estadísticas de finalización, calificaciones institucionales e índices de rendimiento.
            </p>
        </div>

        <!-- Botón de Exportación Simulado -->
        <div style="display: flex; gap: 10px;">
            <button type="button" class="action-btn" onclick="alert('Exportando reporte completo a formato CSV...');" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Exportar a CSV
            </button>
            <button type="button" class="welcome-btn" onclick="alert('Generando documento PDF oficial de reportes académicos...');" style="font-size: 13px; padding: 10px 16px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-4 11H9v-5h6v5zm4-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Tablas de Rendimiento: Mejor y Peor desempeño -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <!-- Alumnos con Mejor Desempeño -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background-color: var(--color-eager-green); border-radius: 50%;"></span>
                    Alumnos con Mejor Desempeño
                </h3>
                <span style="font-size: 11px; font-weight: 800; background-color: var(--color-storybook-green); color: #2e7a00; padding: 3px 8px; border-radius: 999px;">TOP 3</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th style="text-align: right;">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alumnosMejor)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">Aún no hay calificaciones registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($alumnosMejor as $a): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--color-eager-green);"><?= $a['posicion'] ?></td>
                            <td style="font-weight: 700; color: var(--color-charcoal);"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td style="font-size: 13px; color: var(--color-pencil-gray);"><?= htmlspecialchars($a['curso']) ?></td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-eager-green);"><?= $a['promedio'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <!-- Alumnos con Mayor Riesgo / Menor Desempeño -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background-color: #ea2b2b; border-radius: 50%;"></span>
                    Alumnos con Menor Desempeño
                </h3>
                <span style="font-size: 11px; font-weight: 800; background-color: #ffebee; color: #ea2b2b; padding: 3px 8px; border-radius: 999px;">ATENCIÓN</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th style="text-align: right;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alumnosPeor)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">Aún no hay calificaciones registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($alumnosPeor as $a): ?>
                        <tr>
                            <td style="font-weight: 800; color: #ea2b2b;"><?= $a['posicion'] ?></td>
                            <td style="font-weight: 700; color: var(--color-charcoal);"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td style="font-size: 13px; color: var(--color-pencil-gray);"><?= htmlspecialchars($a['curso']) ?></td>
                            <td style="text-align: right;">
                                <span style="font-size: 11px; font-weight: 800; background-color: #fff4e5; color: #d97706; padding: 2px 8px; border-radius: 999px;">
                                    <?= $a['promedio'] ?> (<?= $a['estado'] ?>)
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>
    </div>

    <!-- Cursos con Mayor Índice de Reprobación -->
    <article class="summary-card" style="display: block; padding: 24px;">
        <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); margin-bottom: 8px;">
            Cursos con Mayor Índice de Reprobación y Deserción
        </h3>
        <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 16px;">
            Métricas de aprobación para la toma de decisiones institucionales y soporte a docentes.
        </p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Docente Asignado</th>
                        <th style="text-align: center;">Total Alumnos</th>
                        <th style="text-align: center;">Tasa de Reprobación</th>
                        <th style="text-align: right;">Promedio General</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosReprobacion)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">No hay cursos registrados en la base de datos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cursosReprobacion as $cr): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--color-charcoal);"><?= htmlspecialchars($cr['curso']) ?></td>
                            <td style="color: var(--color-pencil-gray);"><?= htmlspecialchars($cr['docente']) ?></td>
                            <td style="text-align: center; font-weight: 700;"><?= $cr['alumnos'] ?></td>
                            <td style="text-align: center;">
                                <span style="font-weight: 800; color: <?= ((float)$cr['tasa_reprobacion'] > 15) ? '#ea2b2b' : '#d97706' ?>;">
                                    <?= $cr['tasa_reprobacion'] ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-charcoal);">
                                <?= $cr['promedio_general'] ?> / 10
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
