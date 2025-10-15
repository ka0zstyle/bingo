<?php
session_start();
require_once '../includes/database.php';

// Añade esta acción para el logout al principio
if ($_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_GET['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Usuario y contraseña son requeridos.";
        header('Location: login.php');
        exit;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, active FROM bingo_admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['active'] && password_verify($password, $user['password_hash'])) {
        // ¡Login Exitoso! Regenerar ID de sesión por seguridad.
        session_regenerate_id(true);

        // Establecer las variables de sesión que la aplicación espera
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'];

        // Redirigir al panel principal (ej. pagos.php)
        header('Location: pagos.php');
        exit;
    } else {
        // Error en el login. Redirigir de vuelta con un mensaje.
        $_SESSION['login_error'] = "Credenciales incorrectas o usuario inactivo.";
        header('Location: login.php');
        exit;
    }
}
?>