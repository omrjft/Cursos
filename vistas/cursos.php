<?php
// Vista: Catálogo de Cursos (Director) / Gestión Global de Cursos (Superadmin) / Mis Cursos (Alumno y Ponente)
$conexion = conect();
$usuario_id = (int)($_SESSION["id"] ?? 0);
$rol_id = (int)($_SESSION["rol_id"] ?? 0);

$esSuperadmin = ($rol_id === 1);
$esDirector = ($rol_id === 2);
$esAlumno = ($rol_id === 3);
$esPonente = ($rol_id === 4);

// =========================================================================
// 1. MODO SUPERADMIN: Gestión Global de Cursos
//    - Reestructuración de Tabla: Eliminadas métricas de docente ("Tareas pendientes", "Alumnos inscritos").
//    - Nuevas Columnas: Título, Docente Asignado, Estado (Borrador o Publicado), Fecha de Creación.
//    - Nuevas Acciones: Publicar un borrador y reasignar al ponente.
// =========================================================================
if ($esSuperadmin):
    $stmtGlobal = $conexion->prepare(
        "SELECT c.id, c.titulo, c.docente_id, COALESCE(u.nombre, 'Sin asignar') AS docente,
                c.estado, c.created_at
         FROM cursos c
         LEFT JOIN usuarios u ON c.docente_id = u.id
         ORDER BY c.created_at DESC, c.id DESC"
    );
    $stmtGlobal->execute();
    $resultadoGlobal = $stmtGlobal->get_result();
    $cursosGlobales = [];
    while ($fila = $resultadoGlobal->fetch_assoc()) {
        $cursosGlobales[] = $fila;
    }
?>
    <section>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Gestión Global de Cursos</h2>
                <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                    Control administrativo y auditoría de todas las asignaturas de la plataforma.
                </p>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="min-width: 240px;">Título del Curso</th>
                        <th>Docente Asignado</th>
                        <th style="text-align: center;">Estado</th>
                        <th style="text-align: center;">Fecha de Creación</th>
                        <th style="text-align: right; min-width: 260px;">Acciones de Administración</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosGlobales)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 24px;">
                                No hay cursos registrados en la base de datos.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($cursosGlobales as $cg): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 15px; color: var(--color-charcoal);">
                                    <?= htmlspecialchars($cg['titulo']) ?>
                                </strong>
                            </td>
                            <td style="color: var(--color-pencil-gray); font-weight: 600;">
                                <?= htmlspecialchars($cg['docente']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                    $est = $cg['estado'];
                                    if ($est === 'Publicado') {
                                        $estiloPill = 'background-color: var(--color-storybook-green); color: var(--color-spark-blue);';
                                    } elseif ($est === 'Inactivo') {
                                        $estiloPill = 'background-color: #3a2028; color: #ff7887;';
                                    } else {
                                        $estiloPill = 'background-color: #3c2d18; color: #f0b35e;';
                                    }
                                ?>
                                <span style="display: inline-block; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; <?= $estiloPill ?>">
                                    <?= htmlspecialchars($est) ?>
                                </span>
                            </td>
                            <td style="text-align: center; color: var(--color-pencil-gray); font-size: 14px;">
                                <?= $cg['created_at'] ? htmlspecialchars(date('d/m/Y', strtotime($cg['created_at']))) : '—' ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($cg['estado'] === 'Borrador'): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="actualizar_estado_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$cg['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-eager-green); color: var(--color-spark-blue); margin-right: 4px;">
                                            Publicar
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($cg['estado'], ['Publicado', 'Borrador'], true)): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="desactivar_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$cg['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-error); color: var(--color-error); margin-right: 4px;">
                                            Desactivar
                                        </button>
                                    </form>
                                <?php elseif ($cg['estado'] === 'Inactivo'): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="activar_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$cg['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue); margin-right: 4px;">
                                            Reactivar como borrador
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($ponentes)): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline-flex; gap: 4px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="reasignar_docente">
                                        <input type="hidden" name="curso_id" value="<?= (int)$cg['id'] ?>">
                                        <select name="docente_id" required aria-label="Seleccionar docente para <?= htmlspecialchars($cg['titulo']) ?>" style="max-width: 150px; border: 1px solid var(--color-border-gray); border-radius: 8px; padding: 4px;">
                                            <option value="">Asignar docente</option>
                                            <?php foreach ($ponentes as $ponente): ?>
                                                <option value="<?= (int)$ponente['id'] ?>" <?= (int)$cg['docente_id'] === (int)$ponente['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ponente['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                                            Asignar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--color-pencil-gray);">No hay docentes activos</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php
