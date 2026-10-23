<?php
// Datos simulados de usuarios
$estadisticas = [
    'total' => '1,248',
    'alumnos' => '892',
    'docentes' => '86'
];

$usuarios = [
    [
        'nombre' => 'Carlos Mendoza',
        'email' => 'carlos.mendoza@plataforma.com',
        'rol' => 'Alumno',
        'estado' => 'Activo'
    ],
    [
        'nombre' => 'Ana Sofía Torres',
        'email' => 'ana.torres@plataforma.com',
        'rol' => 'Docente',
        'estado' => 'Activo'
    ],
    [
        'nombre' => 'Juan Pablo Morales',
        'email' => 'juan.morales@plataforma.com',
        'rol' => 'Alumno',
        'estado' => 'Inactivo'
    ],
    [
        'nombre' => 'Laura Gómez',
        'email' => 'laura.gomez@plataforma.com',
        'rol' => 'Director',
        'estado' => 'Activo'
    ],
    [
        'nombre' => 'Miguel Ángel Ramírez',
        'email' => 'miguel.ramirez@plataforma.com',
        'rol' => 'Ponente',
        'estado' => 'Pendiente'
    ]
];
?>

<section>
    <h2>Gestión de Usuarios</h2>

    <div>
        <p>Total de usuarios: <?= $estadisticas['total'] ?></p>
        <p>Alumnos: <?= $estadisticas['alumnos'] ?></p>
        <p>Docentes: <?= $estadisticas['docentes'] ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                    <td><?= htmlspecialchars($usuario['rol']) ?></td>
                    <td><?= htmlspecialchars($usuario['estado']) ?></td>
                    <td>
                        <button type="button">Editar</button>
                        <button type="button">Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

