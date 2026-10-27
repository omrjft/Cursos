<?php

function conect(): mysqli
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $usuario = getenv('DB_USER') ?: 'omar';
    $password = getenv('DB_PASSWORD') ?: 'root';
    $baseDatos = getenv('DB_NAME') ?: 'cursos';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conexion = new mysqli($host, $usuario, $password, $baseDatos);
    $conexion->set_charset('utf8mb4');

    return $conexion;
}
