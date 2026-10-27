<?php
$conexion = conect();
$resumenCursos = $conexion->query(
    "SELECT estado, COUNT(*) AS total
     FROM cursos
     GROUP BY estado
     ORDER BY estado"
);
$cursosPorEstado = [];
while ($fila = $resumenCursos->fetch_assoc()) {
    $cursosPorEstado[] = $fila;
}

$totalUsuarios = (int)$conexion->query('SELECT COUNT(*) FROM usuarios')->fetch_row()[0];
$totalCursos = (int)$conexion->query('SELECT COUNT(*) FROM cursos')->fetch_row()[0];
?>
<section>
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Configuración del Sistema</h2>
        <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
            Estado real de la plataforma, basado en los datos actualmente almacenados.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <article class="summary-card">
            <div class="card-data">
                <span class="card-label">Usuarios registrados</span>
                <span class="card-value"><?= number_format($totalUsuarios) ?></span>
            </div>
        </article>
        <article class="summary-card">
            <div class="card-data">
                <span class="card-label">Cursos registrados</span>
                <span class="card-value"><?= number_format($totalCursos) ?></span>
            </div>
        </article>
    </div>

    <div style="display: flex; flex-direction: column; gap: 24px; max-width: 900px;">
        <article class="summary-card" style="display: block; padding: 24px;">
            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 8px;">Mantenimiento</h3>
            <p style="font-size: 14px; color: var(--color-pencil-gray);">
                La base de datos actual no incluye una tabla de configuración global. El modo de mantenimiento no está configurado ni se puede activar desde esta pantalla.
            </p>
        </article>

        <article class="summary-card" style="display: block; padding: 24px;">
            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 14px;">Cursos por estado</h3>
            <?php if (empty($cursosPorEstado)): ?>
                <p style="color: var(--color-pencil-gray);">No hay cursos registrados.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th style="text-align: right;">Cursos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursosPorEstado as $fila): ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['estado']) ?></td>
                                <td style="text-align: right;"><?= (int)$fila['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p style="font-size: 13px; color: var(--color-pencil-gray); margin-top: 12px;">
                No existe una tabla de categorías de cursos en el esquema actual.
            </p>
        </article>

        <article class="summary-card" style="display: block; padding: 24px;">
            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 8px;">Copias de Seguridad</h3>
            <p style="font-size: 14px; color: var(--color-pencil-gray);">
                No hay un registro de copias de seguridad en la base de datos y la aplicación no tiene configurado un proceso de respaldo. No se muestran archivos de ejemplo.
            </p>
        </article>
    </div>
</section>
