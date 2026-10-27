<?php
// Vista: Detalle de Curso - Modo Alumno y Modo General
$conexion = conect();
$usuario_id = (int)($_SESSION["id"] ?? 0);
$rol_id = (int)($_SESSION["rol_id"] ?? 0);
$esAlumno = ($rol_id === 3);

$cursoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Obtener información del curso
$stmtCurso = $conexion->prepare("SELECT c.id, c.titulo, c.descripcion, COALESCE(u.nombre, 'Sin asignar') AS docente FROM cursos c LEFT JOIN usuarios u ON c.docente_id = u.id WHERE c.id = ?");
$stmtCurso->bind_param("i", $cursoId);
$stmtCurso->execute();
$curso = $stmtCurso->get_result()->fetch_assoc();

if (!$curso) {
    echo "<section><h2>Curso no disponible</h2><p>No se encontró el curso solicitado en la base de datos.</p></section>";
    return;
}

// 2. Obtener progreso de matrícula si es alumno
$progresoAlumno = 0.0;
$puedeMarcarProgreso = false;
if ($esAlumno) {
    $stmtMat = $conexion->prepare("SELECT 1 FROM matriculas WHERE usuario_id = ? AND curso_id = ?");
    $stmtMat->bind_param("ii", $usuario_id, $cursoId);
    $stmtMat->execute();
    $mat = $stmtMat->get_result()->fetch_assoc();
    if ($mat) {
        $puedeMarcarProgreso = true;

        $stmtAvance = $conexion->prepare(
            "SELECT COUNT(DISTINCT t.id) AS total_temas,
                    COUNT(DISTINCT pt.tema_id) AS temas_completados
             FROM bloques b
             JOIN temas t ON t.bloque_id = b.id
             LEFT JOIN progreso_temas pt
               ON pt.tema_id = t.id AND pt.usuario_id = ?
             WHERE b.curso_id = ?"
        );
        $stmtAvance->bind_param("ii", $usuario_id, $cursoId);
        $stmtAvance->execute();
        $avance = $stmtAvance->get_result()->fetch_assoc();
        $totalTemas = (int)($avance['total_temas'] ?? 0);
        $temasCompletados = (int)($avance['temas_completados'] ?? 0);
        $progresoAlumno = $totalTemas > 0
            ? round($temasCompletados * 100 / $totalTemas, 2)
            : 0.0;
    }
}

// 3. Obtener Bloques y Temas interactivos
$bloques = [];
$stmtB = $conexion->prepare("SELECT id, titulo FROM bloques WHERE curso_id = ? ORDER BY orden ASC");
$stmtB->bind_param("i", $cursoId);
$stmtB->execute();
$resB = $stmtB->get_result();

while ($b = $resB->fetch_assoc()) {
    $stmtT = $conexion->prepare(
        "SELECT t.id, t.titulo, (pt.tema_id IS NOT NULL) AS completado
         FROM temas t
         LEFT JOIN progreso_temas pt
           ON pt.tema_id = t.id AND pt.usuario_id = ?
         WHERE t.bloque_id = ?
         ORDER BY t.orden ASC"
    );
    $stmtT->bind_param("ii", $usuario_id, $b['id']);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    $temas = [];
    while ($t = $resT->fetch_assoc()) {
        $temas[] = $t;
    }
    $b['temas'] = $temas;
    $bloques[] = $b;
}

