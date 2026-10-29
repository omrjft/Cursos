<?php
// Vista: Detalle de Curso - Modo Alumno y Modo General
$conexion = conect();
$usuario_id = (int)($_SESSION["id"] ?? 0);
$rol_id = (int)($_SESSION["rol_id"] ?? 0);
$esAlumno = ($rol_id === 3);

$cursoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Obtener información del curso
$stmtCurso = $conexion->prepare("SELECT c.id, c.titulo, c.descripcion, c.estado, COALESCE(u.nombre, 'Sin asignar') AS docente, u.estado AS docente_estado FROM cursos c LEFT JOIN usuarios u ON c.docente_id = u.id AND u.estado = 'Activo' WHERE c.id = ?");
$stmtCurso->bind_param("i", $cursoId);
$stmtCurso->execute();
$curso = $stmtCurso->get_result()->fetch_assoc();

if (!$curso) {
    echo "<section><h2>Curso no disponible</h2><p>No se encontró el curso solicitado en la base de datos.</p></section>";
    return;
}
if ($esAlumno && ($curso['docente_estado'] !== 'Activo' || $curso['estado'] !== 'Publicado')) {
    http_response_code(404);
    echo "<section><h2>Curso no disponible</h2><p>El curso no está disponible actualmente.</p></section>";
    return;
}

