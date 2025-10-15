<?php
if (!defined('BINGO_SYSTEM')) {
    die('Acceso denegado');
}

/**
 * Inicia la sesión si aún no está activa y genera un token CSRF si no existe.
 */
function initialize_session_and_csrf() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Lee y cachea el cuerpo JSON de la request una única vez.
 * Devuelve un array asociativo o null si no hay JSON válido.
 */
function get_json_body(): ?array {
    static $parsed = false;
    static $json = null;

    if ($parsed) {
        return $json;
    }
    $parsed = true;

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $json = null;
        return $json;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $json = null;
        return $json;
    }

    $json = $decoded;
    return $json;
}

/**
 * Valida el token CSRF enviado por POST, GET o en el cuerpo JSON (application/json).
 * - Devuelve true si es válido.
 * - Si es inválido, responde 403 en JSON y termina la ejecución.
 */
function validate_csrf_token(): bool {
    $token_key = 'csrf_token';
    $token = null;

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');

    if ($method === 'POST') {
        // Si es JSON, intentar leerlo del body
        if (stripos($contentType, 'application/json') !== false) {
            $data = get_json_body();
            if (is_array($data) && array_key_exists($token_key, $data)) {
                $token = $data[$token_key];
            }
        }
        // Fallback a POST tradicional
        if ($token === null && isset($_POST[$token_key])) {
            $token = $_POST[$token_key];
        }
    } else {
        if (isset($_GET[$token_key])) {
            $token = $_GET[$token_key];
        }
    }

    if (!$token || !isset($_SESSION[$token_key]) || !hash_equals($_SESSION[$token_key], $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido o ausente.']);
        exit;
    }

    return true;
}

/**
 * Una función de atajo para imprimir HTML de forma segura.
 *
 * @param string|null $string El texto a imprimir.
 */
function e(?string $string): void {
    echo htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Registra una acción de auditoría para una compra.
 *
 * @param PDO $pdo La conexión a la base de datos.
 * @param int $purchase_id El ID de la compra afectada.
 * @param int $admin_id El ID del admin/mod que realiza la acción.
 * @param string $action_type El tipo de acción (ej: 'status_changed').
 * @param string $description Una descripción legible del cambio.
 */
function log_purchase_action(PDO $pdo, int $purchase_id, int $admin_id, string $action_type, string $description): void {
    $stmt = $pdo->prepare(
        "INSERT INTO bingo_purchase_audit_log (purchase_id, admin_user_id, action_type, description) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$purchase_id, $admin_id, $action_type, $description]);
}