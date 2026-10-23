<?php
// Datos simulados de cursos para el detalle
$catalogoCursos = [
    1 => [
        'titulo' => 'Desarrollo Web desde Cero',
        'progreso' => '68%',
        'bloques' => [
            [
                'nombre' => 'Bloque 1: Introducción y Fundamentos Web',
                'temas' => [
                    'Tema 1: Arquitectura Cliente-Servidor y Protocolo HTTP',
                    'Tema 2: Estructura y semántica básica con HTML5',
                    'Tema 3: Primeros pasos con hojas de estilo CSS'
                ]
            ],
            [
                'nombre' => 'Bloque 2: Backend y Programación en el Servidor',
                'temas' => [
                    'Tema 1: Sintaxis básica, variables y control de flujo en PHP',
                    'Tema 2: Manejo de formularios y métodos GET/POST',
                    'Tema 3: Gestión de sesiones y autenticación de usuarios'
                ]
            ],
            [
                'nombre' => 'Bloque 3: Integración con Bases de Datos',
                'temas' => [
                    'Tema 1: Conexión mediante mysqli y sentencias preparadas',
                    'Tema 2: Operaciones CRUD completas'
                ]
            ]
        ],
        'proximas_tareas' => [
            'Práctica 3: Implementar login con sesiones en PHP (Vence: 28 de Octubre)',
            'Proyecto Parcial: Prototipo navegable de cursos (Vence: 05 de Noviembre)'
        ],
        'calificaciones' => [
            'Evaluación Diagnóstica: 10 / 10',
            'Práctica 1 (Estructura HTML): 9.5 / 10',
            'Práctica 2 (Formularios y Validación): 8.8 / 10'
        ]
    ],
    2 => [
        'titulo' => 'Bases de Datos con MySQL y SQL Relacional',
        'progreso' => '45%',
        'bloques' => [
            [
                'nombre' => 'Bloque 1: Modelo Relacional y Diseño',
                'temas' => [
                    'Tema 1: Entidades, atributos y relaciones',
                    'Tema 2: Reglas de normalización (1FN, 2FN, 3FN)'
                ]
            ],
            [
                'nombre' => 'Bloque 2: Lenguaje DDL y DML',
                'temas' => [
                    'Tema 1: Creación de esquemas y tablas',
                    'Tema 2: Consultas avanzadas con JOINs y agregaciones'
                ]
            ]
        ],
        'proximas_tareas' => [
            'Taller de Normalización de Tablas (Vence: 30 de Octubre)'
        ],
        'calificaciones' => [
            'Examen Teórico SQL: 9.0 / 10'
        ]
    ],
    3 => [
        'titulo' => 'Programación Orientada a Objetos en PHP',
        'progreso' => '25%',
        'bloques' => [
            [
                'nombre' => 'Bloque 1: Clases y Objetos',
                'temas' => [
                    'Tema 1: Definición de clases, propiedades y métodos',
                    'Tema 2: Encapsulamiento y modificadores de acceso'
                ]
            ]
        ],
        'proximas_tareas' => [
            'Ejercicio: Diagrama de clases y modelado de objetos (Vence: 02 de Noviembre)'
        ],
        'calificaciones' => [
            'Cuestionario Fundamentos POO: 10 / 10'
        ]
    ]
];

$cursoId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$cursoActual = $catalogoCursos[$cursoId] ?? $catalogoCursos[1];
?>

<article>
    <h2><?= htmlspecialchars($cursoActual['titulo']) ?></h2>
    <p>Avance: <?= htmlspecialchars($cursoActual['progreso']) ?></p>

    <p><a href="dashboard.php?seccion=cursos">&larr; Volver a Mis Cursos</a></p>

    <section>
        <h3>Temario del Curso</h3>
        <ul>
            <?php foreach ($cursoActual['bloques'] as $bloque): ?>
                <li>
                    <strong><?= htmlspecialchars($bloque['nombre']) ?></strong>
                    <ul>
                        <?php foreach ($bloque['temas'] as $tema): ?>
                            <li><?= htmlspecialchars($tema) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section>
        <h3>Próximas tareas</h3>
        <ul>
            <?php foreach ($cursoActual['proximas_tareas'] as $tarea): ?>
                <li><?= htmlspecialchars($tarea) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section>
        <h3>Calificaciones</h3>
        <ul>
            <?php foreach ($cursoActual['calificaciones'] as $calificacion): ?>
                <li><?= htmlspecialchars($calificacion) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</article>

