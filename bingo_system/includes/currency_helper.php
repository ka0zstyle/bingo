<?php
if (!defined('BINGO_SYSTEM')) { die('Acceso denegado'); }

/**
 * Contiene la configuración de moneda para evitar múltiples consultas a la DB.
 * @var array|null
 */
static $currency_config = null;

/**
 * Obtiene la configuración de moneda (default currency y decimales).
 * Se asume que rate se guarda como: 1 USD = rate (moneda default).
 */
function get_currency_settings(): array {
    global $currency_config;

    if ($currency_config !== null) {
        return $currency_config;
    }

    $pdo = get_db_connection();

    $stmt_currency = $pdo->query("SELECT code, symbol, rate FROM bingo_currencies WHERE is_default = TRUE LIMIT 1");
    $default_currency = $stmt_currency->fetch(PDO::FETCH_ASSOC);

    $stmt_decimals = $pdo->query("SELECT setting_value FROM bingo_settings WHERE setting_key = 'show_decimals' LIMIT 1");
    $show_decimals = $stmt_decimals->fetchColumn();

    if (!$default_currency) {
        $default_currency = ['code' => 'VES', 'symbol' => 'Bs.', 'rate' => 197.5];
    }
    if ($show_decimals === false) {
        $show_decimals = '1';
    }

    $currency_config = [
        'code' => $default_currency['code'],
        'symbol' => $default_currency['symbol'],
        'rate' => (float)$default_currency['rate'], // 1 USD = rate default
        'show_decimals' => (bool)$show_decimals,
    ];

    return $currency_config;
}

/**
 * Formatea un precio dado en la moneda por defecto del sistema.
 */
function format_price(float $price, string $currency_code): string {
    $config = get_currency_settings();
    $decimals = $config['show_decimals'] ? 2 : 0;
    return $config['symbol'] . number_format($price, $decimals, ',', '.');
}