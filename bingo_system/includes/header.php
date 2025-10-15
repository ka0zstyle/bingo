<?php 
if (!defined('BINGO_SYSTEM')) die('Acceso denegado'); 

// Iniciar sesión si no está iniciada (necesario para leer $_SESSION['csrf_token'])
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Helper local de escape para evitar dependencia de e()
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Obtenemos el nombre del archivo actual para saber qué enlace marcar como "activo"
$currentPage = basename($_SERVER['PHP_SELF']);
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo h($csrfToken); ?>">
    <title>Bingo Maracay - Profesional</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="<?php echo BASE_URL; ?>" class="logo">
                <img src="<?php echo LOGO_URL; ?>" alt="Logo Bingo Maracay">
                <span>Bingo Maracay</span>
            </a>
            <nav class="main-nav">
                <a href="<?php echo BASE_URL; ?>" class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">Inicio</a>
                
                <a href="<?php echo BASE_URL; ?>verificador.php" class="nav-link <?php echo ($currentPage === 'verificador.php') ? 'active' : ''; ?>">Verificar Cartones</a>
                
                <a href="#" class="nav-link">Resultados</a>
                <a href="#" class="nav-link">Contacto</a>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">