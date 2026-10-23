<?php
function conect(){
    $host = "localhost";
    $usuario = "omar";
    $password = "root";
    $baseDatos = "cursos";

    $conexion = new mysqli($host, $usuario, $password, $baseDatos);

    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    return $conexion;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $conexion = conect();
    $sql = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($usuario = $resultado->fetch_assoc()) {
        // Valida tanto contraseña con hash (bcrypt) como en texto plano si no ha sido hasheada
        $esValida = password_verify($password, $usuario["password_hash"]) || ($password === $usuario["password_hash"]);
        if ($esValida) {
            session_start();
            session_regenerate_id(true);
            $_SESSION["id"] = $usuario["id"];
            $_SESSION["nombre"] = $usuario["nombre"];
            $_SESSION["rol_id"] = $usuario["rol_id"];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "No existe un usuario con ese correo.";
    }
}
?>

<h1>
ingrese datos de usuario
</h1>

<?php if (!empty($error)): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" placeholder="ejemplo@correo.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary">Iniciar sesión</button>
            </form>