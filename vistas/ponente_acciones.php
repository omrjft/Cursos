<?php
// Vista: Acciones del Ponente (Editar Temario y Calificar Entregas) con control de propiedad en servidor
if (!isset($_SESSION["id"]) || ($_SESSION["rol_id"] ?? null) != 4) {
    http_response_code(403);
    echo "<p style='color: #ea2b2b; font-weight: 700;'>Acceso denegado.</p>";
    return;
}

$conexion = conect();
$ponente_id = (int)$_SESSION["id"];
$curso_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$accion = $_GET['seccion'] ?? '';

// Comprobar estrictamente la propiedad del curso en el servidor
$stmtPropiedad = $conexion->prepare("SELECT id, titulo, descripcion, estado FROM cursos WHERE id = ? AND docente_id = ?");
$stmtPropiedad->bind_param("ii", $curso_id, $ponente_id);
$stmtPropiedad->execute();
$curso = $stmtPropiedad->get_result()->fetch_assoc();

if (!$curso) {
    echo "<div class='summary-card' style='border-color: #ea2b2b;'>
            <div class='card-icon-wrapper' style='background-color: #ffdfe0; color: #ea2b2b;'>
                <svg viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>
            </div>
            <div>
                <h3 style='color: #ea2b2b;'>Curso no encontrado o acceso no autorizado</h3>
                <p style='color: var(--color-pencil-gray); font-size: 14px;'>Solo puedes gestionar cursos de los que eres el ponente titular.</p>
                <p style='margin-top: 10px;'><a href='dashboard.php?seccion=cursos' class='action-btn'>&larr; Volver a Mis Cursos</a></p>
            </div>
          </div>";
    return;
}

?>

