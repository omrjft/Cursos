<?php
//mostrar nombre y tipo de usuario en el dashboard
session_start();
require_once __DIR__ . '/database.php';

if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit;
}

$db = conect();
$usuario_id = (int)$_SESSION["id"];
$stmtCuenta = $db->prepare(
    "SELECT u.nombre, u.rol_id, u.estado, r.nombre AS rol
     FROM usuarios u
     JOIN roles r ON r.id = u.rol_id
     WHERE u.id = ?"
);
$stmtCuenta->bind_param('i', $usuario_id);
$stmtCuenta->execute();
$cuentaActual = $stmtCuenta->get_result()->fetch_assoc();

if (!$cuentaActual || $cuentaActual['estado'] !== 'Activo') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$nombre = htmlspecialchars($cuentaActual['nombre']);
$rol_id = (int)$cuentaActual['rol_id'];
$_SESSION['nombre'] = $cuentaActual['nombre'];
$_SESSION['rol_id'] = $rol_id;

// Determinar tipo de usuario según rol_id (coincidente con la tabla roles)
switch ($rol_id) {
    case 1:
        $tipo_usuario = "superadmin";
        break;
    case 2:
        $tipo_usuario = "director";
        break;
    case 3:
        $tipo_usuario = "alumno";
        break;
    case 4:
        $tipo_usuario = "ponente";
        break;
    default:
        $tipo_usuario = "usuario";
        break;
}

$esSuperadmin = ($rol_id === 1);
$esDirector = ($rol_id === 2);
$esAlumno = ($rol_id === 3);
$esPonente = ($rol_id === 4);

// Texto de la insignia en Topbar
if ($esDirector) {
    $insignia_rol = "DIRECTOR";
} elseif ($esAlumno) {
    $insignia_rol = "ALUMNO";
} else {
    $insignia_rol = strtoupper($tipo_usuario);
}

// Navegación por pestañas mediante parámetro GET (?seccion=...)
$seccion = $_GET['seccion'] ?? 'inicio';

// Restricciones de acceso estricto en el servidor:
// 1. Un alumno no tiene acceso a gestión de usuarios, creación de cursos ni acciones de docente/director/superadmin
if ($esAlumno) {
    if (in_array($seccion, ['usuarios', 'supervision_usuarios', 'reportes', 'configuracion', 'mis_alumnos', 'nuevo_curso', 'editar_temario', 'calificar_entregas'])) {
        $seccion = 'inicio';
    }
}

// 2. Un ponente no puede acceder a 'usuarios', 'supervision_usuarios', 'configuracion' ni 'reportes'
if ($esPonente) {
    if (in_array($seccion, ['usuarios', 'supervision_usuarios', 'reportes', 'configuracion'])) {
        $seccion = 'mis_alumnos';
    }
}

// 3. Un director no puede acceder a crear cursos ni editar temarios de ponente, ni configuración global
if ($esDirector) {
    if (in_array($seccion, ['nuevo_curso', 'editar_temario', 'calificar_entregas', 'mis_alumnos', 'configuracion'])) {
        $seccion = 'inicio';
    }
    if ($seccion === 'usuarios') {
        $seccion = 'supervision_usuarios';
    }
}

// 4. Solo el superadmin puede acceder a 'configuracion'
if (!$esSuperadmin && $seccion === 'configuracion') {
    $seccion = 'inicio';
}

// 5. Usuarios que no sean director no acceden a supervisión de usuarios ni reportes
if (!$esDirector) {
    if ($seccion === 'supervision_usuarios') {
        $seccion = $esPonente ? 'mis_alumnos' : ($esAlumno ? 'inicio' : 'usuarios');
    }
    if ($seccion === 'reportes') {
        $seccion = 'inicio';
    }
}

