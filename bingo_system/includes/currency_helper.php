<?php
if (!defined('BINGO_SYSTEM')) { die('Acceso denegado'); }

/**
 * Cache simple por request.
 */
static $currency_config = null;

/**
 * Obtiene la configuración de la moneda por defecto.
 * Se asume que guardas: 1 USD = rate (moneda default).
 * Si la default es VES con rate=197.5 => 1 USD = 197.5 VES.
 */
function get_currency_settings(): array {
    global $currency_config;
    if ($currency_config !== null) {
        return $currency_config;
    }

    $pdo = get_db_connection();

    // Lee la moneda por defecto
    $stmt_currency = $pdo->query("SELECT code, symbol, rate FROM bingo_currencies WHERE is_default = 1 LIMIT 1");
    $default_currency = $stmt_currency->fetch(PDO::FETCH_ASSOC);

    // Ajuste de decimales para el formateo público
    $stmt_decimals = $pdo->query("SELECT setting_value FROM bingo_settings WHERE setting_key = 'show_decimals' LIMIT 1");
    $show_decimals = $stmt_decimals->fetchColumn();

    if (!$default_currency) {
        // Valores de respaldo razonables (VES como ejemplo)
        $default_currency = ['code' => 'VES', 'symbol' => 'Bs.', 'rate' => 197.5];
    }
    if ($show_decimals === false) {
        $show_decimals = '1';
    }

    $currency_config = [
        'code'          => (string)$default_currency['code'],
        'symbol'        => (string)$default_currency['symbol'],
        'rate'          => (float)$default_currency['rate'], // 1 USD = rate <default>
        'show_decimals' => (bool)$show_decimals,
    ];
    return $currency_config;
}

/**
 * Convierte un monto en la moneda local (default) a USD, usando la tasa actual.
 * - Si default=USD: retorna el mismo valor.
 * - Si default != USD: USD = Local / rate (porque rate es "1 USD = rate Local").
 */
function local_to_usd(float $amount_local): float {
    $cfg = get_currency_settings();
    if ($cfg['code'] === 'USD') return $amount_local;
    $rate = max(1e-12, (float)$cfg['rate']);
    return $amount_local / $rate;
}

/**
 * Convierte USD a la moneda local (default).
 * - Si default=USD: retorna el mismo valor.
 * - Si default != USD: Local = USD * rate
 */
function usd_to_local(float $amount_usd): float {
    $cfg = get_currency_settings();
    if ($cfg['code'] === 'USD') return $amount_usd;
    return $amount_usd * (float)$cfg['rate'];
}

/**
 * Formatea un precio en la moneda local (símbolo, decimales).
 */
function format_local(float $price): string {
    $cfg = get_currency_settings();
    $decimals = $cfg['show_decimals'] ? 2 : 0;
    return $cfg['symbol'] . number_format($price, $decimals, ',', '.');
}

/**
 * Formatea un precio en USD.
 */
function format_usd(float $price_usd): string {
    $decimals = get_currency_settings()['show_decimals'] ? 2 : 2;
    return '$' . number_format($price_usd, $decimals, ',', '.');
}