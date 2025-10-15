<?php
/**
 * API del panel Admin
 * - Normaliza cartones antes de renderizar (soporta ['numbers'=>...], columnas B..O o 5x5).
 * - Unifica persistencia (cuando agrega cartones, guarda SOLO numbers en card_json).
 * - Endpoints: save_event, adjust_card_quantity, get_cartons_for_purchase, update_purchase_status,
 *   preview_carton, get_purchase_history, toggle_event_status, save_settings,
 *   save_payment_method, save_currency, set_default_currency.
 */
declare(strict_types=1);

session_start();
define('BINGO_SYSTEM', true);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../lib/BingoCardGenerator.php';
require_once __DIR__ . '/../lib/BingoCardRenderer.php';

function json_ok(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $message, int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
function require_csrf_for_post(): void {
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') return;
    $sent = $_POST['csrf_token'] ?? null;
    if ($sent === null) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['csrf_token'])) $sent = $j['csrf_token'];
        }
    }
    if (!isset($_SESSION['csrf_token']) || $sent !== $_SESSION['csrf_token']) {
        json_err('Token CSRF inválido o ausente.', 403);
    }
}
/** Normaliza cualquier estructura de cartón a columnas B..O o grilla 5x5. */
function normalize_numbers($data) {
    if (is_array($data) && array_key_exists('numbers', $data)) $data = $data['numbers'];
    if (is_array($data) && isset($data['B'],$data['I'],$data['N'],$data['G'],$data['O'])) return $data;
    if (is_array($data) && count($data) === 5 && is_array($data[0] ?? null)) {
        $cols = ['B'=>[], 'I'=>[], 'N'=>[], 'G'=>[], 'O'=>[]];
        for ($r=0;$r<5;$r++) {
            $row = array_values($data[$r]);
            for ($c=0;$c<5;$c++) {
                $val = $row[$c] ?? '';
                if ($c === 0) $cols['B'][$r] = $val;
                if ($c === 1) $cols['I'][$r] = $val;
                if ($c === 2) $cols['N'][$r] = $val;
                if ($c === 3) $cols['G'][$r] = $val;
                if ($c === 4) $cols['O'][$r] = $val;
            }
        }
        if (!isset($cols['N'][2]) || $cols['N'][2] === '' || $cols['N'][2] === null) $cols['N'][2] = 'FREE';
        return $cols;
    }
    return [
        'B' => ['', '', '', '', ''],
        'I' => ['', '', '', '', ''],
        'N' => ['', '', 'FREE', '', ''],
        'G' => ['', '', '', '', ''],
        'O' => ['', '', '', '', ''],
    ];
}
function log_purchase_action(PDO $pdo, int $purchaseId, ?int $adminId, string $action, string $description): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bingo_purchase_audit_log (purchase_id, admin_user_id, action, description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$purchaseId, $adminId, $action, $description]);
    } catch (Throwable $e) {}
}

$current_user_id = (int)($_SESSION['admin_user_id'] ?? 0);
$current_role    = (string)($_SESSION['admin_role'] ?? '');
if (!$current_user_id) json_err('No autenticado.', 401);

$pdo = get_db_connection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === '') json_err('Acción requerida.', 400);

if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf_for_post();

