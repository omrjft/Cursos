<?php
// Vista: Mis Alumnos (para Ponente)
// Requiere sesión de ponente activa y $usuario_id disponible en el contexto

if (!isset($_SESSION["id"]) || ($_SESSION["rol_id"] ?? null) != 4) {
    http_response_code(403);
    echo "<p style='color: #ea2b2b; font-weight: 700;'>Acceso denegado. Esta sección es exclusiva para ponentes.</p>";
    return;
}

$conexion = conect();
$ponente_id = (int)$_SESSION["id"];

// Consulta segura filtrando exclusivamente por los cursos del ponente autenticado
$sql = "SELECT u.nombre, u.email, c.titulo AS curso, avance.progreso_global
        FROM matriculas m
        JOIN usuarios u ON m.usuario_id = u.id
        JOIN cursos c ON m.curso_id = c.id
        JOIN (
            SELECT matricula.usuario_id, matricula.curso_id,
                   CASE WHEN COUNT(DISTINCT tema.id) = 0 THEN 0
                        ELSE ROUND(COUNT(DISTINCT progreso.tema_id) * 100 / COUNT(DISTINCT tema.id), 2)
                   END AS progreso_global
            FROM matriculas matricula
            LEFT JOIN bloques bloque ON bloque.curso_id = matricula.curso_id
            LEFT JOIN temas tema ON tema.bloque_id = bloque.id
            LEFT JOIN progreso_temas progreso
              ON progreso.tema_id = tema.id AND progreso.usuario_id = matricula.usuario_id
            GROUP BY matricula.usuario_id, matricula.curso_id
        ) avance ON avance.usuario_id = m.usuario_id AND avance.curso_id = m.curso_id
        WHERE c.docente_id = ?
        ORDER BY u.nombre ASC, c.titulo ASC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $ponente_id);
$stmt->execute();
$resultado = $stmt->get_result();

$alumnos = [];
while ($fila = $resultado->fetch_assoc()) {
    $alumnos[] = $fila;
}
?>

<section>
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Mis Alumnos</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Estudiantes inscritos en tus cursos activos y su avance actual.
            </p>
        </div>
        <div style="background-color: var(--color-bg-light); border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 8px 16px; font-size: 14px; font-weight: 700; color: var(--color-charcoal);">
            Total inscripciones: <strong><?= count($alumnos) ?></strong>
        </div>
    </div>

    <?php if (empty($alumnos)): ?>
        <article class="summary-card" style="text-align: center; justify-content: center; padding: 40px 20px;">
            <div style="max-width: 460px;">
                <div class="card-icon-wrapper icon-blue" style="margin: 0 auto 16px auto;">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 6px;">Aún no tienes alumnos inscritos</h3>
                <p style="font-size: 14px; color: var(--color-pencil-gray);">
                    Cuando los estudiantes se matriculen a tus cursos publicados, aparecerán aquí para que des seguimiento a su progreso.
                </p>
            </div>
        </article>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Curso en el que está inscrito</th>
                        <th>Progreso / Calificación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $alumno): ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--color-charcoal);">
                                <?= htmlspecialchars($alumno['nombre']) ?>
                            </td>
                            <td style="color: var(--color-pencil-gray);">
                                <?= htmlspecialchars($alumno['email']) ?>
                            </td>
                            <td>
                                <span style="display: inline-block; background-color: var(--color-bg-light); border: 1px solid var(--color-border-gray); padding: 4px 10px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                                    <?= htmlspecialchars($alumno['curso']) ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $progreso = (float)($alumno['progreso_global'] ?? 0);
                                $colorProgreso = $progreso >= 70 ? 'var(--color-eager-green)' : ($progreso >= 40 ? 'var(--color-spark-blue)' : '#e67e22');
                                ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="flex: 1; max-width: 120px; height: 10px; background-color: #e5e5e5; border-radius: 999px; overflow: hidden;">
                                        <div style="width: <?= min(100, max(0, $progreso)) ?>%; height: 100%; background-color: <?= $colorProgreso ?>; border-radius: 999px;"></div>
                                    </div>
                                    <span style="font-weight: 800; font-size: 13px; color: var(--color-charcoal);">
                                        <?= number_format($progreso, 1) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