// 2. Obtener progreso de matrícula si es alumno
$progresoAlumno = 0.0;
$puedeSolicitarRevision = false;
if ($esAlumno) {
    $stmtMat = $conexion->prepare("SELECT 1 FROM matriculas WHERE usuario_id = ? AND curso_id = ?");
    $stmtMat->bind_param("ii", $usuario_id, $cursoId);
    $stmtMat->execute();
    $mat = $stmtMat->get_result()->fetch_assoc();
    if ($mat) {
        $puedeSolicitarRevision = true;

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
$revisionesPorTema = [];
if ($puedeSolicitarRevision) {
    $stmtHistorial = $conexion->prepare(
        'SELECT r.id, r.tema_id, r.estado, r.calificacion, r.observacion, r.entregable_url,
                r.solicitado_en, r.revisado_en
         FROM revisiones_temas r
         JOIN temas t ON t.id = r.tema_id
         JOIN bloques b ON b.id = t.bloque_id
         WHERE r.usuario_id = ? AND b.curso_id = ?
         ORDER BY r.tema_id, r.id'
    );
    $stmtHistorial->bind_param("ii", $usuario_id, $cursoId);
    $stmtHistorial->execute();
    $resHistorial = $stmtHistorial->get_result();
    while ($revision = $resHistorial->fetch_assoc()) {
        $revisionesPorTema[(int)$revision['tema_id']][] = $revision;
    }
}

$bloques = [];
$stmtB = $conexion->prepare("SELECT id, titulo FROM bloques WHERE curso_id = ? ORDER BY orden ASC");
$stmtB->bind_param("i", $cursoId);
$stmtB->execute();
$resB = $stmtB->get_result();

while ($b = $resB->fetch_assoc()) {
    $stmtT = $conexion->prepare(
        "SELECT t.id, t.titulo, t.descripcion, (pt.tema_id IS NOT NULL) AS completado
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
        $historial = $revisionesPorTema[(int)$t['id']] ?? [];
        $ultimaRevision = empty($historial) ? null : $historial[count($historial) - 1];
        $t['revision_historial'] = $historial;
        $t['revision_estado'] = $ultimaRevision['estado'] ?? null;
        $t['revision_calificacion'] = $ultimaRevision['calificacion'] ?? null;
        $t['revision_observacion'] = $ultimaRevision['observacion'] ?? null;
        $temas[] = $t;
    }
    $b['temas'] = $temas;
    $bloques[] = $b;
}

// 4. Materiales de Apoyo
$materialesPorTema = [];
$stmtM = $conexion->prepare("SELECT m.id, m.tema_id, m.nombre, m.url_archivo, m.url_externa, m.tipo
    FROM materiales m 
    JOIN temas t ON m.tema_id = t.id 
    JOIN bloques b ON t.bloque_id = b.id 
    WHERE b.curso_id = ?
    ORDER BY t.orden, m.id");
$stmtM->bind_param("i", $cursoId);
$stmtM->execute();
$resM = $stmtM->get_result();
while ($m = $resM->fetch_assoc()) {
    $materialesPorTema[(int)$m['tema_id']][] = $m;
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

    <!-- Temario, detalles y materiales del curso -->
    <div style="display: grid; grid-template-columns: minmax(0, 1fr); gap: 28px; align-items: start;">

        <!-- Temario interactivo -->
        <section class="summary-card" style="display: block; padding: 28px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 2px solid var(--color-border-gray); padding-bottom: 12px;">
                <h3 style="font-size: 20px; font-weight: 900; color: var(--color-charcoal);">Temario Interactivo</h3>
                <span style="font-size: 12px; font-weight: 700; color: var(--color-pencil-gray);">El ponente revisa el avance</span>
            </div>

            <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 20px;">
                <?= $puedeSolicitarRevision ? 'Solicita la revisión de cada tema. Tu avance aumentará cuando el ponente lo apruebe con una calificación de 7 o más.' : 'Consulta los temas disponibles en este curso.' ?>
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
                                <?php $estadoRevision = $tema['revision_estado'] ?? null; ?>
                                <li>
                                    <details style="border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn); background: var(--color-paper-white);">
                                        <summary style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px; cursor: pointer; list-style-position: inside;">
                                            <strong style="font-size: 14px; color: var(--color-charcoal); <?= $temaCompletado ? 'text-decoration: line-through; color: var(--color-pencil-gray);' : '' ?>">
                                                <?= htmlspecialchars($tema['titulo']) ?>
                                            </strong>
                                            <?php if ($temaCompletado): ?>
                                                <span style="color: #2e7a00; font-size: 12px; font-weight: 800; white-space: nowrap;">Aprobado</span>
                                            <?php elseif ($estadoRevision === 'pendiente'): ?>
                                                <span style="color: #d97706; font-size: 12px; font-weight: 800; white-space: nowrap;">Pendiente</span>
                                            <?php elseif ($estadoRevision === 'requiere_correccion'): ?>
                                                <span style="color: #b42318; font-size: 12px; font-weight: 800; white-space: nowrap;">Requiere corrección</span>
                                            <?php elseif ($puedeSolicitarRevision): ?>
                                                <span style="color: var(--color-pencil-gray); font-size: 12px; font-weight: 800; white-space: nowrap;">Disponible para revisión</span>
                                            <?php endif; ?>
                                        </summary>
                                        <div style="padding: 0 14px 14px;">
                                            <section aria-label="Detalles del tema">
                                                <?php if (!empty($tema['descripcion'])): ?>
                                                    <div style="margin-top: 8px; padding: 10px 12px; border-left: 3px solid var(--color-spark-blue); background: var(--color-paper-white); border-radius: 0 var(--radius-btn) var(--radius-btn) 0;">
                                                        <strong style="display: block; font-size: 11px; color: var(--color-pencil-gray); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">Descripción</strong>
                                                        <p style="font-size: 13px; color: var(--color-charcoal); margin: 0;">
                                                            <?= nl2br(htmlspecialchars($tema['descripcion'])) ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </section>

                                        <?php if ($puedeSolicitarRevision && !$temaCompletado && $estadoRevision !== 'pendiente'): ?>
                                            <form method="POST" action="dashboard.php?seccion=curso_detalle&amp;id=<?= $cursoId ?>" style="display: flex; flex-direction: column; align-items: flex-end; gap: 6px; min-width: min(100%, 220px);">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion" value="solicitar_revision_tema">
                                                <input type="hidden" name="tema_id" value="<?= (int)$tema['id'] ?>">
                                                <label style="width: 100%; font-size: 11px; font-weight: 800; color: var(--color-pencil-gray);">
                                                    Link del entregable (opcional)
                                                    <input type="url" name="entregable_url" maxlength="2048" placeholder="https://..." style="display: block; width: 100%; margin-top: 4px; padding: 8px; border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn);">
                                                </label>
                                                <button type="submit" class="action-btn" style="white-space: nowrap; border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                                                    <?= $estadoRevision === 'requiere_correccion' ? 'Solicitar nueva revisión' : 'Solicitar revisión' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php if ($puedeSolicitarRevision && !empty($tema['revision_historial'])): ?>
                                        <ol aria-label="Historial de revisiones" style="list-style: none; margin: 12px 0 0; padding: 10px 12px; background: var(--color-paper-white); border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn);">
                                            <?php foreach ($tema['revision_historial'] as $numeroRevision => $revision): ?>
                                                <li style="<?= $numeroRevision > 0 ? 'border-top: 1px solid var(--color-border-gray); margin-top: 10px; padding-top: 10px;' : '' ?>">
                                                    <strong style="font-size: 12px; color: var(--color-charcoal);">
                                                        Intento <?= $numeroRevision + 1 ?> · <?= htmlspecialchars(str_replace('_', ' ', ucfirst($revision['estado']))) ?>
                                                    </strong>
                                                    <?php if ($revision['calificacion'] !== null): ?>
                                                        <span style="font-size: 12px; color: var(--color-pencil-gray);">
                                                            · <?= number_format((float)$revision['calificacion'], 2) ?>/10
                                                        </span>
                                                    <?php endif; ?>
                                                    <span style="display: block; font-size: 11px; color: var(--color-pencil-gray); margin-top: 3px;">
                                                        Solicitada: <?= htmlspecialchars($revision['solicitado_en']) ?>
                                                        <?php if ($revision['revisado_en'] !== null): ?>
                                                            · Revisada: <?= htmlspecialchars($revision['revisado_en']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if ($revision['observacion'] !== null && $revision['observacion'] !== ''): ?>
                                                        <span style="display: block; font-size: 13px; color: var(--color-charcoal); margin-top: 5px;">
                                                            Observación: <?= nl2br(htmlspecialchars($revision['observacion'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($revision['entregable_url'])): ?>
                                                        <a href="<?= htmlspecialchars($revision['entregable_url']) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block; font-size: 12px; color: var(--color-spark-blue); font-weight: 800; margin-top: 5px;">
                                                            Ver entregable enviado ↗
                                                        </a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                    <?php if (!empty($materialesPorTema[(int)$tema['id']])): ?>
                                        <section aria-label="Material de apoyo del tema <?= htmlspecialchars($tema['titulo']) ?>" style="margin-top: 10px;">
                                            <strong style="display: block; font-size: 11px; color: var(--color-pencil-gray); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 5px;">Material de apoyo</strong>
                                            <ul style="list-style: none; display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0;">
                                                <?php foreach ($materialesPorTema[(int)$tema['id']] as $material): ?>
                                                    <?php if (!empty($material['url_externa'])): ?>
                                                        <li>
                                                            <a href="<?= htmlspecialchars($material['url_externa']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars(($material['nombre'] ?? 'Material de apoyo') . ' (se abre en una pestaña nueva)') ?>" class="action-btn" style="display: inline-flex; align-items: center; gap: 6px; color: var(--color-spark-blue); text-decoration: none;">
                                                                <?= htmlspecialchars($material['nombre'] ?? 'Material de apoyo') ?>
                                                                <span aria-hidden="true">↗</span>
                                                            </a>
                                                        </li>
                                                    <?php elseif (!empty($material['url_archivo'])): ?>
                                                        <li>
                                                            <a href="<?= htmlspecialchars($material['url_archivo']) ?>" download="<?= htmlspecialchars($material['nombre'] ?? '') ?>" class="action-btn" style="display: inline-flex; color: var(--color-spark-blue); text-decoration: none;">
                                                                <?= htmlspecialchars($material['nombre'] ?? 'Material de apoyo') ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </section>
                                    <?php endif; ?>
                                        </div>
                                    </details>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</article>
