<?php
// Datos simulados de cursos
$resumenDocente = [
    'total_alumnos' => 145,
    'tareas_pendientes' => 12
];

$cursos = [
    [
        'id' => 1,
        'titulo' => 'Desarrollo Web desde Cero',
        'descripcion' => 'Aprende los fundamentos de HTML, CSS, JavaScript y PHP para crear aplicaciones web.',
        'alumnos' => 60,
        'tareas' => 5
    ],
    [
        'id' => 2,
        'titulo' => 'Bases de Datos con MySQL y SQL Relacional',
        'descripcion' => 'Diseño y normalización de bases de datos, consultas complejas y optimización.',
        'alumnos' => 52,
        'tareas' => 4
    ],
    [
        'id' => 3,
        'titulo' => 'Programación Orientada a Objetos en PHP',
        'descripcion' => 'Conceptos clave de POO, patrones de diseño y buenas prácticas en backend.',
        'alumnos' => 33,
        'tareas' => 3
    ]
];
?>

<section>
    <h2>Mis Cursos</h2>

    <div>
        <p>Total de alumnos: <?= $resumenDocente['total_alumnos'] ?></p>
        <p>Tareas pendientes: <?= $resumenDocente['tareas_pendientes'] ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Curso</th>
                <th>Descripción</th>
                <th>Alumnos Inscritos</th>
                <th>Tareas por Revisar</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cursos as $curso): ?>
                <tr>
                    <td><?= htmlspecialchars($curso['titulo']) ?></td>
                    <td><?= htmlspecialchars($curso['descripcion']) ?></td>
                    <td><?= $curso['alumnos'] ?></td>
                    <td><?= $curso['tareas'] ?></td>
                    <td>
                        <a href="dashboard.php?seccion=curso_detalle&id=<?= $curso['id'] ?>">Ver detalle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

