<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();

define('BINGO_SYSTEM', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/lib/PDFGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verificador.php');
    exit;
}

$event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$id_card  = preg_replace('/[^0-9]/', '', $_POST['id_card'] ?? '');

if (!$event_id || !$email || !$id_card) {
    $_SESSION['verifier_error'] = 'Todos los campos son obligatorios.';
    header('Location: verificador.php');
    exit;
}

$pdo = get_db_connection();

// Buscamos los cartones aprobados de ese usuario para ese evento
$stmt = $pdo->prepare("
    SELECT c.card_code, c.card_json
    FROM bingo_purchases p
    JOIN bingo_cards c ON p.id = c.purchase_id
    WHERE p.event_id = ? AND p.owner_email = ? AND p.owner_id_card = ? AND p.status = 'approved'
");
$stmt->execute([$event_id, $email, $id_card]);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cards)) {
    $_SESSION['verifier_error'] = 'No se encontraron compras aprobadas para este evento con los datos proporcionados. Revisa que hayas seleccionado el evento correcto y que tu pago haya sido confirmado.';
    header('Location: verificador.php');
    exit;
}

// Obtenemos el nombre del evento
$stmt_event = $pdo->prepare("SELECT name FROM bingo_events WHERE id = ?");
$stmt_event->execute([$event_id]);
$eventName = $stmt_event->fetchColumn();

// Cargamos la configuración de diseño
$stmt_settings = $pdo->query("SELECT * FROM bingo_settings");
$settings_raw = [];
if ($stmt_settings) {
    foreach ($stmt_settings->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings_raw[$row['setting_key']] = $row['setting_value'];
    }
}

// Mapeo de settings hacia PDFGenerator
$settings = [
    'header_color'       => $settings_raw['carton_header_color'] ?? '#ef4444',
    'header_text_color'  => $settings_raw['carton_header_text_color'] ?? '#ffffff',
    'free_cell_color'    => $settings_raw['carton_free_cell_color'] ?? '#ef4444',
    'free_text_color'    => $settings_raw['carton_free_text_color'] ?? '#ffffff',
    'grid_style'         => $settings_raw['carton_grid_style'] ?? 'solid',
    'border_color'       => $settings_raw['carton_border_color'] ?? '#ef4444',
    'number_color'       => $settings_raw['carton_number_color'] ?? '#2d3748',
];

// Generamos el PDF
$pdf = new PDFGenerator($settings);
$pdf->setEventName($eventName);
$pdf->AddPage();

$cardCount = 0;
foreach ($cards as $card) {
    if ($cardCount > 0 && $cardCount % 2 == 0) {
        $pdf->AddPage();
    }

    // Normalización: si card_json viene como {"numbers": {...}}, tomar ese interior.
    $raw     = json_decode($card['card_json'], true);
    $numbers = (is_array($raw) && array_key_exists('numbers', $raw)) ? $raw['numbers'] : $raw;

    $pdf->DrawBingoCard($numbers, $card['card_code']);
    $cardCount++;
}

$pdf->Output('D', 'Mis_Cartones_Bingo.pdf');