<?php
$resultadoPostulacionesDirector = $db->query(
    "SELECT id, nombre, email, experiencia, estado, fecha_solicitud,
            fecha_revision, motivo_rechazo
     FROM postulaciones_directores
     ORDER BY (estado = 'pendiente') DESC, fecha_solicitud DESC, id DESC"
);
$postulacionesDirector = [];
$pendientesPostulacionDirector = 0;
while ($postulacionDirector = $resultadoPostulacionesDirector->fetch_assoc()) {
    $postulacionesDirector[] = $postulacionDirector;
    if ($postulacionDirector['estado'] === 'pendiente') {
        $pendientesPostulacionDirector++;
    }
}
?>

<section>
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal);">Postulaciones de Directores</h2>
        <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
            Revisa la experiencia de cada aspirante y decide si crear su cuenta de director.
        </p>
        <p style="margin-top: 10px; font-size: 14px; color: var(--color-charcoal);">
            Solicitudes pendientes: <strong><?= $pendientesPostulacionDirector ?></strong>
        </p>
    </div>

    <?php if ($postulacionesDirector === []): ?>
        <div class="summary-card" style="display: block; padding: 24px;">
            No hay postulaciones de directores todavía.
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 18px;">
            <?php foreach ($postulacionesDirector as $postulacionDirector): ?>
                <?php
                $estadoPostulacionDirector = (string)$postulacionDirector['estado'];
                $estadoEtiquetaDirector = match ($estadoPostulacionDirector) {
                    'pendiente' => 'Pendiente',
                    'aprobada' => 'Aprobada',
                    'rechazada' => 'Rechazada',
                    default => 'Desconocida',
                };
                ?>
                <article class="summary-card" style="display: block; padding: 22px;">
                    <div style="display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: flex-start;">
                        <div>
                            <h3 style="font-size: 19px; color: var(--color-charcoal);">
                                <?= htmlspecialchars($postulacionDirector['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </h3>
                            <p style="margin-top: 4px; color: var(--color-pencil-gray);">
                                <?= htmlspecialchars($postulacionDirector['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </p>
                            <p style="margin-top: 8px; font-size: 13px; color: var(--color-pencil-gray);">
                                Enviada: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($postulacionDirector['fecha_solicitud'])), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <span style="display: inline-block; padding: 5px 11px; border-radius: 999px; font-size: 12px; font-weight: 800; background: <?= $estadoPostulacionDirector === 'pendiente' ? '#3c3318' : ($estadoPostulacionDirector === 'aprobada' ? 'var(--color-storybook-green)' : '#3a2028') ?>; color: var(--color-charcoal);">
                            <?= htmlspecialchars($estadoEtiquetaDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </span>
                    </div>

                    <div style="margin-top: 16px;">
                        <h4 style="font-size: 14px; margin-bottom: 6px;">Experiencia o especialidad</h4>
                        <p style="white-space: pre-wrap; line-height: 1.55;"><?= htmlspecialchars($postulacionDirector['experiencia'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    </div>

                    <?php if ($estadoPostulacionDirector === 'pendiente'): ?>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; align-items: flex-end;">
                            <form method="POST" action="dashboard.php?seccion=postulaciones_directores">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="accion" value="aprobar_postulacion_director">
                                <input type="hidden" name="postulacion_id" value="<?= (int)$postulacionDirector['id'] ?>">
                                <button type="submit" class="welcome-btn">Aprobar y crear cuenta</button>
                            </form>

                            <form method="POST" action="dashboard.php?seccion=postulaciones_directores" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="accion" value="rechazar_postulacion_director">
                                <input type="hidden" name="postulacion_id" value="<?= (int)$postulacionDirector['id'] ?>">
                                <label style="font-size: 12px; font-weight: 700;">
                                    Motivo (opcional)
                                    <input
                                        type="text"
                                        name="motivo_rechazo"
                                        maxlength="500"
                                        style="display: block; min-width: 240px; height: 40px; margin-top: 4px; padding: 0 10px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn);"
                                    >
                                </label>
                                <button type="submit" class="action-btn">Rechazar</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p style="margin-top: 14px; font-size: 13px; color: var(--color-pencil-gray);">
                            Resuelta: <?= $postulacionDirector['fecha_revision'] !== null
                                ? htmlspecialchars(date('d/m/Y H:i', strtotime($postulacionDirector['fecha_revision'])), ENT_QUOTES, 'UTF-8')
                                : '—' ?>
                            <?php if ($estadoPostulacionDirector === 'rechazada' && (string)$postulacionDirector['motivo_rechazo'] !== ''): ?>
                                · Motivo: <?= htmlspecialchars($postulacionDirector['motivo_rechazo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
