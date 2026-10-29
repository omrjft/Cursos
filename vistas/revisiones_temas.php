<?php
if (!isset($_SESSION['id']) || (int)($_SESSION['rol_id'] ?? 0) !== 4) {
    http_response_code(403);
    echo '<p style="color: #ea2b2b; font-weight: 700;">Acceso denegado.</p>';
    return;
}

$conexion = conect();
$ponenteId = (int)$_SESSION['id'];
$stmtPendientes = $conexion->prepare(
    "SELECT r.id, r.solicitado_en, r.entregable_url, u.nombre AS alumno, c.titulo AS curso,
            t.titulo AS tema
     FROM revisiones_temas r
     JOIN usuarios u ON u.id = r.usuario_id AND u.estado = 'Activo'
     JOIN temas t ON t.id = r.tema_id
     JOIN bloques b ON b.id = t.bloque_id
     JOIN cursos c ON c.id = b.curso_id
     JOIN usuarios ponente ON ponente.id = c.docente_id AND ponente.estado = 'Activo'
     WHERE r.ponente_id = ? AND r.estado = 'pendiente'
       AND c.docente_id = r.ponente_id
     ORDER BY r.solicitado_en ASC, r.id ASC"
);
$stmtPendientes->bind_param('i', $ponenteId);
$stmtPendientes->execute();
$resultadoPendientes = $stmtPendientes->get_result();
$solicitudesPendientes = [];
while ($solicitud = $resultadoPendientes->fetch_assoc()) {
    $solicitudesPendientes[] = $solicitud;
}
?>

<section>
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal);">Revisión de temas</h2>
        <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
            Evalúa los temas que tus alumnos enviaron para revisión. Una nota de 7 o más los aprobará.
        </p>
    </div>

    <?php if (empty($solicitudesPendientes)): ?>
        <article class="summary-card" style="display: block; text-align: center; padding: 32px;">
            No tienes temas pendientes de revisar.
        </article>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 20px;">
            <?php foreach ($solicitudesPendientes as $solicitud): ?>
                <article class="summary-card" style="display: block; padding: 22px;">
                    <h3 style="font-size: 18px; font-weight: 900; color: var(--color-charcoal);">
                        <?= htmlspecialchars($solicitud['tema']) ?>
                    </h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray); margin-top: 8px;">
                        <?= htmlspecialchars($solicitud['alumno']) ?> &middot;
                        <?= htmlspecialchars($solicitud['curso']) ?>
                    </p>
                    <p style="font-size: 12px; color: var(--color-pencil-gray); margin-top: 4px;">
                        Solicitada: <?= htmlspecialchars($solicitud['solicitado_en']) ?>
                    </p>
                    <?php if (!empty($solicitud['entregable_url'])): ?>
                        <p style="margin-top: 10px;">
                            <a href="<?= htmlspecialchars($solicitud['entregable_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--color-spark-blue); font-size: 14px; font-weight: 800;">
                                Abrir entregable del alumno ↗
                            </a>
                        </p>
                    <?php else: ?>
                        <p style="font-size: 12px; color: var(--color-pencil-gray); margin-top: 8px;">
                            El alumno no adjuntó un enlace de entregable.
                        </p>
                    <?php endif; ?>

                    <form method="POST" action="dashboard.php?seccion=revisiones_temas" style="margin-top: 18px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="accion" value="calificar_revision_tema">
                        <input type="hidden" name="revision_id" value="<?= (int)$solicitud['id'] ?>">
                        <div style="display: grid; grid-template-columns: minmax(100px, 140px) 1fr; gap: 12px; align-items: end;">
                            <label style="font-size: 13px; font-weight: 800; color: var(--color-charcoal);">
                                Calificación (0–10)
                                <input type="number" name="calificacion" min="0" max="10" step="0.01" required
                                       style="display: block; width: 100%; height: 42px; margin-top: 6px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 10px;">
                            </label>
                            <label style="font-size: 13px; font-weight: 800; color: var(--color-charcoal);">
                                Observaciones
                                <textarea name="observacion" maxlength="2000" rows="2" required
                                          placeholder="Indica qué debe corregir el alumno si la nota es menor a 7."
                                          style="display: block; width: 100%; margin-top: 6px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 9px 10px; resize: vertical;"></textarea>
                            </label>
                        </div>
                        <p style="font-size: 12px; color: var(--color-pencil-gray); margin: 8px 0 14px;">
                            Menos de 7 requiere correcciones; 7 o más completa el tema.
                        </p>
                        <button type="submit" class="welcome-btn" style="font-size: 13px; padding: 10px 18px;">
                            Guardar revisión
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
