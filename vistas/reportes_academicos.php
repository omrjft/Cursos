<?php
// Vista: Reportes Académicos basados en revisiones de temas y progreso aprobado.
$conexion = conect();

$sqlRendimiento = "SELECT u.id AS alumno_id, u.nombre, c.id AS curso_id, c.titulo AS curso,
            AVG(r.calificacion) AS promedio,
            (SELECT CASE WHEN COUNT(DISTINCT tema.id) = 0 THEN 0
                         ELSE ROUND(COUNT(DISTINCT progreso.tema_id) * 100 / COUNT(DISTINCT tema.id), 1)
                    END
             FROM matriculas matricula
             LEFT JOIN bloques bloque ON bloque.curso_id = matricula.curso_id
             LEFT JOIN temas tema ON tema.bloque_id = bloque.id
             LEFT JOIN progreso_temas progreso
               ON progreso.tema_id = tema.id AND progreso.usuario_id = matricula.usuario_id
             WHERE matricula.usuario_id = u.id AND matricula.curso_id = c.id) AS progreso
     FROM revisiones_temas r
     JOIN usuarios u ON u.id = r.usuario_id AND u.estado = 'Activo'
     JOIN temas t ON t.id = r.tema_id
     JOIN bloques b ON b.id = t.bloque_id
     JOIN cursos c ON c.id = b.curso_id
     WHERE r.calificacion IS NOT NULL
       AND r.estado IN ('aprobada', 'requiere_correccion')";
$alumnosMejor = [];
$consultaMejor = $conexion->query(
    $sqlRendimiento . ' GROUP BY u.id, u.nombre, c.id, c.titulo
     ORDER BY promedio DESC, u.nombre ASC, c.titulo ASC
     LIMIT 3'
);
while ($fila = $consultaMejor->fetch_assoc()) {
    $alumnosMejor[] = $fila;
}

$alumnosPeor = [];
$consultaPeor = $conexion->query(
    $sqlRendimiento . ' GROUP BY u.id, u.nombre, c.id, c.titulo
     ORDER BY promedio ASC, u.nombre ASC, c.titulo ASC
     LIMIT 3'
);
while ($fila = $consultaPeor->fetch_assoc()) {
    $alumnosPeor[] = $fila;
}
foreach ($alumnosMejor as $indice => &$fila) {
    $fila['posicion'] = $indice + 1;
    $fila['promedio'] = number_format((float)$fila['promedio'], 1) . ' / 10';
    $fila['progreso'] = number_format((float)($fila['progreso'] ?? 0), 1) . '%';
}
unset($fila);
foreach ($alumnosPeor as $indice => &$fila) {
    $promedio = (float)$fila['promedio'];
    $fila['posicion'] = $indice + 1;
    $fila['promedio'] = number_format($promedio, 1) . ' / 10';
    $fila['progreso'] = number_format((float)($fila['progreso'] ?? 0), 1) . '%';
    $fila['estado'] = $promedio < 7 ? 'Requiere apoyo' : 'En seguimiento';
}
unset($fila);

$consultaCursos = $conexion->query(
    "SELECT c.id, c.titulo AS curso, c.estado, COALESCE(u.nombre, 'Sin asignar') AS docente,
            (SELECT COUNT(*) FROM matriculas m
             JOIN usuarios alumno ON alumno.id = m.usuario_id AND alumno.estado = 'Activo'
             WHERE m.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*)
             FROM revisiones_temas r
             JOIN temas t ON t.id = r.tema_id
             JOIN bloques b ON b.id = t.bloque_id
             JOIN usuarios alumno ON alumno.id = r.usuario_id AND alumno.estado = 'Activo'
             WHERE b.curso_id = c.id
               AND r.calificacion IS NOT NULL
               AND r.estado IN ('aprobada', 'requiere_correccion')) AS revisiones_evaluadas,
            (SELECT COUNT(*)
             FROM progreso_temas progreso
             JOIN temas t ON t.id = progreso.tema_id
             JOIN bloques b ON b.id = t.bloque_id
             JOIN usuarios alumno ON alumno.id = progreso.usuario_id AND alumno.estado = 'Activo'
             WHERE b.curso_id = c.id) AS temas_aprobados,
            (SELECT COUNT(*)
             FROM revisiones_temas r
             JOIN temas t ON t.id = r.tema_id
             JOIN bloques b ON b.id = t.bloque_id
             JOIN usuarios alumno ON alumno.id = r.usuario_id AND alumno.estado = 'Activo'
             WHERE b.curso_id = c.id
               AND r.estado = 'aprobada'
               AND r.calificacion IS NOT NULL) AS revisiones_aprobadas,
            (SELECT COUNT(*)
             FROM revisiones_temas r
             JOIN temas t ON t.id = r.tema_id
             JOIN bloques b ON b.id = t.bloque_id
             JOIN usuarios alumno ON alumno.id = r.usuario_id AND alumno.estado = 'Activo'
             WHERE b.curso_id = c.id
               AND r.estado = 'requiere_correccion'
               AND r.calificacion IS NOT NULL) AS requieren_correccion,
            (SELECT AVG(r.calificacion)
             FROM revisiones_temas r
             JOIN temas t ON t.id = r.tema_id
             JOIN bloques b ON b.id = t.bloque_id
             JOIN usuarios alumno ON alumno.id = r.usuario_id AND alumno.estado = 'Activo'
             WHERE b.curso_id = c.id
               AND r.calificacion IS NOT NULL
               AND r.estado IN ('aprobada', 'requiere_correccion')) AS promedio_general
     FROM cursos c
     LEFT JOIN usuarios u ON c.docente_id = u.id AND u.estado = 'Activo'
     ORDER BY c.titulo ASC"
);
$cursosReporte = [];
while ($fila = $consultaCursos->fetch_assoc()) {
    $totalRevisiones = (int)$fila['revisiones_evaluadas'];
    $fila['tasa_aprobacion'] = $totalRevisiones > 0
        ? number_format((int)$fila['revisiones_aprobadas'] * 100 / $totalRevisiones, 1) . '%'
        : '—';
    $fila['promedio_general'] = $fila['promedio_general'] === null
        ? '—'
        : number_format((float)$fila['promedio_general'], 1);
    $cursosReporte[] = $fila;
}
?>

