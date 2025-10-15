<?php
session_start();
define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

// PRIMERO: Recuperamos los datos del formulario anterior, si existen.
$old_data = $_SESSION['form_data'] ?? [];
// SEGUNDO: Limpiamos la sesión para no rellenar el formulario en futuras visitas.
unset($_SESSION['form_data']);

// TERCERO: Asignamos los valores a variables para usarlas de forma segura en el formulario.
$nombre_val = htmlspecialchars($old_data['nombre'] ?? '');
$email_val = htmlspecialchars($old_data['email'] ?? '');
$id_card_val = htmlspecialchars($old_data['id_card'] ?? '');
$cantidad_val = htmlspecialchars($old_data['cantidad'] ?? '1');
$ref_pago_val = htmlspecialchars($old_data['ref_pago'] ?? '');
$payment_method_val = $old_data['payment_method'] ?? '';

if (file_exists(__DIR__ . '/includes/currency_helper.php')) {
    require_once __DIR__ . '/includes/currency_helper.php';
    $currency_settings = get_currency_settings();
} else {
    $currency_settings = ['symbol' => 'Bs.', 'code' => 'VES', 'show_decimals' => true];
}

$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    header('Location: ' . BASE_URL);
    exit('Evento no especificado.');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT name, price_local, price_usd FROM bingo_events WHERE id = ? AND is_active = 1 AND event_date > NOW()");
$stmt->execute([$event_id]);
$evento = $stmt->fetch();

if (!$evento) {
    $_SESSION['general_error'] = 'El evento al que intentas acceder ya no está disponible para la venta.';
    header('Location: ' . BASE_URL);
    exit;
}

$payment_methods_stmt = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1");
$payment_methods = $payment_methods_stmt->fetchAll();
$price_for_js = ($currency_settings['code'] === 'USD') ? $evento['price_usd'] : $evento['price_local'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h2>Comprar Cartones para: <?php echo htmlspecialchars($evento['name']); ?></h2>
    <p>Sigue los pasos para completar tu compra de forma segura.</p>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="procesar_compra.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <input type="hidden" id="price_per_carton" value="<?php echo $price_for_js; ?>">
        
        <div class="form-step">
            <h3><i class="fas fa-user"></i>1. Tus Datos</h3>
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" id="nombre" name="nombre" required placeholder="Ej: Ana Pérez" value="<?php echo $nombre_val; ?>">
            </div>
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required placeholder="Ej: ana.perez@correo.com" value="<?php echo $email_val; ?>">
            </div>
            <div class="form-group">
                <label for="id_card">Número de Cédula (sin puntos)</label>
                <input type="text" id="id_card" name="id_card" required pattern="[0-9]+" placeholder="Ej: 12345678" value="<?php echo $id_card_val; ?>">
                <small>Este será tu clave para descargar los cartones.</small>
            </div>
            <div class="form-group">
                <label for="cantidad">Cantidad de Cartones (Máx: 20)</label>
                <input type="number" id="cantidad" name="cantidad" min="1" max="20" required value="<?php echo $cantidad_val; ?>">
            </div>
        </div>

        <div class="form-step">
            <h3><i class="fas fa-money-bill-wave"></i>2. Realiza tu Pago</h3>
            <div class="form-group total-amount-display">
                <strong>Total a Pagar: <span id="total_amount_display"></span></strong>
            </div>
            <div class="form-group">
                <label>Elige un método de pago y sigue las instrucciones:</label>
                <div class="payment-methods">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="payment-method-card">
                            <?php $methodName = htmlspecialchars_decode($method['name']); ?>
                            <input type="radio" name="payment_method" value="<?php echo htmlspecialchars($methodName); ?>" id="pm_<?php echo $method['id']; ?>" required <?php if ($payment_method_val === $methodName) echo 'checked'; ?>>
                            <label class="payment-method-header" for="pm_<?php echo $method['id']; ?>">
                                <span class="radio-custom"></span>
                                <?php echo htmlspecialchars($methodName); ?>
                            </label>
                            <div class="payment-details">
                                <?php
                                $details = htmlspecialchars_decode(trim($method['details']));
                                $pairs = explode(',', $details);
                                if (count($pairs) > 0 && strpos($details, ':') !== false) {
                                    echo '<div class="details-grid">';
                                    foreach ($pairs as $pair) {
                                        $parts = explode(':', $pair, 2);
                                        if (count($parts) === 2) {
                                            $key = trim($parts[0]); $value = trim($parts[1]);
                                            echo '<div class="detail-item"><strong>' . htmlspecialchars($key) . ':</strong><span>' . htmlspecialchars($value) . '</span><button type="button" class="copy-btn" data-clipboard-text="' . htmlspecialchars($value) . '">Copiar</button></div>';
                                        }
                                    }
                                    echo '</div>';
                                } else { echo '<p>' . nl2br(htmlspecialchars($details)) . '</p>'; }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="form-step">
            <h3><i class="fas fa-check-circle"></i>3. Confirma tu Compra</h3>
            <div class="form-group">
                <label for="ref_pago">Referencia de Pago</label>
                <input type="text" id="ref_pago" name="ref_pago" required placeholder="Ej: 00123456789" value="<?php echo $ref_pago_val; ?>">
            </div>
            <div class="form-group">
                <label>Adjuntar Comprobante de Pago (Capture)</label>
                <div class="file-upload-group">
                    <label for="payment_receipt" class="file-upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Haz clic aquí para seleccionar un archivo</span>
                    </label>
                    <input type="file" id="payment_receipt" name="payment_receipt" accept="image/png, image/jpeg, image/gif, application/pdf" required>
                    <div class="file-name" id="file-name-display"></div>
                </div>
                 <small>Archivos permitidos: JPG, PNG, GIF, PDF. Tamaño máximo: 5MB.</small>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Finalizar Compra</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cantidadInput = document.getElementById('cantidad');
    const pricePerCarton = parseFloat(document.getElementById('price_per_carton').value);
    const totalAmountDisplay = document.getElementById('total_amount_display');
    const currencyConfig = <?php echo json_encode($currency_settings); ?>;

    function calculateAndDisplayTotal() {
        const cantidad = parseInt(cantidadInput.value, 10) || 0;
        const total = cantidad * pricePerCarton;
        const decimals = currencyConfig.show_decimals ? 2 : 0;
        const formattedTotal = total.toLocaleString('es-VE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        totalAmountDisplay.textContent = currencyConfig.symbol + ' ' + formattedTotal;
    }
    cantidadInput.addEventListener('input', calculateAndDisplayTotal);
    calculateAndDisplayTotal();

    document.querySelectorAll('.copy-btn').forEach(button => {
        button.addEventListener('click', function() {
            const textToCopy = this.getAttribute('data-clipboard-text');
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = this.textContent;
                this.textContent = '¡Copiado!'; this.classList.add('copied');
                setTimeout(() => { this.textContent = originalText; this.classList.remove('copied'); }, 2000);
            }).catch(err => { console.error('Error al copiar: ', err); });
        });
    });

    const fileInput = document.getElementById('payment_receipt');
    const fileNameDisplay = document.getElementById('file-name-display');
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileNameDisplay.textContent = 'Archivo seleccionado: ' + this.files[0].name;
        } else {
            fileNameDisplay.textContent = '';
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>