try {

switch ($action) {

    case 'save_event': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $raw = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?: []) : $_POST;

        $id          = isset($data['id']) ? (int)$data['id'] : 0;
        $name        = trim((string)($data['name'] ?? ''));
        $date        = trim((string)($data['event_date'] ?? ''));
        $price_local = (float)($data['price_local'] ?? 0);

        if ($name === '' || $date === '' || $price_local <= 0) {
            json_err('Datos inválidos para el evento.', 400);
        }

        // Ya no guardamos price_usd; la conversión será en tiempo real usando bingo_currencies.
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bingo_events SET name = ?, event_date = ?, price_local = ? WHERE id = ?");
            $stmt->execute([$name, $date, $price_local, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bingo_events (name, event_date, price_local) VALUES (?, ?, ?)");
            $stmt->execute([$name, $date, $price_local]);
        }
        json_ok(['message' => 'Evento guardado correctamente.']);
    }

    case 'adjust_card_quantity': {
        $purchaseId     = filter_input(INPUT_POST, 'purchase_id', FILTER_VALIDATE_INT);
        $quantityChange = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if (!$purchaseId || !$quantityChange) json_err('Datos inválidos.', 400);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT event_id FROM bingo_purchases WHERE id = ?");
        $stmt->execute([$purchaseId]);
        $eventId = (int)$stmt->fetchColumn();
        if (!$eventId) { $pdo->rollBack(); json_err('La compra no existe.', 400); }

        // Obtenemos precio LOCAL por cartón del evento
        $stmt = $pdo->prepare("SELECT price_local FROM bingo_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) { $pdo->rollBack(); json_err('El evento asociado no existe.', 400); }

        $pricePerCardLocal = (float)$event['price_local'];

        // Moneda por defecto (real-time). Si no es USD, USD = Local / rate.
        $stmtCur = $pdo->query("SELECT code, rate FROM bingo_currencies WHERE is_default = 1 LIMIT 1");
        $cur     = $stmtCur->fetch(PDO::FETCH_ASSOC) ?: ['code' => 'USD', 'rate' => 1];
        $code    = (string)($cur['code'] ?? 'USD');
        $rate    = (float)($cur['rate'] ?? 1);
        $pricePerCardUsd = ($code === 'USD') ? $pricePerCardLocal : ($pricePerCardLocal / ($rate > 0 ? $rate : 1));

        // Cantidad de cartones actual
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bingo_cards WHERE purchase_id = ?");
        $stmt->execute([$purchaseId]);
        $currentCardCount = (int)$stmt->fetchColumn();

        $log_description = '';

        if ($quantityChange > 0) {
            $generator = null;
            try { $generator = new BingoCardGenerator($pdo); }
            catch (Throwable $e) { try { $generator = new BingoCardGenerator(); } catch (Throwable $e2) {} }
            if (!$generator) { $pdo->rollBack(); json_err('No se pudo instanciar el generador de cartones.', 500); }

            $stmt_insert = $pdo->prepare("INSERT INTO bingo_cards (purchase_id, event_id, card_code, card_json) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < $quantityChange; $i++) {
                $card = $generator->generate();
                if (!is_array($card)) { $pdo->rollBack(); json_err('No se pudo generar el cartón.', 500); }
                $codeCard = (string)($card['code'] ?? '');
                $numbers  = normalize_numbers($card);
                if ($codeCard === '') $codeCard = 'B-' . (1000 + time() % 100000) . '-' . chr(rand(65, 90));
                $stmt_insert->execute([$purchaseId, $eventId, $codeCard, json_encode($numbers, JSON_UNESCAPED_UNICODE)]);
            }
            $log_description = "Añadió {$quantityChange} cartón(es). Cantidad anterior: {$currentCardCount}.";
        } else {
            $cardsToRemove = abs($quantityChange);
            if ($currentCardCount - $cardsToRemove < 1) {
                $pdo->rollBack(); json_err('No se pueden quitar tantos cartones. Debe quedar al menos 1.', 400);
            }
            $stmtSel = $pdo->prepare("SELECT id FROM bingo_cards WHERE purchase_id = ? ORDER BY id DESC LIMIT ?");
            $stmtSel->bindValue(1, $purchaseId, PDO::PARAM_INT);
            $stmtSel->bindValue(2, $cardsToRemove, PDO::PARAM_INT);
            $stmtSel->execute();
            $ids = $stmtSel->fetchAll(PDO::FETCH_COLUMN);
            if (count($ids) < $cardsToRemove) { $pdo->rollBack(); json_err('No hay suficientes cartones para eliminar.', 400); }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtDel = $pdo->prepare("DELETE FROM bingo_cards WHERE id IN ($placeholders)");
            $stmtDel->execute($ids);

            $log_description = "Quitó {$cardsToRemove} cartón(es). Cantidad anterior: {$currentCardCount}.";
        }

        // Nuevo conteo
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bingo_cards WHERE purchase_id = ?");
        $stmt->execute([$purchaseId]);
        $newCardCount = (int)$stmt->fetchColumn();

        // Totales recalculados en tiempo real
        $newTotalLocal = $newCardCount * $pricePerCardLocal;
        $newTotalUsd   = $newCardCount * $pricePerCardUsd;

        $stmt_update = $pdo->prepare("UPDATE bingo_purchases SET total_local = ?, total_usd = ? WHERE id = ?");
        $stmt_update->execute([$newTotalLocal, $newTotalUsd, $purchaseId]);

        log_purchase_action($pdo, $purchaseId, $current_user_id, 'adjust_quantity', $log_description);

        $pdo->commit();

        json_ok([
            'message' => 'Cantidad de cartones actualizada con éxito.',
            'new_card_count' => $newCardCount,
            'new_total_local' => number_format((float)$newTotalLocal, 2, '.', ''),
            'new_total_usd'   => number_format((float)$newTotalUsd, 2, '.', ''),
        ]);
    }

    case 'get_cartons_for_purchase': {
        $purchaseId = filter_input(INPUT_GET, 'purchase_id', FILTER_VALIDATE_INT);
        if (!$purchaseId) json_err('ID de compra inválido.', 400);

        $stmt = $pdo->prepare("
            SELECT c.card_json, c.card_code, e.name as event_name
            FROM bingo_cards c
            LEFT JOIN bingo_purchases p ON c.purchase_id = p.id
            LEFT JOIN bingo_events e ON p.event_id = e.id
            WHERE c.purchase_id = ?
        ");
        $stmt->execute([$purchaseId]);
        $cards_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$cards_data) json_err('No se encontraron cartones para esta compra.', 404);

        $renderer   = new BingoCardRenderer();
        $cards_html = [];
        foreach ($cards_data as $card) {
            $raw     = json_decode($card['card_json'], true);
            $numbers = normalize_numbers($raw);
            $cards_html[] = $renderer->renderHtml($numbers, $card['card_code'], null, $card['event_name']);
        }
        json_ok(['cards' => $cards_html]);
    }

    case 'get_payment_updates': {
        // Sin cambios estructurales aquí; sigue devolviendo totales guardados de la compra.
        $last_check_timestamp = $_GET['since'] ?? date('Y-m-d H:i:s', time() - 30);

        $stmt_new = $pdo->prepare("
            SELECT p.id, p.owner_name, p.owner_email, p.payment_ref, p.created_at, p.status,
                   p.total_local, p.total_usd, p.payment_receipt_path, e.name as event_name, COUNT(c.id) as card_count
            FROM bingo_purchases p
            LEFT JOIN bingo_events e ON p.event_id = e.id
            LEFT JOIN bingo_cards c ON p.id = c.purchase_id
            WHERE p.status = 'pending' AND p.created_at > ?
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        $stmt_new->execute([$last_check_timestamp]);
        $new_purchases = $stmt_new->fetchAll(PDO::FETCH_ASSOC);

        $pending_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);
        $approved_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'approved'")->fetchColumn() ?: 0);
        $rejected_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'rejected'")->fetchColumn() ?: 0);
        $history_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchase_audit_log")->fetchColumn() ?: 0);
        $total_pending  = (float)($pdo->query("SELECT SUM(total_local) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);

        json_ok([
            'new_purchases' => $new_purchases,
            'stats' => [
                'pending'  => $pending_count,
                'approved' => $approved_count,
                'rejected' => $rejected_count,
                'history'  => $history_count,
                'total_pending' => $total_pending,
            ]
        ]);
    }

    case 'update_purchase_status': {
        $purchaseId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $newStatus  = trim((string)($_POST['status'] ?? ''));
        if (!$purchaseId || !in_array($newStatus, ['approved','rejected'], true) || !$current_user_id) {
            json_err('Datos inválidos.', 400);
        }

        $stmt_status = $pdo->prepare("SELECT status FROM bingo_purchases WHERE id = ?");
        $stmt_status->execute([$purchaseId]);
        $oldStatus = (string)($stmt_status->fetchColumn() ?: '');

        $updateQuery = "UPDATE bingo_purchases SET status = ?, processed_by_admin_id = ?, processed_at = NOW() WHERE id = ?";

        if ($newStatus === 'approved') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$newStatus, $current_user_id, $purchaseId]);

            $stmt_data = $pdo->prepare("
                SELECT p.owner_name, p.owner_email, e.name as event_name, c.card_code, c.card_json
                FROM bingo_purchases p
                JOIN bingo_cards c ON p.id = c.purchase_id
                LEFT JOIN bingo_events e ON p.event_id = e.id
                WHERE p.id = ?
            ");
            $stmt_data->execute([$purchaseId]);
            $results = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
            if (empty($results)) { $pdo->rollBack(); json_err('No se encontraron cartones para esta compra aprobada.', 400); }

            $customerName = (string)$results[0]['owner_name'];
            $customerEmail= (string)$results[0]['owner_email'];
            $eventName    = (string)($results[0]['event_name'] ?? 'Evento General');
            $cardCount    = count($results);

            $renderer  = new BingoCardRenderer();
            $emailBody = "<h1>¡Tu pago ha sido confirmado, {$customerName}!</h1>"
                       . "<p>Aquí tienes tus {$cardCount} cartones para el evento <strong>{$eventName}</strong>. ¡Mucha suerte!</p><hr>";
            foreach ($results as $card) {
                $raw     = json_decode($card['card_json'], true);
                $numbers = normalize_numbers($raw);
                $emailBody .= $renderer->renderHtml($numbers, (string)$card['card_code'], null, $eventName);
                $emailBody .= "<hr>";
            }

            $sent = send_bingo_email($customerEmail, $customerName, "Tus {$cardCount} Cartones de Bingo están listos", $emailBody);
            if (!$sent) { $pdo->rollBack(); json_err('Error al enviar el correo de confirmación.', 500); }

            $log_description = "Cambió el estado de '{$oldStatus}' a '{$newStatus}'.";
            log_purchase_action($pdo, $purchaseId, $current_user_id, 'status_changed', $log_description);

            $pdo->commit();

            $pending_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);
            $approved_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'approved'")->fetchColumn() ?: 0);
            $rejected_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'rejected'")->fetchColumn() ?: 0);
            $history_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchase_audit_log")->fetchColumn() ?: 0);
            $total_pending  = (float)($pdo->query("SELECT SUM(total_local) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);

            json_ok([
                'message' => 'Compra aprobada y correo enviado.',
                'stats' => [
                    'pending'  => $pending_count,
                    'approved' => $approved_count,
                    'rejected' => $rejected_count,
                    'history'  => $history_count,
                    'total_pending' => $total_pending,
                ]
            ]);

        } else {
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$newStatus, $current_user_id, $purchaseId]);

            $log_description = "Cambió el estado de '{$oldStatus}' a '{$newStatus}'.";
            log_purchase_action($pdo, $purchaseId, $current_user_id, 'status_changed', $log_description);

            $pending_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);
            $approved_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'approved'")->fetchColumn() ?: 0);
            $rejected_count = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchases WHERE status = 'rejected'")->fetchColumn() ?: 0);
            $history_count  = (int)($pdo->query("SELECT COUNT(*) FROM bingo_purchase_audit_log")->fetchColumn() ?: 0);
            $total_pending  = (float)($pdo->query("SELECT SUM(total_local) FROM bingo_purchases WHERE status = 'pending'")->fetchColumn() ?: 0);

            json_ok([
                'message' => 'Compra rechazada.',
                'stats' => [
                    'pending'  => $pending_count,
                    'approved' => $approved_count,
                    'rejected' => $rejected_count,
                    'history'  => $history_count,
                    'total_pending' => $total_pending,
                ]
            ]);
        }
    }

    case 'preview_carton': {
        $raw = file_get_contents('php://input');
        $payload = $raw ? (json_decode($raw, true) ?: []) : $_POST;

        $settings_data = [
            'header_color'      => (string)($payload['header_color']      ?? '#ef4444'),
            'header_text_color' => (string)($payload['header_text_color'] ?? '#ffffff'),
            'free_cell_color'   => (string)($payload['free_cell_color']   ?? '#ef4444'),
            'free_text_color'   => (string)($payload['free_text_color']   ?? '#ffffff'),
            'grid_style'        => (string)($payload['grid_style']        ?? 'solid'),
            'border_color'      => (string)($payload['border_color']      ?? '#ef4444'),
            'number_color'      => (string)($payload['number_color']      ?? '#2d3748'),
            'font_family'       => (string)($payload['font']              ?? 'Poppins, Arial, sans-serif'),
            'center_content'    => (string)($payload['center_content']    ?? 'FREE'),
        ];

        $fakeCardData = [
            'B' => [1, 7, 13, 8, 2],
            'I' => [18, 21, 24, 25, 29],
            'N' => [31, 33, 'FREE', 40, 44],
            'G' => [48, 51, 53, 58, 60],
            'O' => [62, 65, 68, 71, 75],
        ];

        $renderer = new BingoCardRenderer();
        $html     = $renderer->renderHtml($fakeCardData, 'B-PREVIEW-X', $settings_data, 'Evento de Muestra');

        json_ok(['html' => $html]);
    }

    case 'get_purchase_history': {
        $purchaseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$purchaseId) json_err('ID de compra inválido.', 400);

        $stmt = $pdo->prepare("
            SELECT a.description, a.created_at, u.username
            FROM bingo_purchase_audit_log a
            JOIN bingo_admin_users u ON a.admin_user_id = u.id
            WHERE a.purchase_id = ?
            ORDER BY a.created_at DESC
            LIMIT 200
        ");
        $stmt->execute([$purchaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_ok(['history' => $rows ?: []]);
    }

    case 'toggle_event_status': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $id        = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);
        if (!$id || $is_active === null) json_err('Datos inválidos.', 400);

        $stmt = $pdo->prepare("UPDATE bingo_events SET is_active = ? WHERE id = ?");
        $stmt->execute([(int)$is_active, $id]);

        json_ok(['message' => 'Estado del evento actualizado.']);
    }

    case 'save_settings': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $raw = file_get_contents('php://input');
        $payload = $raw ? (json_decode($raw, true) ?: []) : $_POST;

        $allowed = [
            'carton_header_color'      => 'header_color',
            'carton_header_text_color' => 'header_text_color',
            'carton_free_cell_color'   => 'free_cell_color',
            'carton_free_text_color'   => 'free_text_color',
            'carton_grid_style'        => 'grid_style',
            'carton_border_color'      => 'border_color',
            'carton_number_color'      => 'number_color',
            'carton_font'              => 'font',
            'carton_center_content'    => 'center_content',
            'carton_center_is_free'    => 'center_is_free',
            'show_decimals'            => 'show_decimals',
        ];

        $stmt = $pdo->prepare("
            INSERT INTO bingo_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($allowed as $dbKey => $inKey) {
            if (isset($payload[$inKey])) {
                $val = (string)$payload[$inKey];
                $stmt->execute([$dbKey, $val]);
            }
        }
        json_ok(['message' => 'Ajustes guardados.']);
    }

    case 'save_payment_method': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name    = trim((string)($_POST['name'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        if ($name === '' || $details === '') json_err('Nombre y detalles son obligatorios.', 400);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bingo_payment_methods SET name = ?, details = ? WHERE id = ?");
            $stmt->execute([$name, $details, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bingo_payment_methods (name, details) VALUES (?, ?)");
            $stmt->execute([$name, $details]);
        }
        json_ok(['message' => 'Método de pago guardado.']);
    }

    case 'toggle_payment_method_status': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $id        = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);
        if (!$id || $is_active === null) json_err('Datos inválidos.', 400);

        $stmt = $pdo->prepare("UPDATE bingo_payment_methods SET is_active = ? WHERE id = ?");
        $stmt->execute([(int)$is_active, $id]);

        json_ok(['message' => 'Estado del método de pago actualizado.']);
    }

    case 'save_currency': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $rate = trim((string)($_POST['rate'] ?? ''));
        if (!$id || !is_numeric($rate) || (float)$rate <= 0) {
            json_err('ID o tasa de cambio inválida.', 400);
        }
        // Guardamos “1 USD = rate (moneda default)”
        $stmt = $pdo->prepare("UPDATE bingo_currencies SET rate = ? WHERE id = ?");
        $stmt->execute([(float)$rate, $id]);
        json_ok(['message' => 'Tasa de cambio actualizada.']);
    }

    case 'set_default_currency': {
        if ($current_role !== 'admin') json_err('Permisos insuficientes.', 403);
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) json_err('ID inválido.', 400);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE bingo_currencies SET is_default = 0")->execute();
        $stmt = $pdo->prepare("UPDATE bingo_currencies SET is_default = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        json_ok(['message' => 'Moneda predeterminada actualizada.']);
    }

    default:
        json_err('Acción no soportada.', 404);
}

} catch (Throwable $e) {
    json_err('Error inesperado: ' . $e->getMessage(), 500);
}