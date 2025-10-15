<?php
if (!defined('BINGO_SYSTEM')) { die('Acceso denegado'); }

/**
 * Contiene la configuración de moneda para evitar múltiples consultas a la DB.
 * @var array|null
 */
static $currency_config = null;

/**
 * Obtiene la configuración de moneda (default currency y formato de decimales) de la base de datos.
 * Utiliza un caché estático para realizar la consulta una sola vez por petición.
 *
 * @return array La configuración de la moneda.
 */
function get_currency_settings(): array {
    global $currency_config;

    if ($currency_config !== null) {
        return $currency_config;
    }

    $pdo = get_db_connection();
    
    // --- INICIO DE LA CORRECCIÓN ---
    // Añadimos 'code' a la consulta SQL.
    $stmt_currency = $pdo->query("SELECT code, symbol, rate FROM currencies WHERE is_default = TRUE LIMIT 1");
    // --- FIN DE LA CORRECCIÓN ---
    $default_currency = $stmt_currency->fetch(PDO::FETCH_ASSOC);

    // Obtener el ajuste de decimales
    $stmt_decimals = $pdo->query("SELECT setting_value FROM bingo_settings WHERE setting_key = 'show_decimals' LIMIT 1");
    $show_decimals = $stmt_decimals->fetchColumn();

    // Si no hay configuración, usar valores seguros por defecto
    if (!$default_currency) {
        $default_currency = ['code' => 'VES', 'symbol' => 'Bs.', 'rate' => 0.02739726];
    }
    if ($show_decimals === false) {
        $show_decimals = '1';
    }

    $currency_config = [
        'code' => $default_currency['code'], // <-- Ahora 'code' siempre existirá
        'symbol' => $default_currency['symbol'],
        'rate' => (float)$default_currency['rate'],
        'show_decimals' => (bool)$show_decimals,
    ];
    
    return $currency_config;
}

/**
 * Formatea un precio dado en la moneda por defecto del sistema.
 *
 * @param float $price El precio en la moneda a formatear.
 * @param string $currency_code El código de la moneda (ej: 'VES').
 * @return string El precio formateado con el símbolo y decimales correctos.
 */
function format_price(float $price, string $currency_code): string {
    $config = get_currency_settings();
    
    $decimals = $config['show_decimals'] ? 2 : 0;
    
    return $config['symbol'] . number_format($price, $decimals, ',', '.');
}