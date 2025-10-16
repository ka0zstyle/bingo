<?php
session_start();

define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/lib/BingoCardGenerator.php';

// Intentar cargar helper de moneda para conversiones en tiempo real
$currency_helper_loaded = false;
$currency_settings = null;
if (file_exists(__DIR__ . '/includes/currency_helper.php')) {
    require_once __DIR__ . '/includes/currency_helper.php';
    if (function_exists('get_currency_settings')) {
        $currency_settings = get_currency_settings(); // ['code','symbol','rate','show_decimals']
        $currency_helper_loaded = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL);
    exit;
}

// Guardamos los datos del formulario para repoblar si hay error
$_SESSION['form_data'] = $_POST;

// Sanitización de entrada
$event_id       = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$nombre         = trim($_POST['nombre'] ?? '');
$email          = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$id_card        = preg_replace('/[^0-9]/', '', $_POST['id_card'] ?? '');
$cantidad       = filter_var(trim($_POST['cantidad'] ?? '1'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
$refPago        = htmlspecialchars(trim($_POST['ref_pago'] ?? ''));
$payment_method = htmlspecialchars(trim($_POST['payment_method'] ?? ''));

// Manejo del comprobante (el campo puede llamarse payment_receipt, receipt o comprobante)
$receipt_path_for_db = null;
try {
    $uploadField = null;
    foreach (['payment_receipt', 'receipt', 'comprobante'] as $f) {
        if (isset($_FILES[$f]) && is_array($_FILES[$f]) && !empty($_FILES[$f]['name'])) {
            $uploadField = $f;
            break;
        }
    }
    if ($uploadField) {
        $fileTmp  = $_FILES[$uploadField]['tmp_name'] ?? '';
        $fileName = $_FILES[$uploadField]['name'] ?? '';
        $fileErr  = $_FILES[$uploadField]['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileErr === UPLOAD_ERR_OK && is_uploaded_file($fileTmp)) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            // Permitir imágenes comunes y PDF
            $allowed = ['jpg','jpeg','png','webp','gif','pdf'];
            if (!in_array($ext, $allowed, true)) {
                throw new RuntimeException('Formato de comprobante no permitido.');
            }

            $uploadDir = __DIR__ . '/uploads/receipts/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $destName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . $destName;

            if (!move_uploaded_file($fileTmp, $destPath)) {
                throw new RuntimeException('No se pudo guardar el comprobante.');
            }
            // Guardamos solo el nombre de archivo; la ruta pública se arma con BASE_URL en admin/pagos.php
            $receipt_path_for_db = $destName;
        } elseif ($fileErr !== UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Error al subir el comprobante (código: ' . (int)$fileErr . ').');
        }
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Error con el comprobante: ' . $e->getMessage();
    header('Location: ' . BASE_URL . 'comprar.php?event_id=' . (int)$event_id);
    exit;
}

// Validaciones básicas
if (!$event_id) {
    $_SESSION['error'] = 'Debes seleccionar un evento.';
    header('Location: ' . BASE_URL . 'comprar.php?event_id=' . (int)$event_id);
    exit;
}
if (!$nombre || !$email || !$id_card || !$cantidad || !$refPago || !$payment_method) {
    $_SESSION['error'] = 'Todos los campos son obligatorios.';
    header('Location: ' . BASE_URL . 'comprar.php?event_id=' . (int)$event_id);
    exit;
}

$pdo = get_db_connection();

// Precios del evento: ya NO existe price_usd en bingo_events
$stmt_event = $pdo->prepare("SELECT price_local FROM bingo_events WHERE id = ?");
$stmt_event->execute([$event_id]);
$event_prices = $stmt_event->fetch(PDO::FETCH_ASSOC);

if (!$event_prices) {
    $_SESSION['error'] = 'El evento seleccionado no es válido.';
    header('Location: ' . BASE_URL);
    exit;
}

// Total en moneda local (default)
$price_local_per_card = (float)$event_prices['price_local'];
$total_local          = $cantidad * $price_local_per_card;

// Total en USD (dinámico con tasa actual)
if ($currency_helper_loaded && function_exists('local_to_usd')) {
    $total_usd = local_to_usd($total_local);
} else {
    // Fallback directo a la DB si no está disponible el helper:
    $cur = $pdo->query("SELECT code, rate FROM bingo_currencies WHERE is_default = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cur && isset($cur['code'], $cur['rate'])) {
        $code = (string)$cur['code'];
        $rate = (float)$cur['rate'];
        $total_usd = ($code === 'USD') ? $total_local : ($total_local / max(1e-12, $rate));
    } else {
        // Último recurso: asumir USD=local
        $total_usd = $total_local;
    }
}

try {
    $pdo->beginTransaction();

    // Insert de la compra (guardamos los totales calculados)
    $stmt_purchase = $pdo->prepare('
        INSERT INTO bingo_purchases (event_id, owner_name, owner_email, owner_id_card, payment_ref, total_local, total_usd, payment_method, payment_receipt_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt_purchase->execute([
        $event_id, $nombre, $email, $id_card, $refPago, $total_local, $total_usd, $payment_method, $receipt_path_for_db
    ]);
    $purchaseId = (int)$pdo->lastInsertId();

    // Centro FREE (si aplica)
    $stmt_setting  = $pdo->query("SELECT setting_value FROM bingo_settings WHERE setting_key = 'carton_center_is_free'");
    $isCenterFree  = ($stmt_setting->fetchColumn() ?? '1') === '1';

    // Generador de cartones
    $generator = new BingoCardGenerator($pdo);

    // Guardamos SOLO los numbers en card_json (persistencia unificada)
    $stmt_card_insert = $pdo->prepare('INSERT INTO bingo_cards (purchase_id, card_json, card_code) VALUES (?, ?, ?)');
    $stmt_card_update = $pdo->prepare('UPDATE bingo_cards SET card_code = ? WHERE id = ?');

    for ($i = 0; $i < $cantidad; $i++) {
        // generate puede devolver ['code' => 'B-...', 'numbers' => ['B'=>[], ...]]
        $cardData = $generator->generate($isCenterFree);

        // Normalizamos: si viene ['numbers'=>..] tomamos ese arreglo; si viene 5x5 o columnas, lo usamos tal cual.
        if (is_array($cardData) && array_key_exists('numbers', $cardData)) {
            $numbers = $cardData['numbers'];
        } else {
            $numbers = $cardData;
        }

        // Insertamos con code temporal; luego generamos el code público en base al ID
        $stmt_card_insert->execute([$purchaseId, json_encode($numbers, JSON_UNESCAPED_UNICODE), 'TEMP']);
        $cardId = (int)$pdo->lastInsertId();

        // Generación de código público estable (ej: B-1166-K)
        $publicId     = 1000 + $cardId;
        $randomLetter = chr(rand(65, 90));
        $cardCode     = "B-{$publicId}-{$randomLetter}";

        // Si el generador ya trae 'code', podrías preferirlo. Se deja la lógica actual por compatibilidad.
        $stmt_card_update->execute([$cardCode, $cardId]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    if ((int)$e->getCode() === 23000) {
        $_SESSION['error'] = 'Error: La referencia de pago ya ha sido utilizada.';
    } else {
        $_SESSION['error'] = 'Error crítico al procesar la compra: ' . $e->getMessage();
    }
    header('Location: ' . BASE_URL . 'comprar.php?event_id=' . (int)$event_id);
    exit;
}

// Limpiamos datos del formulario si todo salió bien
unset($_SESSION['form_data']);

// Mensaje de confirmación para la UI
$_SESSION['success'] = 'Tu pago ha sido registrado. Recibirás un correo cuando sea aprobado por el equipo.';

// Redirigimos al formulario del evento para ver el estado
header('Location: ' . BASE_URL . 'comprar.php?event_id=' . (int)$event_id);
exit;