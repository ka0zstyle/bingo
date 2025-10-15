<?php
session_start();
define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';

// Si no hay mensaje de éxito, redirigimos al inicio para evitar acceso directo.
if (!isset($_SESSION['message'])) {
    header('Location: ' . BASE_URL);
    exit;
}

$message = $_SESSION['message'];
unset($_SESSION['message'], $_SESSION['message_type']); // Limpiamos para que no se muestre de nuevo

require_once __DIR__ . '/includes/header.php';
?>

<div class="success-container">
    <div class="success-icon">
        <i class="fas fa-check"></i>
    </div>
    <h2>¡Operación Exitosa!</h2>
    <p><?php echo htmlspecialchars($message); ?></p>
    <a href="<?php echo BASE_URL; ?>" class="btn">Volver al Inicio</a>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>