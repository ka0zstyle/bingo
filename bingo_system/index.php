<?php
define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

// Verificamos si el helper de moneda existe ANTES de intentar usarlo.
if (file_exists(__DIR__ . '/includes/currency_helper.php')) {
    require_once __DIR__ . '/includes/currency_helper.php';
}

// Fallback seguro por si no existe currency_helper.php o no define format_price
if (!function_exists('format_price')) {
    function format_price(float $price, string $currency_code): string {
        $symbol = ($currency_code === 'USD') ? '$' : 'Bs.';
        return $symbol . number_format($price, 2);
    }
}

$juegos = [];
$default_currency_code = 'VES'; // Valor por defecto seguro

if (function_exists('get_currency_settings')) {
    try {
        $currencySettings = get_currency_settings();
        if (is_array($currencySettings) && isset($currencySettings['code']) && in_array($currencySettings['code'], ['USD', 'VES'], true)) {
            $default_currency_code = $currencySettings['code'];
        }
    } catch (Throwable $e) {
        // No hacer nada si falla
    }
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM bingo_events WHERE is_active = 1 AND event_date > NOW() ORDER BY event_date ASC");
    $juegos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['general_error'] = 'No se pudo cargar la lista de juegos en este momento. Inténtalo más tarde.';
    $juegos = [];
}

// Incluimos el header después de toda la lógica PHP
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <h1>El Bingo Online de Maracay</h1>
    <p>¡Compra tus cartones, participa y gana increíbles premios!</p>
</div>

<div class="container">

    <?php if (isset($_SESSION['general_error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['general_error']; unset($_SESSION['general_error']); ?></div>
    <?php endif; ?>

    <h2 class="games-section-title">Próximos Juegos</h2>
    
    <section class="games-list">
        <?php if (empty($juegos)): ?>
            <p style="text-align: center; grid-column: 1 / -1;">No hay juegos activos en este momento. ¡Vuelve pronto!</p>
        <?php else: ?>
            <?php foreach ($juegos as $juego): ?>
                <div class="game-card">
                    <div class="card-content">
                        <h3><?php echo htmlspecialchars($juego['name']); ?></h3>
                        
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i> <span><?php echo date('d/m/Y h:i A', strtotime($juego['event_date'])); ?></span>
                        </div>
                        
                        <div class="price">
                            <?php
                                if ($default_currency_code === 'USD') {
                                    echo format_price((float)$juego['price_usd'], 'USD');
                                } else {
                                    echo format_price((float)$juego['price_local'], 'VES');
                                }
                            ?>
                            <span style="font-size: 1rem; color: var(--text-light);"> / cartón</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="<?php echo BASE_URL . 'comprar.php?event_id=' . (int)$juego['id']; ?>" class="btn">Comprar Cartones</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div> <?php
require_once __DIR__ . '/includes/footer.php';
?>