<?php
define('BINGO_SYSTEM', true);
require_once '../includes/security.php';
require_once '../includes/config.php';
require_once '../includes/database.php';

initialize_session_and_csrf();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $_SESSION['admin_role'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <meta name="csrf-token" content="<?php e($_SESSION['csrf_token']); ?>">
    
    <title><?php e($page_title ?? 'Admin Panel'); ?> - Sistema de Bingo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin-style.css">
    
    <!-- LÍNEA AÑADIDA -->
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    <!-- FIN DE LÍNEA AÑADIDA -->

</head>
<body>
<div class="admin-layout">
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>Bingo Admin</h3>

            <button class="mobile-menu-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="admin-nav-list" title="Menú">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <ul class="nav-list" id="admin-nav-list">
             <li>
                <a href="pagos.php" class="<?php echo ($current_page == 'pagos.php') ? 'active' : ''; ?>">
                    <span class="icon">&#128179;</span>
                    <span>Pagos</span>
                </a>
            </li>
            <?php if ($current_role === 'admin'): ?>
                <li>
                    <a href="eventos.php" class="<?php echo ($current_page == 'eventos.php') ? 'active' : ''; ?>">
                        <span class="icon">&#128197;</span>
                        <span>Eventos</span>
                    </a>
                </li>
                <li>
                    <a href="ajustes.php" class="<?php echo ($current_page == 'ajustes.php') ? 'active' : ''; ?>">
                        <span class="icon">&#9881;</span>
                        <span>Ajustes</span>
                    </a>
                </li>
                <li>
                    <a href="usuarios.php" class="<?php echo ($current_page == 'usuarios.php') ? 'active' : ''; ?>">
                        <span class="icon">&#128100;</span>
                        <span>Usuarios</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-footer">
                <a href="auth.php?action=logout&csrf_token=<?php e($_SESSION['csrf_token']); ?>">
                    <span class="icon">&#128682;</span>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="main-content">
        <header class="main-header">
            <h1><?php e($page_title ?? 'Panel de Control'); ?></h1>
        </header>
        <main class="content-wrapper">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const navList = document.getElementById('admin-nav-list');

    if (menuToggle && navList) {
        menuToggle.addEventListener('click', function () {
            const isOpen = navList.classList.toggle('open');
            menuToggle.classList.toggle('is-open', isOpen);
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }
});
</script>