<section>
    <!-- Encabezado con Botón de Exportación -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Reportes Académicos</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Rendimiento de revisiones de temas y avance aprobado persistido por curso.
            </p>
        </div>

        <!-- Exportación real de los datos consultados -->
        <div style="display: flex; gap: 10px;">
            <a href="dashboard.php?seccion=reportes&amp;export=csv" class="action-btn" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Exportar a CSV
            </a>
        </div>
    </div>

    <!-- Tablas de Rendimiento: Mejor y Peor desempeño -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <!-- Alumnos con Mejor Desempeño -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background-color: var(--color-eager-green); border-radius: 50%;"></span>
                    Promedios más altos por curso
                </h3>
                <span style="font-size: 11px; font-weight: 800; background-color: var(--color-storybook-green); color: var(--color-spark-blue); padding: 3px 8px; border-radius: 999px;">TOP 3</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th style="text-align: right;">Nota promedio</th>
                        <th style="text-align: right;">Progreso aprobado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alumnosMejor)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">Aún no hay revisiones de temas calificadas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($alumnosMejor as $a): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--color-eager-green);"><?= $a['posicion'] ?></td>
                            <td style="font-weight: 700; color: var(--color-charcoal);"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td style="font-size: 13px; color: var(--color-pencil-gray);"><?= htmlspecialchars($a['curso']) ?></td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-eager-green);"><?= $a['promedio'] ?></td>
                            <td style="text-align: right;"><?= $a['progreso'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <!-- Alumnos con Mayor Riesgo / Menor Desempeño -->
        <article class="summary-card" style="display: block; padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background-color: #ff7887; border-radius: 50%;"></span>
                    Promedios más bajos por curso
                </h3>
                <span style="font-size: 11px; font-weight: 800; background-color: #3a2028; color: #ff7887; padding: 3px 8px; border-radius: 999px;">ATENCIÓN</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th style="text-align: right;">Nota promedio</th>
                        <th style="text-align: right;">Progreso aprobado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alumnosPeor)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">Aún no hay revisiones de temas calificadas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($alumnosPeor as $a): ?>
                        <tr>
                            <td style="font-weight: 800; color: #ff7887;"><?= $a['posicion'] ?></td>
                            <td style="font-weight: 700; color: var(--color-charcoal);"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td style="font-size: 13px; color: var(--color-pencil-gray);"><?= htmlspecialchars($a['curso']) ?></td>
                            <td style="text-align: right; font-weight: 800;"><?= $a['promedio'] ?> (<?= $a['estado'] ?>)</td>
                            <td style="text-align: right;">
                                <span style="font-size: 11px; font-weight: 800; background-color: #3c2d18; color: #f0b35e; padding: 2px 8px; border-radius: 999px;">
                                    <?= $a['progreso'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </article>
    </div>

    <!-- Resumen de revisiones y aprobaciones por curso -->
    <article class="summary-card" style="display: block; padding: 24px;">
        <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); margin-bottom: 8px;">
            Resumen de revisiones por curso
        </h3>
        <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 16px;">
            Matrículas, intentos calificados, aprobaciones de temas, correcciones y notas registradas en la plataforma.
        </p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Docente Asignado</th>
                        <th>Estado</th>
                        <th style="text-align: center;">Alumnos activos</th>
                        <th style="text-align: center;">Revisiones evaluadas</th>
                        <th style="text-align: center;">Temas aprobados</th>
                        <th style="text-align: center;">Intentos con corrección</th>
                        <th style="text-align: center;">Aprobación de intentos</th>
                        <th style="text-align: right;">Nota promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosReporte)): ?>
                        <tr><td colspan="9" style="text-align: center; color: var(--color-pencil-gray); padding: 20px;">No hay cursos registrados en la base de datos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cursosReporte as $cr): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--color-charcoal);"><?= htmlspecialchars($cr['curso']) ?></td>
                            <td style="color: var(--color-pencil-gray);"><?= htmlspecialchars($cr['docente']) ?></td>
                            <td><?= htmlspecialchars($cr['estado']) ?></td>
                            <td style="text-align: center; font-weight: 700;"><?= $cr['alumnos'] ?></td>
                            <td style="text-align: center;"><?= (int)$cr['revisiones_evaluadas'] ?></td>
                            <td style="text-align: center;"><?= (int)$cr['temas_aprobados'] ?></td>
                            <td style="text-align: center;"><?= (int)$cr['requieren_correccion'] ?></td>
                            <td style="text-align: center;"><?= $cr['tasa_aprobacion'] ?></td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-charcoal);">
                                <?= $cr['promedio_general'] === '—' ? '—' : $cr['promedio_general'] . ' / 10' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
