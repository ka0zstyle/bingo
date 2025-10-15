<?php
define('BINGO_SYSTEM', true);
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/database.php';

// Iniciar sesión para acceder a $_SESSION
initialize_session_and_csrf();

// --- Logout Action ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Al cerrar sesión, no es necesario validar el token CSRF, ya que esto puede fallar.
    // La sesión simplemente se destruye de forma segura para cerrar la sesión del usuario.
    session_destroy();
    header('Location: login.php');
    exit;
}

// --- Login Action ---
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Usuario y contraseña son requeridos.";
        header('Location: login.php');
        exit;
    }

    try {
        $pdo = get_db_connection();
    } catch (Exception $e) {
        $_SESSION['login_error'] = "Error de conexión a la base de datos: " . $e->getMessage();
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, active FROM bingo_admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['active'] && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true); // Regenerar ID de sesión por seguridad

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'];
        
        // Generar un nuevo token CSRF después del login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: pagos.php');
        exit;
    } else {
        $_SESSION['login_error'] = "Credenciales incorrectas o usuario inactivo.";
        header('Location: login.php');
        exit;
    }
}

// Si se llega aquí sin acción, redirigir
header('Location: login.php');
exit;