// ========================================================
// 2. MODO DIRECTOR: Catálogo de Cursos (Tabla de Monitoreo)
// ========================================================
elseif ($esDirector):
    $sqlTodosCursos = "SELECT c.id, c.titulo, c.descripcion, c.estado, u.nombre AS docente,
                        (SELECT COUNT(*) FROM matriculas m
                         JOIN usuarios alumno ON alumno.id = m.usuario_id AND alumno.estado = 'Activo'
                         WHERE m.curso_id = c.id) AS total_alumnos
                       FROM cursos c
                       JOIN usuarios u ON c.docente_id = u.id AND u.estado = 'Activo'
                       ORDER BY c.id ASC";
    $resTC = $conexion->query($sqlTodosCursos);
    $cursosCatalogo = [];
    if ($resTC) {
        while ($fila = $resTC->fetch_assoc()) {
            $cursosCatalogo[] = $fila;
        }
    }

?>
    <section>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Catálogo de Cursos</h2>
                <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                    Monitoreo de todas las asignaturas activas, docentes a cargo y estado de publicación.
                </p>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="min-width: 240px;">Título del Curso</th>
                        <th>Docente Asignado</th>
                        <th style="text-align: center;">Total de Alumnos</th>
                        <th style="text-align: center;">Estado</th>
                        <th style="text-align: right; min-width: 140px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursosCatalogo)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-pencil-gray); padding: 24px;">
                                No hay cursos registrados en la base de datos.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($cursosCatalogo as $c): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 15px; color: var(--color-charcoal);">
                                    <?= htmlspecialchars($c['titulo']) ?>
                                </strong>
                            </td>
                            <td style="color: var(--color-pencil-gray); font-weight: 600;">
                                <?= htmlspecialchars($c['docente'] ?? 'Sin asignar') ?>
                            </td>
                            <td style="text-align: center; font-weight: 800; font-size: 15px; color: var(--color-charcoal);">
                                <?= (int)$c['total_alumnos'] ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
                                    if ($c['estado'] === 'Publicado') {
                                        $estiloEstadoCatalogo = 'background-color: var(--color-storybook-green); color: var(--color-spark-blue);';
                                    } elseif ($c['estado'] === 'Inactivo') {
                                        $estiloEstadoCatalogo = 'background-color: #3a2028; color: #ff7887;';
                                    } else {
                                        $estiloEstadoCatalogo = 'background-color: #3c2d18; color: #f0b35e;';
                                    }
                                ?>
                                <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; <?= $estiloEstadoCatalogo ?>">
                                    <?= htmlspecialchars($c['estado'] ?? 'Borrador') ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($c['estado'] === 'Borrador'): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="actualizar_estado_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-eager-green); color: var(--color-spark-blue); margin-right: 4px;">
                                            Publicar
                                        </button>
                                    </form>
                                <?php elseif ($c['estado'] === 'Publicado'): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="desactivar_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-error); color: var(--color-error); margin-right: 4px;">
                                            Desactivar
                                        </button>
                                    </form>
                                <?php elseif ($c['estado'] === 'Inactivo'): ?>
                                    <form method="POST" action="dashboard.php?seccion=cursos" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="activar_curso">
                                        <input type="hidden" name="curso_id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue); margin-right: 4px;">
                                            Reactivar como borrador
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="dashboard.php?seccion=curso_detalle&id=<?= (int)$c['id'] ?>" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue);">
                                    Ver Temario
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php
// ==========================================
// 3. MODO ALUMNO (Tarjetas de Cursos Inscritos)
// ==========================================
elseif ($esAlumno):
    $sqlCursosAlumno = "SELECT c.id, c.titulo, c.descripcion, u.nombre as docente,
                               CASE WHEN COUNT(DISTINCT t.id) = 0 THEN 0
                                    ELSE ROUND(COUNT(DISTINCT pt.tema_id) * 100 / COUNT(DISTINCT t.id), 2)
                               END AS progreso_global
                        FROM matriculas m
                        JOIN cursos c ON m.curso_id = c.id
                        JOIN usuarios u ON c.docente_id = u.id AND u.estado = 'Activo'
                        LEFT JOIN bloques b ON b.curso_id = c.id
                        LEFT JOIN temas t ON t.bloque_id = b.id
                        LEFT JOIN progreso_temas pt
                          ON pt.tema_id = t.id AND pt.usuario_id = m.usuario_id
                        WHERE m.usuario_id = ? AND c.estado = 'Publicado'
                        GROUP BY m.usuario_id, c.id, c.titulo, c.descripcion, u.nombre
                        ORDER BY c.titulo ASC";
    $stmtCA = $conexion->prepare($sqlCursosAlumno);
    $stmtCA->bind_param("i", $usuario_id);
    $stmtCA->execute();
    $resCA = $stmtCA->get_result();

    $cursosInscritos = [];
    while ($fila = $resCA->fetch_assoc()) {
        $cursosInscritos[] = $fila;
    }

    $stmtCatalogoAlumno = $conexion->prepare(
        "SELECT c.id, c.titulo, c.descripcion, u.nombre AS docente
         FROM cursos c
         JOIN usuarios u ON u.id = c.docente_id AND u.estado = 'Activo'
         WHERE c.estado = 'Publicado'
           AND NOT EXISTS (
               SELECT 1 FROM matriculas m
               WHERE m.curso_id = c.id AND m.usuario_id = ?
           )
         ORDER BY c.titulo ASC"
    );
    $stmtCatalogoAlumno->bind_param('i', $usuario_id);
    $stmtCatalogoAlumno->execute();
    $resultadoCatalogoAlumno = $stmtCatalogoAlumno->get_result();
    $cursosDisponibles = [];
    while ($fila = $resultadoCatalogoAlumno->fetch_assoc()) {
        $cursosDisponibles[] = $fila;
    }