if ($esDirector && $seccion === 'reportes' && ($_GET['export'] ?? '') === 'csv') {
    $resultadoExportacion = $db->query(
        "SELECT c.titulo, COALESCE(u.nombre, 'Sin asignar') AS docente,
                (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id = c.id) AS alumnos,
                (SELECT COUNT(*)
                 FROM entregas_tareas e
                 JOIN tareas t ON t.id = e.tarea_id
                 WHERE t.curso_id = c.id AND e.calificacion IS NOT NULL) AS calificaciones,
                (SELECT COUNT(*)
                 FROM entregas_tareas e
                 JOIN tareas t ON t.id = e.tarea_id
                 WHERE t.curso_id = c.id AND e.calificacion < 7) AS reprobados,
                (SELECT AVG(e.calificacion)
                 FROM entregas_tareas e
                 JOIN tareas t ON t.id = e.tarea_id
                 WHERE t.curso_id = c.id AND e.calificacion IS NOT NULL) AS promedio
         FROM cursos c
         LEFT JOIN usuarios u ON u.id = c.docente_id
         ORDER BY c.titulo"
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte-academico.csv"');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Curso', 'Docente', 'Alumnos', 'Calificaciones', 'Reprobados', 'Tasa de reprobacion', 'Promedio general']);
    while ($fila = $resultadoExportacion->fetch_assoc()) {
        $totalCalificaciones = (int)$fila['calificaciones'];
        $valoresTexto = [$fila['titulo'], $fila['docente']];
        foreach ($valoresTexto as &$valorTexto) {
            if (preg_match('/^[=+\-@]/', (string)$valorTexto)) {
                $valorTexto = "'" . $valorTexto;
            }
        }
        unset($valorTexto);
        fputcsv($salida, [
            $valoresTexto[0],
            $valoresTexto[1],
            (int)$fila['alumnos'],
            $totalCalificaciones,
            (int)$fila['reprobados'],
            $totalCalificaciones > 0 ? round((int)$fila['reprobados'] * 100 / $totalCalificaciones, 1) . '%' : '—',
            $fila['promedio'] === null ? '' : number_format((float)$fila['promedio'], 1)
        ]);
    }
    fclose($salida);
    exit;
}

// Mapa de nombres para migas de pan según rol
$titulos_secciones = [
    'inicio' => 'Dashboard',
    'usuarios' => 'Gestión de Usuarios',
    'supervision_usuarios' => 'Supervisión de Usuarios',
    'cursos' => $esSuperadmin ? 'Gestión Global de Cursos' : ($esDirector ? 'Catálogo de Cursos' : 'Mis Cursos'),
    'reportes' => 'Reportes Académicos',
    'configuracion' => 'Configuración del Sistema',
    'curso_detalle' => 'Detalle del Curso',
    'mis_alumnos' => 'Mis Alumnos',
    'editar_temario' => 'Editar Temario',
    'calificar_entregas' => 'Calificar Entregas',
    'nuevo_curso' => 'Crear Curso',
    'perfil' => 'Mi Perfil'
];
$seccion_actual_nombre = $titulos_secciones[$seccion] ?? 'Dashboard';

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$mensajeAccion = '';

if (isset($_SESSION['flash_message'])) {
    $mensajeAccion = (string)$_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenEnviado) || !hash_equals($csrfToken, $tokenEnviado)) {
        http_response_code(400);
        $mensajeAccion = 'La solicitud expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? $_POST['accion_ponente'] ?? '';

        if ($accion === 'crear_usuario' && $esSuperadmin) {
            $nombreEntrada = $_POST['nombre'] ?? '';
            $emailEntrada = $_POST['email'] ?? '';
            $nombreNuevo = is_string($nombreEntrada) ? trim($nombreEntrada) : '';
            $emailNuevo = is_string($emailEntrada) ? trim($emailEntrada) : '';
            $passwordNuevo = $_POST['password'] ?? '';
            $rolNuevo = filter_var($_POST['rol_id'] ?? null, FILTER_VALIDATE_INT);

            if (
                $nombreNuevo === ''
                || strlen($nombreNuevo) > 100
                || !filter_var($emailNuevo, FILTER_VALIDATE_EMAIL)
                || strlen($emailNuevo) > 150
                || !is_string($passwordNuevo)
                || strlen($passwordNuevo) < 10
                || $rolNuevo === false
            ) {
                $mensajeAccion = 'Revisa los datos. El nombre, correo y rol son obligatorios; la contraseña debe tener al menos 10 caracteres.';
            } else {
                $stmtRol = $db->prepare('SELECT id FROM roles WHERE id = ?');
                $stmtRol->bind_param('i', $rolNuevo);
                $stmtRol->execute();
                if (!$stmtRol->get_result()->fetch_assoc()) {
                    $mensajeAccion = 'El rol seleccionado no existe.';
                } else {
                    $stmtExiste = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
                    $stmtExiste->bind_param('s', $emailNuevo);
                    $stmtExiste->execute();
                    if ($stmtExiste->get_result()->fetch_assoc()) {
                        $mensajeAccion = 'Ya existe una cuenta con ese correo.';
                    } else {
                        $hashNuevo = password_hash($passwordNuevo, PASSWORD_DEFAULT);
                        $stmtCrear = $db->prepare(
                            "INSERT INTO usuarios (rol_id, nombre, email, password_hash, estado)
                             VALUES (?, ?, ?, ?, 'Activo')"
                        );
                        $stmtCrear->bind_param('isss', $rolNuevo, $nombreNuevo, $emailNuevo, $hashNuevo);
                        $stmtCrear->execute();
                        $_SESSION['flash_message'] = 'Usuario creado correctamente.';
                        header('Location: dashboard.php?seccion=usuarios');
                        exit;
                    }
                }
            }
        } elseif ($accion === 'cambiar_estado_usuario' && $esSuperadmin) {
            $usuarioObjetivo = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
            $nuevoEstado = $_POST['estado'] ?? '';
            if ($usuarioObjetivo === false || !in_array($nuevoEstado, ['Activo', 'Inactivo'], true)) {
                $mensajeAccion = 'No se pudo validar el cambio de estado del usuario.';
            } else {
                $stmtEstado = $db->prepare('UPDATE usuarios SET estado = ? WHERE id = ?');
                $stmtEstado->bind_param('si', $nuevoEstado, $usuarioObjetivo);
                $stmtEstado->execute();
                $_SESSION['flash_message'] = 'Estado del usuario actualizado.';
                header('Location: dashboard.php?seccion=usuarios');
                exit;
            }
        } elseif ($accion === 'crear_curso' && $esPonente) {
            $tituloEntrada = $_POST['titulo'] ?? '';
            $descripcionEntrada = $_POST['descripcion'] ?? '';
            $tituloNuevo = is_string($tituloEntrada) ? trim($tituloEntrada) : '';
            $descripcionNueva = is_string($descripcionEntrada) ? trim($descripcionEntrada) : '';
            if ($tituloNuevo === '' || strlen($tituloNuevo) > 255) {
                $mensajeAccion = 'El título es obligatorio y no debe exceder 255 caracteres.';
            } else {
                $stmtCrearCurso = $db->prepare(
                    "INSERT INTO cursos (docente_id, titulo, descripcion, estado)
                     VALUES (?, ?, ?, 'Borrador')"
                );
                $stmtCrearCurso->bind_param('iss', $usuario_id, $tituloNuevo, $descripcionNueva);
                $stmtCrearCurso->execute();
                $_SESSION['flash_message'] = 'Curso creado en estado Borrador.';
                header('Location: dashboard.php?seccion=cursos');
                exit;
            }
        } elseif ($accion === 'actualizar_estado_curso' && $esSuperadmin) {
            $cursoObjetivo = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);
            if ($cursoObjetivo === false) {
                $mensajeAccion = 'No se pudo validar el curso.';
            } else {
                $stmtPublicar = $db->prepare(
                    "UPDATE cursos SET estado = 'Publicado'
                     WHERE id = ? AND estado = 'Borrador'"
                );
                $stmtPublicar->bind_param('i', $cursoObjetivo);
                $stmtPublicar->execute();
                if ($stmtPublicar->affected_rows === 0) {
                    $mensajeAccion = 'No se encontró el curso o ya está publicado.';
                } else {
                    $_SESSION['flash_message'] = 'El curso fue publicado.';
                    header('Location: dashboard.php?seccion=cursos');
                    exit;
                }
            }
        } elseif ($accion === 'reasignar_docente' && $esSuperadmin) {
            $cursoObjetivo = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);
            $docenteObjetivo = filter_var($_POST['docente_id'] ?? null, FILTER_VALIDATE_INT);
            if ($cursoObjetivo === false || $docenteObjetivo === false) {
                $mensajeAccion = 'Selecciona un curso y un docente válidos.';
            } else {
                $stmtEsPonente = $db->prepare(
                    "SELECT u.id FROM usuarios u
                     JOIN roles r ON r.id = u.rol_id
                     WHERE u.id = ? AND r.nombre = 'ponente'"
                );
                $stmtEsPonente->bind_param('i', $docenteObjetivo);
                $stmtEsPonente->execute();
                if (!$stmtEsPonente->get_result()->fetch_assoc()) {
                    $mensajeAccion = 'El usuario seleccionado no tiene rol de ponente.';
                } else {
                    $stmtCursoExiste = $db->prepare('SELECT id FROM cursos WHERE id = ?');
                    $stmtCursoExiste->bind_param('i', $cursoObjetivo);
                    $stmtCursoExiste->execute();
                    if (!$stmtCursoExiste->get_result()->fetch_assoc()) {
                        $mensajeAccion = 'No se encontró el curso seleccionado.';
                    } else {
                        $stmtAsignar = $db->prepare('UPDATE cursos SET docente_id = ? WHERE id = ?');
                        $stmtAsignar->bind_param('ii', $docenteObjetivo, $cursoObjetivo);
                        $stmtAsignar->execute();
                        $_SESSION['flash_message'] = 'Docente asignado correctamente.';
                        header('Location: dashboard.php?seccion=cursos');
                        exit;
                    }
                }
            }
        } elseif (in_array($accion, ['agregar_bloque', 'agregar_tema', 'calificar_entrega'], true) && $esPonente) {
            $cursoObjetivo = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);
            $stmtPropiedadCurso = $db->prepare('SELECT id FROM cursos WHERE id = ? AND docente_id = ?');
            if ($cursoObjetivo !== false) {
                $stmtPropiedadCurso->bind_param('ii', $cursoObjetivo, $usuario_id);
                $stmtPropiedadCurso->execute();
            }

            if ($cursoObjetivo === false || !$stmtPropiedadCurso->get_result()->fetch_assoc()) {
                http_response_code(403);
                $mensajeAccion = 'No tienes permiso para modificar este curso.';
            } elseif ($accion === 'agregar_bloque') {
                $tituloEntrada = $_POST['titulo'] ?? '';
                $tituloBloque = is_string($tituloEntrada) ? trim($tituloEntrada) : '';
                if ($tituloBloque === '' || strlen($tituloBloque) > 255) {
                    $mensajeAccion = 'El título del bloque es obligatorio y no debe exceder 255 caracteres.';
                } else {
                    $stmtOrden = $db->prepare('SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM bloques WHERE curso_id = ?');
                    $stmtOrden->bind_param('i', $cursoObjetivo);
                    $stmtOrden->execute();
                    $ordenBloque = (int)$stmtOrden->get_result()->fetch_assoc()['siguiente'];
                    $stmtNuevoBloque = $db->prepare('INSERT INTO bloques (curso_id, titulo, orden) VALUES (?, ?, ?)');
                    $stmtNuevoBloque->bind_param('isi', $cursoObjetivo, $tituloBloque, $ordenBloque);
                    $stmtNuevoBloque->execute();
                    $_SESSION['flash_message'] = 'Bloque agregado correctamente.';
                    header('Location: dashboard.php?seccion=editar_temario&id=' . $cursoObjetivo);
                    exit;
                }
            } elseif ($accion === 'agregar_tema') {
                $bloqueObjetivo = filter_var($_POST['bloque_id'] ?? null, FILTER_VALIDATE_INT);
                $tituloEntrada = $_POST['titulo'] ?? '';
                $tituloTema = is_string($tituloEntrada) ? trim($tituloEntrada) : '';
                if ($bloqueObjetivo === false || $tituloTema === '' || strlen($tituloTema) > 255) {
                    $mensajeAccion = 'Selecciona un bloque e indica un título de tema válido.';
                } else {
                    $stmtBloque = $db->prepare('SELECT id FROM bloques WHERE id = ? AND curso_id = ?');
                    $stmtBloque->bind_param('ii', $bloqueObjetivo, $cursoObjetivo);
                    $stmtBloque->execute();
                    if (!$stmtBloque->get_result()->fetch_assoc()) {
                        http_response_code(403);
                        $mensajeAccion = 'El bloque no pertenece a este curso.';
                    } else {
                        $stmtOrden = $db->prepare('SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM temas WHERE bloque_id = ?');
                        $stmtOrden->bind_param('i', $bloqueObjetivo);
                        $stmtOrden->execute();
                        $ordenTema = (int)$stmtOrden->get_result()->fetch_assoc()['siguiente'];
                        $stmtNuevoTema = $db->prepare('INSERT INTO temas (bloque_id, titulo, orden) VALUES (?, ?, ?)');
                        $stmtNuevoTema->bind_param('isi', $bloqueObjetivo, $tituloTema, $ordenTema);
                        $stmtNuevoTema->execute();
                        $_SESSION['flash_message'] = 'Tema agregado correctamente.';
                        header('Location: dashboard.php?seccion=editar_temario&id=' . $cursoObjetivo);
                        exit;
                    }
                }
            } else {
                $entregaObjetivo = filter_var($_POST['entrega_id'] ?? null, FILTER_VALIDATE_INT);
                $calificacionEntrada = $_POST['calificacion'] ?? null;
                $calificacion = is_string($calificacionEntrada) && is_numeric($calificacionEntrada)
                    ? (float)$calificacionEntrada
                    : null;
                if (
                    $entregaObjetivo === false
                    || $calificacion === null
                    || !is_finite($calificacion)
                    || $calificacion < 0
                    || $calificacion > 10
                ) {
                    $mensajeAccion = 'La calificación debe ser un número entre 0 y 10.';
                } else {
                    $stmtCalificar = $db->prepare(
                        "UPDATE entregas_tareas e
                         JOIN tareas t ON t.id = e.tarea_id
                         SET e.calificacion = ?
                         WHERE e.id = ? AND t.curso_id = ? AND e.calificacion IS NULL"
                    );
                    $stmtCalificar->bind_param('dii', $calificacion, $entregaObjetivo, $cursoObjetivo);
                    $stmtCalificar->execute();
                    if ($stmtCalificar->affected_rows === 0) {
                        $mensajeAccion = 'No se encontró una entrega pendiente para calificar.';
                    } else {
                        $_SESSION['flash_message'] = 'Calificación guardada correctamente.';
                        header('Location: dashboard.php?seccion=calificar_entregas&id=' . $cursoObjetivo);
                        exit;
                    }
                }
            }
        } elseif ($accion === 'actualizar_progreso_tema' && $esAlumno) {
            $cursoObjetivo = filter_var($_POST['curso_id'] ?? null, FILTER_VALIDATE_INT);
            $temaObjetivo = filter_var($_POST['tema_id'] ?? null, FILTER_VALIDATE_INT);
            $completado = ($_POST['completado'] ?? '') === '1';

            if ($cursoObjetivo === false || $temaObjetivo === false) {
                $mensajeAccion = 'No se pudo validar el tema seleccionado.';
            } else {
                $stmtValidacion = $db->prepare(
                    'SELECT
                        EXISTS(
                            SELECT 1 FROM matriculas
                            WHERE curso_id = ? AND usuario_id = ?
                        ) AS matricula_valida,
                        EXISTS(
                            SELECT 1
                            FROM temas t
                            JOIN bloques b ON b.id = t.bloque_id
                            WHERE t.id = ? AND b.curso_id = ?
                        ) AS tema_valido'
                );
                $stmtValidacion->bind_param(
                    'iiii',
                    $cursoObjetivo,
                    $usuario_id,
                    $temaObjetivo,
                    $cursoObjetivo
                );
                $stmtValidacion->execute();
                $resultadoValidacion = $stmtValidacion->get_result();
                $validacion = $resultadoValidacion->fetch_assoc();
                $resultadoValidacion->free();
                $stmtValidacion->close();
                $matriculaValida = (bool)$validacion['matricula_valida'];
                $temaValido = (bool)$validacion['tema_valido'];

                if (!$matriculaValida || !$temaValido) {
                    http_response_code(403);
                    $mensajeAccion = 'No tienes permiso para modificar el avance de este tema.';
                } else {
                    try {
                        $db->begin_transaction();

                        if ($completado) {
                            $stmtCompletar = $db->prepare(
                                'INSERT IGNORE INTO progreso_temas (usuario_id, tema_id) VALUES (?, ?)'
                            );
                            $stmtCompletar->bind_param('ii', $usuario_id, $temaObjetivo);
                            $stmtCompletar->execute();
                        } else {
                            $stmtDesmarcar = $db->prepare(
                                'DELETE FROM progreso_temas WHERE usuario_id = ? AND tema_id = ?'
                            );
                            $stmtDesmarcar->bind_param('ii', $usuario_id, $temaObjetivo);
                            $stmtDesmarcar->execute();
                        }

                        $stmtAvance = $db->prepare(
                            "UPDATE matriculas m
                             SET progreso_global = (
                                SELECT CASE WHEN COUNT(DISTINCT t.id) = 0 THEN 0
                                    ELSE ROUND(COUNT(DISTINCT pt.tema_id) * 100 / COUNT(DISTINCT t.id), 2)
                                END
                                FROM cursos c
                                LEFT JOIN bloques b ON b.curso_id = c.id
                                LEFT JOIN temas t ON t.bloque_id = b.id
                                LEFT JOIN progreso_temas pt
                                  ON pt.tema_id = t.id AND pt.usuario_id = m.usuario_id
                                WHERE c.id = m.curso_id
                             )
                             WHERE m.usuario_id = ? AND m.curso_id = ?"
                        );
                        $stmtAvance->bind_param('ii', $usuario_id, $cursoObjetivo);
                        $stmtAvance->execute();
                        $db->commit();

                        $_SESSION['flash_message'] = 'Tu avance se guardó.';
                        header('Location: dashboard.php?seccion=curso_detalle&id=' . $cursoObjetivo);
                        exit;
                    } catch (mysqli_sql_exception $exception) {
                        $db->rollback();
                        error_log(
                            'Error al actualizar progreso del alumno '
                            . $usuario_id
                            . ' en el curso '
                            . $cursoObjetivo
                            . ': '
                            . $exception->getMessage()
                        );
                        $mensajeAccion = 'No se pudo guardar tu avance. Inténtalo de nuevo y, si el problema continúa, contacta al administrador.';
                    }
                }
            }
        } else {
            http_response_code(403);
            $mensajeAccion = 'No tienes permiso para realizar esta acción.';
        }
    }
}