<section>
    <div style="margin-bottom: 20px;">
        <a href="dashboard.php?seccion=cursos" style="color: var(--color-spark-blue); text-decoration: none; font-weight: 700; font-size: 14px;">
            &larr; Volver a Mis Cursos
        </a>
        <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); margin-top: 8px;">
            <?= ($accion === 'editar_temario') ? 'Editar Temario:' : 'Calificar Entregas:' ?> 
            <span style="color: var(--color-eager-green);"><?= htmlspecialchars($curso['titulo']) ?></span>
        </h2>
    </div>

    <?php if ($accion === 'editar_temario'): ?>
        <!-- Panel de Edición de Temario -->
        <article class="summary-card" style="display: block;">
            <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 12px;">Gestión de Bloques y Temas</h3>
            <p style="color: var(--color-pencil-gray); font-size: 14px; margin-bottom: 20px;">
                Organiza la estructura de aprendizaje de tu curso. Los cambios se reflejarán para todos los alumnos matriculados.
            </p>

            <?php
            $stmtBloques = $conexion->prepare('SELECT id, titulo, orden FROM bloques WHERE curso_id = ? ORDER BY orden, id');
            $stmtBloques->bind_param('i', $curso_id);
            $stmtBloques->execute();
            $resBloques = $stmtBloques->get_result();
            $bloquesCurso = [];
            while ($bloque = $resBloques->fetch_assoc()) {
                $bloquesCurso[] = $bloque;
            }
            ?>
            <?php if (empty($bloquesCurso)): ?>
                <p style="color: var(--color-pencil-gray); margin-bottom: 16px;">Este curso aún no tiene bloques.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                    <?php foreach ($bloquesCurso as $bloque): ?>
                        <?php
                        $stmtTemas = $conexion->prepare('SELECT id, titulo, descripcion FROM temas WHERE bloque_id = ? ORDER BY orden, id');
                        $stmtTemas->bind_param('i', $bloque['id']);
                        $stmtTemas->execute();
                        $temasBloque = $stmtTemas->get_result();
                        ?>
                        <article style="border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 16px;">
                            <h4 style="margin-bottom: 10px;"><?= htmlspecialchars($bloque['titulo']) ?></h4>
                            <?php if ($temasBloque->num_rows > 0): ?>
                                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px;">
                                    <?php while ($tema = $temasBloque->fetch_assoc()): ?>
                                        <article style="border: 1px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 14px;">
                                            <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion_ponente" value="actualizar_tema">
                                                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                <input type="hidden" name="tema_id" value="<?= (int)$tema['id'] ?>">
                                                <label style="display: block; font-size: 13px; font-weight: 800; margin-bottom: 8px;">
                                                    Tema
                                                    <input name="titulo" maxlength="255" required value="<?= htmlspecialchars($tema['titulo']) ?>" style="display: block; width: 100%; margin-top: 5px;">
                                                </label>
                                                <label style="display: block; font-size: 13px; font-weight: 800; margin-bottom: 10px;">
                                                    Descripción
                                                    <textarea name="descripcion" maxlength="10000" rows="3" style="display: block; width: 100%; margin-top: 5px;"><?= htmlspecialchars($tema['descripcion'] ?? '') ?></textarea>
                                                </label>
                                                <button type="submit" class="action-btn">Guardar tema</button>
                                            </form>

                                            <?php
                                            $stmtMateriales = $conexion->prepare(
                                                'SELECT id, nombre, tipo, url_externa, url_archivo
                                                 FROM materiales
                                                 WHERE tema_id = ?
                                                 ORDER BY id'
                                            );
                                            $stmtMateriales->bind_param('i', $tema['id']);
                                            $stmtMateriales->execute();
                                            $materialesTema = $stmtMateriales->get_result();
                                            ?>
                                            <div style="margin-top: 14px;">
                                                <h5 style="font-size: 13px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 8px;">
                                                    Materiales existentes
                                                </h5>
                                                <?php if ($materialesTema->num_rows === 0): ?>
                                                    <p style="font-size: 12px; color: var(--color-pencil-gray);">Este tema aún no tiene materiales.</p>
                                                <?php else: ?>
                                                    <ul style="display: flex; flex-direction: column; gap: 8px; list-style: none; margin: 0; padding: 0;">
                                                        <?php while ($material = $materialesTema->fetch_assoc()): ?>
                                                            <?php $urlMaterialExistente = $material['url_externa'] ?: $material['url_archivo']; ?>
                                                            <li style="padding: 10px; background: var(--color-bg-light); border-radius: var(--radius-btn);">
                                                                <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>" style="display: grid; grid-template-columns: minmax(150px, 1fr) minmax(220px, 2fr) auto; gap: 8px; align-items: end;">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                                    <input type="hidden" name="accion_ponente" value="actualizar_material">
                                                                    <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                                    <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                                                    <label style="font-size: 12px; font-weight: 800;">
                                                                        Nombre
                                                                        <input name="nombre" maxlength="255" required value="<?= htmlspecialchars($material['nombre'] ?? '') ?>" style="display: block; width: 100%; margin-top: 5px;">
                                                                    </label>
                                                                    <label style="font-size: 12px; font-weight: 800;">
                                                                        Enlace externo (opcional)
                                                                        <input type="url" name="url_externa" maxlength="2048" value="<?= htmlspecialchars($material['url_externa'] ?? '') ?>" placeholder="https://..." style="display: block; width: 100%; margin-top: 5px;">
                                                                    </label>
                                                                    <button type="submit" class="action-btn">Guardar cambios</button>
                                                                </form>
                                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px;">
                                                                    <div style="min-width: 0;">
                                                                        <span style="font-size: 11px; color: var(--color-pencil-gray);">
                                                                            <?= htmlspecialchars($material['tipo'] ?? 'Material') ?>
                                                                        </span>
                                                                        <?php if (empty($material['url_externa']) && !empty($material['url_archivo'])): ?>
                                                                            <span style="display: block; font-size: 11px; color: var(--color-pencil-gray);">
                                                                                Archivo local; deja el enlace vacío para conservarlo.
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    <?php if (!empty($urlMaterialExistente)): ?>
                                                                        <a href="<?= htmlspecialchars($urlMaterialExistente) ?>"
                                                                           <?= !empty($material['url_externa']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                                                           style="display: block; overflow-wrap: anywhere; font-size: 12px; color: var(--color-spark-blue);">
                                                                            <?= htmlspecialchars($urlMaterialExistente) ?>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    </div>
                                                                    <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>"
                                                                          onsubmit="return confirm('¿Eliminar este material del tema?');">
                                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                                        <input type="hidden" name="accion_ponente" value="eliminar_material">
                                                                        <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                                        <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                                                        <button type="submit" class="action-btn" style="border-color: #b42318; color: #b42318;">
                                                                            Eliminar
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </li>
                                                        <?php endwhile; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </div>

                                            <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>" style="display: grid; grid-template-columns: minmax(150px, 1fr) minmax(220px, 2fr) auto; gap: 8px; align-items: end; margin-top: 14px;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="accion_ponente" value="agregar_material">
                                                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                <input type="hidden" name="tema_id" value="<?= (int)$tema['id'] ?>">
                                                <label style="font-size: 12px; font-weight: 800;">
                                                    Nombre del material
                                                    <input name="nombre" maxlength="255" required placeholder="Ej. Documentación" style="display: block; width: 100%; margin-top: 5px;">
                                                </label>
                                                <label style="font-size: 12px; font-weight: 800;">
                                                    Enlace externo
                                                    <input type="url" name="url_externa" maxlength="2048" required placeholder="https://..." style="display: block; width: 100%; margin-top: 5px;">
                                                </label>
                                                <button type="submit" class="action-btn">Añadir enlace</button>
                                            </form>
                                        </article>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--color-pencil-gray); margin-bottom: 14px;">Sin temas registrados.</p>
                            <?php endif; ?>
                            <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="accion_ponente" value="agregar_tema">
                                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                <input type="hidden" name="bloque_id" value="<?= (int)$bloque['id'] ?>">
                                <input name="titulo" maxlength="255" required placeholder="Título del nuevo tema">
                                <textarea name="descripcion" maxlength="10000" rows="2" placeholder="Descripción del tema (opcional)" style="flex: 1 1 100%;"></textarea>
                                <button type="submit" class="action-btn">Agregar tema</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="dashboard.php?seccion=editar_temario&amp;id=<?= $curso_id ?>" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="accion_ponente" value="agregar_bloque">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <input name="titulo" maxlength="255" required placeholder="Título del nuevo bloque">
                <button type="submit" class="welcome-btn" style="font-size: 13px; padding: 10px 18px;">+ Agregar Bloque</button>
            </form>
        </article>

    <?php elseif ($accion === 'calificar_entregas'): ?>
        <!-- Panel de Calificación de Entregas -->
        <?php
        // Obtener entregas de tareas pertenecientes a este curso del ponente
        $sqlEntregas = "SELECT e.id, e.calificacion, e.archivo_url, e.fecha_entrega, u.nombre as alumno, t.titulo as tarea
                        FROM entregas_tareas e
                        JOIN tareas t ON e.tarea_id = t.id
                        JOIN usuarios u ON e.usuario_id = u.id
                        WHERE t.curso_id = ? AND u.estado = 'Activo'
                        ORDER BY e.fecha_entrega DESC";
        $stmtE = $conexion->prepare($sqlEntregas);
        $stmtE->bind_param("i", $curso_id);
        $stmtE->execute();
        $resE = $stmtE->get_result();
        $entregas = [];
        while ($fila = $resE->fetch_assoc()) {
            $entregas[] = $fila;
        }
        ?>

        <?php if (empty($entregas)): ?>
            <article class="summary-card" style="text-align: center; justify-content: center; padding: 36px 20px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 6px;">No hay entregas pendientes para este curso</h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray);">
                        Cuando los alumnos envíen sus prácticas y proyectos, aparecerán aquí para su revisión.
                    </p>
                </div>
            </article>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Tarea</th>
                            <th>Fecha de Entrega</th>
                            <th>Archivo</th>
                            <th>Estado / Calificación</th>
                            <th style="text-align: right;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregas as $entrega): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--color-charcoal);">
                                    <?= htmlspecialchars($entrega['alumno']) ?>
                                </td>
                                <td><?= htmlspecialchars($entrega['tarea']) ?></td>
                                <td style="color: var(--color-pencil-gray); font-size: 13px;">
                                    <?= htmlspecialchars($entrega['fecha_entrega']) ?>
                                </td>
                                <td>
                                    <span style="color: var(--color-spark-blue); font-weight: 700; font-size: 13px;">
                                        <?= htmlspecialchars(basename($entrega['archivo_url'] ?? 'entrega.zip')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($entrega['calificacion'] === null): ?>
                                        <span style="background-color: #fff4e5; color: #d97706; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 800;">
                                            Pendiente
                                        </span>
                                    <?php else: ?>
                                        <span style="background-color: var(--color-storybook-green); color: #2e7a00; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 800;">
                                            Nota: <?= htmlspecialchars($entrega['calificacion']) ?> / 10
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($entrega['calificacion'] === null): ?>
                                        <form method="POST" action="dashboard.php?seccion=calificar_entregas&amp;id=<?= $curso_id ?>" style="display: inline-flex; gap: 6px; align-items: center;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="accion_ponente" value="calificar_entrega">
                                            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                            <input type="hidden" name="entrega_id" value="<?= (int)$entrega['id'] ?>">
                                            <input type="number" name="calificacion" min="0" max="10" step="0.01" required aria-label="Calificación para <?= htmlspecialchars($entrega['alumno']) ?>" style="width: 84px;">
                                            <button type="submit" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                                                Guardar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