// 4. Materiales de Apoyo
$materiales = [];
$stmtM = $conexion->prepare("SELECT m.id, m.nombre, m.url_archivo, m.tipo 
    FROM materiales m 
    JOIN temas t ON m.tema_id = t.id 
    JOIN bloques b ON t.bloque_id = b.id 
    WHERE b.curso_id = ?");
$stmtM->bind_param("i", $cursoId);
$stmtM->execute();
$resM = $stmtM->get_result();
while ($m = $resM->fetch_assoc()) {
    $materiales[] = $m;
}

// 5. Panel de Tareas y Asignaciones
$tareas = [];
$stmtTar = $conexion->prepare("SELECT t.id, t.titulo, t.descripcion, t.fecha_limite, e.calificacion, e.fecha_entrega, e.archivo_url
    FROM tareas t
    LEFT JOIN entregas_tareas e ON t.id = e.tarea_id AND e.usuario_id = ?
    WHERE t.curso_id = ?
    ORDER BY t.fecha_limite ASC");
$stmtTar->bind_param("ii", $usuario_id, $cursoId);
$stmtTar->execute();
$resTar = $stmtTar->get_result();

while ($tar = $resTar->fetch_assoc()) {
    $estado = 'Pendiente';
    if ($tar['fecha_entrega'] !== null) {
        $estado = ($tar['calificacion'] !== null) ? 'Calificado' : 'Entregado';
    }
    $tar['estado'] = $estado;
    $tareas[] = $tar;
}

?>

<article>
    <!-- Encabezado del Detalle de Curso -->
    <div style="margin-bottom: 24px;">
        <p style="margin-bottom: 8px;">
            <a href="dashboard.php?seccion=cursos" style="color: var(--color-spark-blue); text-decoration: none; font-weight: 700; font-size: 14px;">
                &larr; Volver a Mis Cursos
            </a>
        </p>

        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="font-size: 28px; font-weight: 900; color: var(--color-charcoal); line-height: 1.2;">
                    <?= htmlspecialchars($curso['titulo']) ?>
                </h2>
                <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 6px;">
                    Impartido por: <strong style="color: var(--color-charcoal);"><?= htmlspecialchars($curso['docente'] ?? 'Docente Titular') ?></strong>
                </p>
            </div>

            <!-- Progreso del Alumno -->
            <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 14px 20px; min-width: 200px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 800; text-transform: uppercase; margin-bottom: 6px;">
                    <span style="color: var(--color-pencil-gray);">Tu Avance</span>
                    <span style="color: var(--color-eager-green); font-size: 15px;"><?= round($progresoAlumno) ?>%</span>
                </div>
                <progress value="<?= round($progresoAlumno) ?>" max="100" style="width: 100%; height: 12px; border-radius: 999px; accent-color: var(--color-eager-green);"></progress>
            </div>
        </div>
    </div>

    <!-- Contenido en 2 Columnas: Temario Interactivo a la izquierda, Materiales y Tareas a la derecha -->
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 28px; align-items: start;">

        <!-- 1. TEMARIO INTERACTIVO (Modo Alumno con Checkboxes) -->
        <section class="summary-card" style="display: block; padding: 28px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 2px solid var(--color-border-gray); padding-bottom: 12px;">
                <h3 style="font-size: 20px; font-weight: 900; color: var(--color-charcoal);">Temario Interactivo</h3>
                <span style="font-size: 12px; font-weight: 700; color: var(--color-pencil-gray);">Marca los temas completados</span>
            </div>

            <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 20px;">
                <?= $puedeMarcarProgreso ? 'Marca los temas completados para actualizar tu avance.' : 'Consulta los temas disponibles en este curso.' ?>
            </p>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php if (empty($bloques)): ?>
                    <p style="color: var(--color-pencil-gray);">Este curso aún no tiene bloques ni temas registrados.</p>
                <?php endif; ?>
                <?php foreach ($bloques as $idxBloque => $bloque): ?>
                    <div style="border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 16px; background-color: var(--color-bg-light);">
                        <h4 style="font-size: 16px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span style="width: 24px; height: 24px; background-color: var(--color-storybook-green); color: #2e7a00; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900;">
                                <?= $idxBloque + 1 ?>
                            </span>
                            <?= htmlspecialchars($bloque['titulo']) ?>
                        </h4>

                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; padding-left: 6px;">
                            <?php foreach ($bloque['temas'] as $tema): ?>
                                <?php $temaCompletado = (bool)$tema['completado']; ?>
                                <li>
                                    <?php if ($puedeMarcarProgreso): ?>
                                        <form method="POST" action="dashboard.php?seccion=curso_detalle&amp;id=<?= $cursoId ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="actualizar_progreso_tema">
                                            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                            <input type="hidden" name="tema_id" value="<?= (int)$tema['id'] ?>">
                                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--color-charcoal); user-select: none;">
                                                <input type="checkbox" name="completado" value="1" <?= $temaCompletado ? 'checked' : '' ?> onchange="this.form.submit()" style="width: 18px; height: 18px; accent-color: var(--color-eager-green); cursor: pointer;">
                                                <span style="<?= $temaCompletado ? 'text-decoration: line-through; color: var(--color-pencil-gray);' : '' ?>">
                                                    <?= htmlspecialchars($tema['titulo']) ?>
                                                </span>
                                            </label>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 14px; color: var(--color-charcoal);"><?= htmlspecialchars($tema['titulo']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Columna Derecha: Materiales de Apoyo y Panel de Tareas -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- 2. MATERIALES DE APOYO -->
            <section class="summary-card" style="display: block; padding: 24px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="var(--color-spark-blue)"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    Materiales de Apoyo
                </h3>
                <p style="font-size: 13px; color: var(--color-pencil-gray); margin-bottom: 16px;">
                    Recursos y guías descargables proporcionadas por el docente para complementar tu aprendizaje.
                </p>

                <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px;">
                    <?php if (empty($materiales)): ?>
                        <li style="color: var(--color-pencil-gray);">Este curso aún no tiene materiales registrados.</li>
                    <?php endif; ?>
                    <?php foreach ($materiales as $mat): ?>
                        <li style="display: flex; align-items: center; justify-content: space-between; background-color: var(--color-bg-light); border: 1px solid var(--color-border-gray); padding: 10px 14px; border-radius: var(--radius-btn);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#e74c3c"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                <span style="font-size: 14px; font-weight: 700; color: var(--color-charcoal);">
                                    <?= htmlspecialchars($mat['nombre']) ?>
                                </span>
                            </div>
                            <?php if (!empty($mat['url_archivo'])): ?>
                                <a href="<?= htmlspecialchars($mat['url_archivo']) ?>" download="<?= htmlspecialchars($mat['nombre']) ?>" class="action-btn" style="padding: 4px 10px; font-size: 12px; text-decoration: none; border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                                    Descargar
                                </a>
                            <?php else: ?>
                                <span style="font-size: 12px; color: var(--color-pencil-gray);">Archivo no disponible</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- 3. PANEL DE TAREAS (Pendiente, Entregado, Calificado) -->
            <section class="summary-card" style="display: block; padding: 24px;">
                <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal); margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="var(--color-eager-green)"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    Panel de Tareas y Evaluaciones
                </h3>
                <p style="font-size: 13px; color: var(--color-pencil-gray); margin-bottom: 16px;">
                    Consulta tus asignaciones y notas asignadas por el ponente titular.
                </p>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($tareas)): ?>
                        <p style="color: var(--color-pencil-gray);">Este curso aún no tiene tareas registradas.</p>
                    <?php endif; ?>
                    <?php foreach ($tareas as $tar): ?>
                        <div style="border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 14px; background-color: var(--color-paper-white);">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                                <h4 style="font-size: 15px; font-weight: 800; color: var(--color-charcoal); line-height: 1.3;">
                                    <?= htmlspecialchars($tar['titulo']) ?>
                                </h4>
                                <!-- Estado de Asignación -->
                                <?php if ($tar['estado'] === 'Calificado'): ?>
                                    <span style="background-color: var(--color-storybook-green); color: #2e7a00; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; white-space: nowrap;">
                                        Calificado
                                    </span>
                                <?php elseif ($tar['estado'] === 'Entregado'): ?>
                                    <span style="background-color: #e0f4ff; color: var(--color-spark-blue); font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; white-space: nowrap;">
                                        Entregado
                                    </span>
                                <?php else: ?>
                                    <span style="background-color: #fff4e5; color: #d97706; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; white-space: nowrap;">
                                        Pendiente
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="font-size: 12px; color: var(--color-pencil-gray); margin-bottom: 10px;">
                                Fecha límite: <?= htmlspecialchars($tar['fecha_limite']) ?>
                            </p>

                            <!-- Calificación final si ya fue revisada -->
                            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--color-border-gray); padding-top: 8px;">
                                <span style="font-size: 13px; font-weight: 700; color: var(--color-pencil-gray);">Calificación final:</span>
                                <?php if ($tar['estado'] === 'Calificado' && $tar['calificacion'] !== null): ?>
                                    <strong style="font-size: 15px; color: var(--color-eager-green);">
                                        <?= number_format((float)$tar['calificacion'], 1) ?> / 10
                                    </strong>
                                <?php elseif ($tar['estado'] === 'Entregado'): ?>
                                    <span style="font-size: 12px; color: var(--color-pencil-gray); font-style: italic;">
                                        En revisión por el ponente
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #d97706; font-weight: 700;">
                                        Sin entregar
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</article>