// ==========================================
// MÉTRICAS PARA DASHBOARD DEL SUPERADMIN Y DIRECTOR
// ==========================================
$metricas_superadmin = [
    'usuarios' => 0,
    'cursos_publicados' => 0,
    'registros_hoy' => 0
];
$metricas_director = [
    'alumnos_activos' => 0,
    'cursos_imparticion' => 0,
    'promedio_aprobacion' => '0%'
];

if (($esSuperadmin || $esDirector) && $seccion === 'inicio') {
    $resultadoResumen = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
            (SELECT COUNT(*) FROM cursos WHERE estado = 'Publicado') AS cursos_publicados,
            (SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = CURRENT_DATE()) AS registros_hoy,
            (SELECT COUNT(*) FROM usuarios WHERE rol_id = 3 AND estado = 'Activo') AS alumnos_activos,
            (SELECT COUNT(*) FROM cursos WHERE estado = 'Publicado') AS cursos_imparticion,
            (SELECT
                CASE WHEN COUNT(*) = 0 THEN 0
                     ELSE ROUND(SUM(CASE WHEN calificacion >= 7 THEN 1 ELSE 0 END) * 100 / COUNT(*), 1)
                END
             FROM entregas_tareas WHERE calificacion IS NOT NULL) AS promedio_aprobacion"
    );
    $resumen = $resultadoResumen->fetch_assoc();

    $metricas_superadmin = [
        'usuarios' => (int)$resumen['total_usuarios'],
        'cursos_publicados' => (int)$resumen['cursos_publicados'],
        'registros_hoy' => (int)$resumen['registros_hoy']
    ];
    $metricas_director = [
        'alumnos_activos' => (int)$resumen['alumnos_activos'],
        'cursos_imparticion' => (int)$resumen['cursos_imparticion'],
        'promedio_aprobacion' => number_format((float)$resumen['promedio_aprobacion'], 1) . '%'
    ];
}