?>
    <section>
        <div style="margin-bottom: 32px;">
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Mis Cursos</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                Cursos en los que te encuentras matriculado actualmente. Sigue tu avance día a día.
            </p>

            <?php if (empty($cursosInscritos)): ?>
                <article class="summary-card" style="display: block; text-align: center; padding: 32px; margin-top: 18px;">
                    Aún no estás inscrito en ningún curso. Explora el catálogo y elige por dónde empezar.
                </article>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin-top: 18px;">
                    <?php foreach ($cursosInscritos as $c): ?>
                        <?php
                            $progreso = round((float)($c['progreso_global'] ?? 0));
                            $estaCompletado = ($progreso >= 100);
                        ?>
                        <article class="summary-card" style="display: flex; flex-direction: column; align-items: stretch; justify-content: space-between; gap: 18px; padding: 26px;">
                            <div>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; padding: 4px 10px; border-radius: 999px; <?= $estaCompletado ? 'background-color: var(--color-storybook-green); color: var(--color-spark-blue);' : 'background-color: #162e46; color: var(--color-spark-blue);' ?>">
                                        <?= $estaCompletado ? 'Completado' : 'En progreso' ?>
                                    </span>
                                    <span style="font-size: 14px; font-weight: 800; color: var(--color-charcoal);">
                                        <?= $progreso ?>%
                                    </span>
                                </div>

                                <h3 style="font-size: 20px; font-weight: 900; color: var(--color-charcoal); line-height: 1.25; margin-bottom: 8px;">
                                    <?= htmlspecialchars($c['titulo']) ?>
                                </h3>

                                <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 16px;">
                                    Docente: <strong style="color: var(--color-charcoal);"><?= htmlspecialchars($c['docente']) ?></strong>
                                </p>

                                <div style="margin-bottom: 18px;">
                                    <progress value="<?= $progreso ?>" max="100" style="width: 100%; height: 14px; border-radius: 999px; overflow: hidden; accent-color: var(--color-eager-green);"></progress>
                                </div>
                            </div>

                            <a href="dashboard.php?seccion=curso_detalle&id=<?= (int)$c['id'] ?>" class="welcome-btn" style="text-align: center; justify-content: center; padding: 12px 18px;">
                                Continuar Curso &rarr;
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Explorar cursos</h2>
            <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px; margin-bottom: 18px;">
                Descubre cursos publicados e inscríbete para comenzar.
            </p>

            <?php if (empty($cursosDisponibles)): ?>
                <article class="summary-card" style="display: block; text-align: center; padding: 32px;">
                    No hay cursos nuevos disponibles por el momento.
                </article>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
                    <?php foreach ($cursosDisponibles as $cursoDisponible): ?>
                        <article class="summary-card" style="display: flex; flex-direction: column; align-items: stretch; justify-content: space-between; gap: 18px; padding: 26px;">
                            <div>
                                <span style="display: inline-block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; padding: 4px 10px; border-radius: 999px; background-color: var(--color-storybook-green); color: var(--color-spark-blue); margin-bottom: 12px;">
                                    Disponible
                                </span>
                                <h3 style="font-size: 20px; font-weight: 900; color: var(--color-charcoal); line-height: 1.25; margin-bottom: 8px;">
                                    <?= htmlspecialchars($cursoDisponible['titulo']) ?>
                                </h3>
                                <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 12px;">
                                    Docente: <strong style="color: var(--color-charcoal);"><?= htmlspecialchars($cursoDisponible['docente']) ?></strong>
                                </p>
                                <p style="font-size: 14px; color: var(--color-pencil-gray); line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($cursoDisponible['descripcion'] ?? 'Sin descripción disponible.')) ?>
                                </p>
                            </div>
                            <form method="POST" action="dashboard.php?seccion=cursos">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="accion" value="inscribir_curso">
                                <input type="hidden" name="curso_id" value="<?= (int)$cursoDisponible['id'] ?>">
                                <button type="submit" class="welcome-btn" style="width: 100%; justify-content: center; padding: 12px 18px;">
                                    Inscribirme
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php
// ==========================================
// 4. MODO PONENTE (Gestión de Sus Cursos)
// ==========================================
else:
    $cursos = [];
    $resumenDocente = [
        'total_alumnos' => 0
    ];

    $sqlCursos = "SELECT c.id, c.titulo, c.descripcion, c.estado,
                    (SELECT COUNT(*) FROM matriculas m
                     JOIN usuarios alumno ON alumno.id = m.usuario_id AND alumno.estado = 'Activo'
                     WHERE m.curso_id = c.id) AS alumnos,
                    (SELECT COUNT(*) FROM revisiones_temas revision
                     JOIN usuarios alumno ON alumno.id = revision.usuario_id AND alumno.estado = 'Activo'
                     JOIN temas tema ON tema.id = revision.tema_id
                     JOIN bloques bloque ON bloque.id = tema.bloque_id
                     WHERE bloque.curso_id = c.id
                       AND revision.ponente_id = c.docente_id
                       AND revision.estado = 'pendiente') AS revisiones_pendientes
                  FROM cursos c
                  JOIN usuarios ponente ON ponente.id = c.docente_id AND ponente.estado = 'Activo'
                  WHERE c.docente_id = ?
                  ORDER BY c.id DESC";

    $stmtCursos = $conexion->prepare($sqlCursos);
    $stmtCursos->bind_param("i", $usuario_id);
    $stmtCursos->execute();
    $resC = $stmtCursos->get_result();

    while ($fila = $resC->fetch_assoc()) {
        $cursos[] = $fila;
    }

    $stmtTotales = $conexion->prepare("SELECT 
        (SELECT COUNT(DISTINCT m.usuario_id) FROM matriculas m JOIN usuarios alumno ON alumno.id = m.usuario_id AND alumno.estado = 'Activo' JOIN cursos c ON m.curso_id = c.id WHERE c.docente_id = ?) AS total_alumnos,
        (SELECT COUNT(*) FROM revisiones_temas revision
         JOIN usuarios alumno ON alumno.id = revision.usuario_id AND alumno.estado = 'Activo'
         JOIN temas tema ON tema.id = revision.tema_id
         JOIN bloques bloque ON bloque.id = tema.bloque_id
         JOIN cursos c ON c.id = bloque.curso_id AND c.docente_id = revision.ponente_id
         WHERE revision.ponente_id = ? AND revision.estado = 'pendiente') AS revisiones_pendientes");
    $stmtTotales->bind_param("ii", $usuario_id, $usuario_id);
    $stmtTotales->execute();
    $resTotales = $stmtTotales->get_result()->fetch_assoc();

    $resumenDocente['total_alumnos'] = (int)($resTotales['total_alumnos'] ?? 0);
    $resumenDocente['revisiones_pendientes'] = (int)($resTotales['revisiones_pendientes'] ?? 0);
?>
    <section>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); letter-spacing: -0.01em;">Mis Cursos</h2>
                <p style="color: var(--color-pencil-gray); font-size: 15px; margin-top: 4px;">
                    Gestiona tus asignaturas, temarios y solicitudes de revisión de temas.
                </p>
            </div>

            <a href="dashboard.php?seccion=nuevo_curso" class="welcome-btn" style="display: inline-flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                + Crear Nuevo Curso
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                <div class="card-icon-wrapper icon-blue" style="width: 44px; height: 44px;">
                    <svg viewBox="0 0 24 24" style="width: 22px; height: 22px;"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <div>
                    <span class="card-label" style="font-size: 11px;">Total de Alumnos</span>
                    <p style="font-size: 22px; font-weight: 900; color: var(--color-charcoal);"><?= $resumenDocente['total_alumnos'] ?></p>
                </div>
            </div>

            <div style="background-color: var(--color-paper-white); border: 2px solid var(--color-border-gray); border-radius: var(--radius-card); padding: 16px 20px; display: flex; align-items: center; gap: 14px;">
                <div class="card-icon-wrapper icon-dark" style="width: 44px; height: 44px;">
                    <svg viewBox="0 0 24 24" style="width: 22px; height: 22px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                </div>
                <div>
                    <a href="dashboard.php?seccion=revisiones_temas" style="color: inherit; text-decoration: none;">
                        <span class="card-label" style="font-size: 11px;">Temas por Revisar</span>
                        <p style="font-size: 22px; font-weight: 900; color: var(--color-charcoal);"><?= $resumenDocente['revisiones_pendientes'] ?></p>
                        <span style="font-size: 12px; color: var(--color-spark-blue); font-weight: 800;">Abrir revisiones</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if (empty($cursos)): ?>
            <article class="summary-card" style="text-align: center; justify-content: center; padding: 40px 20px;">
                <div style="max-width: 440px;">
                    <div class="card-icon-wrapper icon-green" style="margin: 0 auto 16px auto;">
                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 6px;">No tienes cursos registrados</h3>
                    <p style="font-size: 14px; color: var(--color-pencil-gray); margin-bottom: 16px;">
                        Comienza creando tu primer curso para compartir tus conocimientos con los alumnos.
                    </p>
                    <a href="dashboard.php?seccion=nuevo_curso" class="welcome-btn" style="display: inline-block;">
                        + Crear Nuevo Curso
                    </a>
                </div>
            </article>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Curso</th>
                            <th>Descripción</th>
                            <th style="text-align: center;">Alumnos Inscritos</th>
                            <th style="text-align: center;">Temas por Revisar</th>
                            <th style="text-align: right; min-width: 240px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $curso): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 15px; color: var(--color-charcoal);">
                                        <?= htmlspecialchars($curso['titulo']) ?>
                                    </strong>
                                    <?php if (isset($curso['estado'])): ?>
                                        <div style="margin-top: 4px;">
                                            <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; <?= ($curso['estado'] === 'Publicado') ? 'background-color: var(--color-storybook-green); color: var(--color-spark-blue);' : 'background-color: #263547; color: var(--color-pencil-gray);' ?>">
                                                <?= htmlspecialchars($curso['estado']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--color-pencil-gray); max-width: 320px; font-size: 14px; line-height: 1.4;">
                                    <?= htmlspecialchars($curso['descripcion']) ?>
                                </td>
                                <td style="text-align: center; font-weight: 800; font-size: 16px; color: var(--color-charcoal);">
                                    <?= (int)$curso['alumnos'] ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php $tPendientes = (int)$curso['revisiones_pendientes']; ?>
                                    <?php if ($tPendientes > 0): ?>
                                        <a href="dashboard.php?seccion=revisiones_temas" style="display: inline-block; background-color: #3c2d18; color: #f0b35e; border: 1px solid #a87b2c; font-weight: 800; font-size: 13px; padding: 4px 10px; border-radius: 999px; text-decoration: none;">
                                            <?= $tPendientes ?> pendientes
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--color-faded-gray); font-size: 13px; font-weight: 600;">
                                            <a href="dashboard.php?seccion=revisiones_temas" style="color: inherit; text-decoration: none;">Al día</a>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="dashboard.php?seccion=editar_temario&id=<?= (int)$curso['id'] ?>" class="action-btn" style="border-color: var(--color-spark-blue); color: var(--color-spark-blue); margin-right: 6px;">
                                        Editar Temario
                                    </a>
                                    <a href="dashboard.php?seccion=revisiones_temas" class="action-btn" style="background-color: var(--color-bg-light);">
                                        Revisar Temas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