// ==========================================
// MÉTRICAS PARA DASHBOARD DEL ALUMNO
// ==========================================
$metricas_alumno = [
    'cursos_progreso' => 0,
    'cursos_completados' => 0,
    'proxima_tarea' => 'No tienes tareas pendientes'
];

if ($esAlumno && $seccion === 'inicio') {
    $stmtC = $db->prepare(
        "SELECT
            SUM(CASE WHEN avance.total_temas > 0
                      AND avance.temas_completados = avance.total_temas
                     THEN 1 ELSE 0 END) AS completados,
            SUM(CASE WHEN avance.total_temas = 0
                      OR avance.temas_completados < avance.total_temas
                     THEN 1 ELSE 0 END) AS en_progreso
         FROM (
            SELECT m.curso_id,
                   COUNT(DISTINCT t.id) AS total_temas,
                   COUNT(DISTINCT pt.tema_id) AS temas_completados
            FROM matriculas m
            LEFT JOIN bloques b ON b.curso_id = m.curso_id
            LEFT JOIN temas t ON t.bloque_id = b.id
            LEFT JOIN progreso_temas pt
              ON pt.tema_id = t.id AND pt.usuario_id = m.usuario_id
            WHERE m.usuario_id = ?
            GROUP BY m.curso_id
         ) AS avance"
    );
    $stmtC->bind_param("i", $usuario_id);
    $stmtC->execute();
    $resMet = $stmtC->get_result()->fetch_assoc();

    if ($resMet && ($resMet['en_progreso'] !== null || $resMet['completados'] !== null)) {
        $metricas_alumno['cursos_progreso'] = (int)($resMet['en_progreso'] ?? 0);
        $metricas_alumno['cursos_completados'] = (int)($resMet['completados'] ?? 0);
    }

    $stmtT = $db->prepare("SELECT t.titulo, t.fecha_limite, c.titulo as curso
        FROM tareas t
        JOIN matriculas m ON t.curso_id = m.curso_id
        JOIN cursos c ON t.curso_id = c.id
        LEFT JOIN entregas_tareas e ON t.id = e.tarea_id AND e.usuario_id = m.usuario_id
        WHERE m.usuario_id = ? AND e.id IS NULL
        ORDER BY t.fecha_limite ASC
        LIMIT 1");
    $stmtT->bind_param("i", $usuario_id);
    $stmtT->execute();
    $resT = $stmtT->get_result()->fetch_assoc();

    if ($resT) {
        $metricas_alumno['proxima_tarea'] = $resT['titulo'];
    }
}

// ==========================================
// MÉTRICAS PARA DASHBOARD DEL PONENTE
// ==========================================
$metricas_ponente = [
    'cursos_activos' => 0,
    'total_alumnos' => 0,
    'tareas_pendientes' => 0
];

if ($esPonente && $seccion === 'inicio') {
    $stmtCA = $db->prepare("SELECT COUNT(*) FROM cursos WHERE docente_id = ? AND estado = 'Publicado'");
    $stmtCA->bind_param("i", $usuario_id);
    $stmtCA->execute();
    $metricas_ponente['cursos_activos'] = (int)$stmtCA->get_result()->fetch_row()[0];

    $stmtTA = $db->prepare("SELECT COUNT(DISTINCT m.usuario_id) FROM matriculas m JOIN cursos c ON m.curso_id = c.id WHERE c.docente_id = ?");
    $stmtTA->bind_param("i", $usuario_id);
    $stmtTA->execute();
    $metricas_ponente['total_alumnos'] = (int)$stmtTA->get_result()->fetch_row()[0];

    $stmtTP = $db->prepare("SELECT COUNT(*) FROM entregas_tareas e JOIN tareas t ON e.tarea_id = t.id JOIN cursos c ON t.curso_id = c.id WHERE c.docente_id = ? AND e.calificacion IS NULL");
    $stmtTP->bind_param("i", $usuario_id);
    $stmtTP->execute();
    $metricas_ponente['tareas_pendientes'] = (int)$stmtTP->get_result()->fetch_row()[0];
}

$stmtP = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
$stmtP->bind_param('i', $usuario_id);
$stmtP->execute();
$usuarioData = $stmtP->get_result()->fetch_assoc();
$email_usuario = htmlspecialchars($usuarioData['email'] ?? '—');
$tipo_usuario = htmlspecialchars($cuentaActual['rol']);

$ponentes = [];
if ($esSuperadmin) {
    $resultadoPonentes = $db->query(
        "SELECT u.id, u.nombre
         FROM usuarios u
         JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'ponente' AND u.estado = 'Activo'
         ORDER BY u.nombre"
    );
    while ($ponente = $resultadoPonentes->fetch_assoc()) {
        $ponentes[] = $ponente;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Plataforma de Cursos</title>
    <!-- Google Fonts: Nunito -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Colores de DESIGN.md */
            --color-eager-green: #58cc02;
            --color-storybook-green: #d7ffb8;
            --color-spark-blue: #1cb0f6;
            --color-fresh-leaf: #a5ed6e;
            --color-night-ink: #000437;
            --color-paper-white: #ffffff;
            --color-charcoal: #4b4b4b;
            --color-pencil-gray: #777777;
            --color-faded-gray: #afafaf;
            --color-border-gray: #e5e5e5;
            --color-bg-light: #f7f9fa;
            --radius-btn: 12px;
            --radius-card: 16px;
            --font-family: 'Nunito', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--color-paper-white);
            color: var(--color-charcoal);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* 1. Header / Topbar */
        .topbar {
            background-color: var(--color-paper-white);
            border-bottom: 2px solid var(--color-border-gray);
            height: 72px;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--color-eager-green);
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .brand-logo svg {
            width: 28px;
            height: 28px;
            fill: var(--color-eager-green);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-greeting {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: var(--color-bg-light);
            border: 2px solid var(--color-border-gray);
            padding: 6px 14px;
            border-radius: var(--radius-btn);
            font-size: 14px;
            font-weight: 700;
            color: var(--color-charcoal);
        }

        .user-greeting .role-badge {
            background-color: var(--color-storybook-green);
            color: #2e7a00;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 999px;
            letter-spacing: 0.05em;
        }

        .notifications-btn {
            position: relative;
            background: transparent;
            border: 2px solid var(--color-border-gray);
            border-radius: var(--radius-btn);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--color-pencil-gray);
            transition: all 0.15s ease;
        }

        .notifications-btn:hover {
            color: var(--color-charcoal);
            border-color: var(--color-faded-gray);
            background-color: var(--color-bg-light);
        }

        .notifications-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 9px;
            height: 9px;
            background-color: #ea2b2b;
            border-radius: 50%;
            border: 2px solid var(--color-paper-white);
        }

        .btn-logout {
            text-decoration: none;
            color: #ea2b2b;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px 16px;
            border-radius: var(--radius-btn);
            border: 2px solid #ea2b2b;
            background: transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .btn-logout:hover {
            background-color: #ffebee;
        }

        /* Layout contenedor del Dashboard */
        .dashboard-container {
            display: flex;
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* 2. Menú de Navegación Lateral (Sidebar adaptado) */
        .sidebar {
            width: 260px;
            background-color: var(--color-paper-white);
            border-right: 2px solid var(--color-border-gray);
            padding: 32px 18px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .sidebar-section-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--color-pencil-gray);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0 12px 8px 12px;
        }

        .sidebar-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-btn);
            color: var(--color-pencil-gray);
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            border: 2px solid transparent;
            transition: all 0.15s ease;
        }

        .sidebar-link svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .sidebar-link:hover {
            background-color: var(--color-bg-light);
            color: var(--color-charcoal);
        }

        .sidebar-link.active {
            background-color: var(--color-storybook-green);
            color: #225a00;
            border-color: var(--color-eager-green);
        }

        .sidebar-link.active svg {
            fill: #225a00;
        }

        /* 3. Área Principal de Contenido (Main Content) */
        .main-content {
            flex: 1;
            padding: 32px 40px;
            background-color: var(--color-paper-white);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Migas de pan (Breadcrumbs) */
        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--color-pencil-gray);
        }

        .breadcrumbs a {
            color: var(--color-spark-blue);
            text-decoration: none;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
        }

        .breadcrumbs .separator {
            color: var(--color-faded-gray);
            font-weight: 900;
        }

        .breadcrumbs .current {
            color: var(--color-charcoal);
        }

        /* Tarjetas de Resumen (Widgets / Cards) */
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin: 12px 0 24px 0;
        }

        .summary-card {
            background-color: var(--color-paper-white);
            border: 2px solid var(--color-faded-gray);
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: 0 4px 0 #e5e5e5;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: transform 0.1s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
        }

        .card-icon-wrapper {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-btn);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-green {
            background-color: var(--color-storybook-green);
            color: var(--color-eager-green);
        }

        .icon-blue {
            background-color: #e0f4ff;
            color: var(--color-spark-blue);
        }

        .icon-dark {
            background-color: #f3e8ff;
            color: #8b5cf6;
        }

        .icon-orange {
            background-color: #fff4e5;
            color: #d97706;
        }

        .card-icon-wrapper svg {
            width: 28px;
            height: 28px;
            fill: currentColor;
        }

        .card-data {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .card-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-pencil-gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card-value {
            font-size: 32px;
            font-weight: 900;
            color: var(--color-charcoal);
            line-height: 1.1;
        }

        /* Banner de Bienvenida */
        .welcome-banner {
            background: linear-gradient(135deg, #f7fdf0 0%, #edf9e2 100%);
            border: 2px solid var(--color-fresh-leaf);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .welcome-text h2 {
            font-size: 26px;
            font-weight: 900;
            color: var(--color-charcoal);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .welcome-text p {
            font-size: 15px;
            color: var(--color-pencil-gray);
            font-weight: 600;
        }

        .welcome-btn {
            background-color: var(--color-eager-green);
            color: var(--color-paper-white);
            text-decoration: none;
            padding: 12px 22px;
            border-radius: var(--radius-btn);
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 0 #46a302;
            white-space: nowrap;
            transition: all 0.1s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .welcome-btn:hover {
            filter: brightness(1.05);
        }

        /* Tablas */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 2px solid var(--color-border-gray);
            border-radius: var(--radius-card);
            overflow: hidden;
            margin-top: 16px;
        }

        th {
            background-color: var(--color-bg-light);
            color: var(--color-pencil-gray);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 18px;
            text-align: left;
            border-bottom: 2px solid var(--color-border-gray);
        }

        td {
            padding: 14px 18px;
            font-size: 15px;
            color: var(--color-charcoal);
            border-bottom: 1px solid var(--color-border-gray);
        }

        tr:last-child td {
            border-bottom: none;
        }

        button, .action-btn {
            font-family: var(--font-family);
            border-radius: var(--radius-btn);
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid var(--color-border-gray);
            background-color: var(--color-paper-white);
            color: var(--color-charcoal);
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-block;
        }

        button:hover, .action-btn:hover {
            border-color: var(--color-spark-blue);
            color: var(--color-spark-blue);
        }

        /* 4. Pie de Página (Footer) */
        .site-footer {
            background-color: var(--color-eager-green);
            color: var(--color-paper-white);
            padding: 24px 32px;
            margin-top: auto;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
        }

        .site-footer p {
            color: var(--color-storybook-green);
        }

        .site-footer span {
            color: var(--color-paper-white);
        }

        /* Responsive */
        @media (max-width: 860px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 2px solid var(--color-border-gray);
                padding: 16px;
            }

            .sidebar-menu {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .main-content {
                padding: 20px 16px;
            }

            .topbar {
                padding: 0 16px;
            }

            .welcome-banner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Barra Superior (Topbar) -->
    <header class="topbar">
        <div class="topbar-left">
            <a href="dashboard.php" class="brand-logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                </svg>
                Cursos Online
            </a>
        </div>

        <div class="topbar-right">
            <!-- Saludo con identificador de rol -->
            <div class="user-greeting">
                <p>Hola <strong><?= $nombre ?></strong>, eres <span class="role-badge"><?= $insignia_rol ?></span></p>
            </div>

            <!-- Enlace de Cerrar Sesión -->
            <a href="logout.php" class="btn-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                Cerrar Sesión
            </a>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- 2. Menú de Navegación Lateral (Sidebar adaptado) -->
        <aside class="sidebar">
            <nav>
                <div class="sidebar-section-title">Menú Principal</div>
                <ul class="sidebar-menu">
                    <!-- Dashboard -->
                    <li>
                        <a href="dashboard.php?seccion=inicio" class="sidebar-link <?= ($seccion === 'inicio') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                            Dashboard
                        </a>
                    </li>

                    <?php if ($esSuperadmin): ?>
                        <!-- 1. Menú de Navegación Lateral Superadmin -->
                        <li>
                            <a href="dashboard.php?seccion=usuarios" class="sidebar-link <?= ($seccion === 'usuarios') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                Gestión de Usuarios
                            </a>
                        </li>
                        <li>
                            <!-- Renombrado: "Gestión Global de Cursos" en lugar de "Mis Cursos" -->
                            <a href="dashboard.php?seccion=cursos" class="sidebar-link <?= ($seccion === 'cursos' || $seccion === 'curso_detalle') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                Gestión Global de Cursos
                            </a>
                        </li>

                    <?php elseif ($esDirector): ?>
                        <!-- Sidebar para Director -->
                        <li>
                            <a href="dashboard.php?seccion=supervision_usuarios" class="sidebar-link <?= ($seccion === 'supervision_usuarios') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                Supervisión de Usuarios
                            </a>
                        </li>
                        <li>
                            <a href="dashboard.php?seccion=cursos" class="sidebar-link <?= ($seccion === 'cursos' || $seccion === 'curso_detalle') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                Catálogo de Cursos
                            </a>
                        </li>
                        <li>
                            <a href="dashboard.php?seccion=reportes" class="sidebar-link <?= ($seccion === 'reportes') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                                Reportes Académicos
                            </a>
                        </li>

                    <?php elseif ($esPonente): ?>
                        <!-- Sidebar para Ponente -->
                        <li>
                            <a href="dashboard.php?seccion=cursos" class="sidebar-link <?= ($seccion === 'cursos' || $seccion === 'curso_detalle' || $seccion === 'editar_temario' || $seccion === 'calificar_entregas' || $seccion === 'nuevo_curso') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                Mis Cursos
                            </a>
                        </li>
                        <li>
                            <a href="dashboard.php?seccion=mis_alumnos" class="sidebar-link <?= ($seccion === 'mis_alumnos') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                Mis Alumnos
                            </a>
                        </li>

                    <?php elseif ($esAlumno): ?>
                        <!-- Sidebar para Alumno -->
                        <li>
                            <a href="dashboard.php?seccion=cursos" class="sidebar-link <?= ($seccion === 'cursos' || $seccion === 'curso_detalle') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                Mis Cursos
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Sección Cuenta / Sistema: Separación para Superadmin -->
                <div class="sidebar-section-title" style="margin-top: 24px;">
                    <?= $esSuperadmin ? 'Administración y Cuenta' : 'Cuenta' ?>
                </div>
                <ul class="sidebar-menu">
                    <li>
                        <!-- Enlace "Mi Perfil" (Datos personales) -->
                        <a href="dashboard.php?seccion=perfil" class="sidebar-link <?= ($seccion === 'perfil') ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Mi Perfil
                        </a>
                    </li>

                    <?php if ($esSuperadmin): ?>
                        <!-- Enlace "Configuración del Sistema" (Ajustes globales de la plataforma) -->
                        <li>
                            <a href="dashboard.php?seccion=configuracion" class="sidebar-link <?= ($seccion === 'configuracion') ? 'active' : '' ?>">
                                <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                                Configuración del Sistema
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- 3. Área Principal de Contenido (Main Content) -->
        <main class="main-content">
            <!-- Migas de pan (Breadcrumbs) -->
            <nav class="breadcrumbs" aria-label="Migas de pan">
                <a href="dashboard.php?seccion=inicio">Inicio</a>
                <span class="separator">&gt;</span>
                <span class="current"><?= htmlspecialchars($seccion_actual_nombre) ?></span>
            </nav>
            <?php if ($mensajeAccion !== ''): ?>
                <div class="summary-card" role="status" style="display: block; margin-bottom: 20px;">
                    <?= htmlspecialchars($mensajeAccion) ?>
                </div>
            <?php endif; ?>

            <?php
            switch ($seccion) {
                case 'configuracion':
                    require __DIR__ . '/vistas/configuracion.php';
                    break;

                case 'supervision_usuarios':
                    require __DIR__ . '/vistas/supervision_usuarios.php';
                    break;

                case 'reportes':
                    require __DIR__ . '/vistas/reportes_academicos.php';
                    break;

                case 'usuarios':
                    if ($esPonente) {
                        require __DIR__ . '/vistas/mis_alumnos.php';
                    } elseif ($esDirector) {
                        require __DIR__ . '/vistas/supervision_usuarios.php';
                    } elseif (!$esAlumno) {
                        require __DIR__ . '/vistas/usuarios.php';
                    }
                    break;

                case 'mis_alumnos':
                    if ($esPonente) {
                        require __DIR__ . '/vistas/mis_alumnos.php';
                    }
                    break;

                case 'cursos':
                    require __DIR__ . '/vistas/cursos.php';
                    break;

                case 'curso_detalle':
                    require __DIR__ . '/vistas/curso_detalle.php';
                    break;

                case 'editar_temario':
                case 'calificar_entregas':
                    if ($esPonente) {
                        require __DIR__ . '/vistas/ponente_acciones.php';
                    }
                    break;

                case 'nuevo_curso':
                    if ($esPonente) {
                        ?>
                        <section>
                            <a href="dashboard.php?seccion=cursos" style="color: var(--color-spark-blue); text-decoration: none; font-weight: 700; font-size: 14px;">
                                &larr; Volver a Mis Cursos
                            </a>
                            <h2 style="font-size: 26px; font-weight: 900; color: var(--color-charcoal); margin: 8px 0 16px 0;">Crear Nuevo Curso</h2>

                            <article class="summary-card" style="display: block; max-width: 680px;">
                                <form method="POST" action="dashboard.php?seccion=nuevo_curso">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="accion" value="crear_curso">
                                    <div style="margin-bottom: 18px;">
                                        <label style="display: block; font-size: 13px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">Título del Curso</label>
                                        <input type="text" name="titulo" maxlength="255" style="width: 100%; height: 46px; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 0 16px; font-family: var(--font-family); font-size: 15px;" required>
                                    </div>
                                    <div style="margin-bottom: 18px;">
                                        <label style="display: block; font-size: 13px; font-weight: 800; color: var(--color-charcoal); text-transform: uppercase; margin-bottom: 6px;">Descripción del Curso</label>
                                        <textarea name="descripcion" rows="4" style="width: 100%; border: 2px solid var(--color-border-gray); border-radius: var(--radius-btn); padding: 12px 16px; font-family: var(--font-family); font-size: 15px; resize: vertical;"></textarea>
                                    </div>
                                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                        <a href="dashboard.php?seccion=cursos" class="action-btn">Cancelar</a>
                                        <button type="submit" class="welcome-btn">Guardar y Continuar</button>
                                    </div>
                                </form>
                            </article>
                        </section>
                        <?php
                    }
                    break;

                case 'perfil':
                    ?>
                    <!-- Vista Mi Perfil (Datos personales) -->
                    <section>
                        <h2>Mi Perfil</h2>
                        <p style="color: var(--color-pencil-gray); margin-top: 8px;">Detalles personales y credenciales de acceso a la cuenta.</p>
                        
                        <article class="summary-card" style="margin-top: 20px; max-width: 560px;">
                            <div class="card-icon-wrapper <?= $esSuperadmin ? 'icon-green' : ($esDirector ? 'icon-dark' : ($esAlumno ? 'icon-green' : 'icon-blue')) ?>">
                                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                            <div class="card-data" style="width: 100%;">
                                <span class="card-label">Datos Personales</span>
                                <span style="font-size: 20px; font-weight: 800; color: var(--color-charcoal);"><?= $nombre ?></span>
                                <span style="font-size: 14px; color: var(--color-pencil-gray); margin-top: 2px;">
                                    Correo: <strong style="color: var(--color-charcoal);"><?= $email_usuario ?></strong>
                                </span>
                                <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 14px; color: var(--color-pencil-gray);">Rol asignado:</span>
                                    <span class="role-badge" style="background-color: var(--color-storybook-green); color: #2e7a00; font-size: 12px; font-weight: 800; text-transform: uppercase; padding: 2px 10px; border-radius: 999px;">
                                        <?= $tipo_usuario ?>
                                    </span>
                                </div>
                            </div>
                        </article>
                    </section>
                    <?php
                    break;

                case 'inicio':
                default:
                    ?>
                    <?php if ($esSuperadmin): ?>
                        <!-- ================= 2. DASHBOARD MODO SUPERADMIN ================= -->
                        <div class="welcome-banner">
                            <div class="welcome-text">
                                <h2>¡Hola, <?= $nombre ?>!</h2>
                                <p>Panel de administración global. Gestiona la plataforma, usuarios y recursos del sistema.</p>
                            </div>
                            <!-- 2. Llamado a la Acción (CTA): Accesos rápidos administrativos -->
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <a href="dashboard.php?seccion=usuarios" class="welcome-btn" style="background-color: var(--color-paper-white); color: var(--color-eager-green); border: 2px solid var(--color-eager-green); box-shadow: 0 4px 0 #bce69c;">
                                    + Añadir Nuevo Usuario
                                </a>
                                <a href="dashboard.php?seccion=cursos" class="welcome-btn">
                                    Gestionar Cursos &rarr;
                                </a>
                            </div>
                        </div>

                        <section aria-label="Tarjetas de Resumen Global">
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 14px;">Resumen del Sistema</h3>
                            
                            <div class="widgets-grid">
                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-green">
                                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Total de Usuarios Registrados</span>
                                        <span class="card-value"><?= number_format($metricas_superadmin['usuarios']) ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-blue">
                                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Cursos Activos</span>
                                        <span class="card-value"><?= number_format($metricas_superadmin['cursos_publicados']) ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-dark">
                                        <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Nuevos registros hoy</span>
                                        <span class="card-value"><?= number_format($metricas_superadmin['registros_hoy']) ?></span>
                                    </div>
                                </article>
                            </div>
                        </section>

                    <?php elseif ($esDirector): ?>
                        <!-- ================= DASHBOARD MODO DIRECTOR ================= -->
                        <div class="welcome-banner">
                            <div class="welcome-text">
                                <h2>¡Hola, <?= $nombre ?>!</h2>
                                <p>Panel de supervisión académica. Monitorea el progreso de la institución y el catálogo de cursos.</p>
                            </div>
                            <a href="dashboard.php?seccion=reportes" class="welcome-btn">
                                Ver Reportes &rarr;
                            </a>
                        </div>

                        <section aria-label="Tarjetas de Resumen del Director">
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 14px;">Métricas Generales de la Institución</h3>
                            
                            <div class="widgets-grid">
                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-green">
                                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Alumnos Activos</span>
                                        <span class="card-value"><?= $metricas_director['alumnos_activos'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-blue">
                                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Cursos en Impartición</span>
                                        <span class="card-value"><?= $metricas_director['cursos_imparticion'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-dark">
                                        <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Promedio General de Aprobación</span>
                                        <span class="card-value"><?= $metricas_director['promedio_aprobacion'] ?></span>
                                    </div>
                                </article>
                            </div>
                        </section>

                    <?php elseif ($esAlumno): ?>
                        <!-- ================= DASHBOARD MODO ALUMNO ================= -->
                        <div class="welcome-banner">
                            <div class="welcome-text">
                                <h2>¡Hola, <?= $nombre ?>!</h2>
                                <p>Listo para seguir aprendiendo. Continúa tus lecciones donde las dejaste.</p>
                            </div>
                            <a href="dashboard.php?seccion=cursos" class="welcome-btn">
                                Continuar Cursos &rarr;
                            </a>
                        </div>

                        <section aria-label="Tarjetas de Resumen del Alumno">
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 14px;">Tu Avance de Aprendizaje</h3>
                            
                            <div class="widgets-grid">
                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-blue">
                                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Cursos en Progreso</span>
                                        <span class="card-value"><?= $metricas_alumno['cursos_progreso'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-green">
                                        <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Cursos Completados</span>
                                        <span class="card-value"><?= $metricas_alumno['cursos_completados'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-orange">
                                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Próxima Tarea</span>
                                        <span style="font-size: 16px; font-weight: 800; color: var(--color-charcoal); line-height: 1.25; margin-top: 4px;">
                                            <?= htmlspecialchars($metricas_alumno['proxima_tarea']) ?>
                                        </span>
                                    </div>
                                </article>
                            </div>
                        </section>

                    <?php elseif ($esPonente): ?>
                        <!-- ================= DASHBOARD PARA PONENTE ================= -->
                        <div class="welcome-banner">
                            <div class="welcome-text">
                                <h2>¡Hola, <?= $nombre ?>!</h2>
                                <p>Panel de control de tus cursos y alumnos. Gestiona tus temarios y retroalimentación.</p>
                            </div>
                            <a href="dashboard.php?seccion=cursos" class="welcome-btn">
                                Ir a Mis Cursos &rarr;
                            </a>
                        </div>

                        <section aria-label="Resumen de Docencia">
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 14px;">Resumen de Docencia</h3>
                            
                            <div class="widgets-grid">
                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-green">
                                        <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Mis Cursos Activos</span>
                                        <span class="card-value"><?= $metricas_ponente['cursos_activos'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-blue">
                                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Total de Mis Alumnos</span>
                                        <span class="card-value"><?= $metricas_ponente['total_alumnos'] ?></span>
                                    </div>
                                </article>

                                <article class="summary-card">
                                    <div class="card-icon-wrapper icon-orange">
                                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                                    </div>
                                    <div class="card-data">
                                        <span class="card-label">Tareas Pendientes de Revisión</span>
                                        <span class="card-value"><?= $metricas_ponente['tareas_pendientes'] ?></span>
                                    </div>
                                </article>
                            </div>
                        </section>
                    <?php endif; ?>
                    <?php
                    break;
            }
            ?>
        </main>
    </div>

    <!-- 4. Pie de Página (Footer) -->
    <footer class="site-footer">
        <p><span>Cursos Online</span> &copy; <?= date('Y') ?> &mdash; Plataforma de aprendizaje interactivo.</p>
    </footer>

</body>
</html>
