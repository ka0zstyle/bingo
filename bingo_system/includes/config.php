<?php
// Protección: previene el acceso directo a este archivo.
if (!defined('BINGO_SYSTEM')) {
    die('Acceso denegado');
}

/**
 * ARCHIVO DE CONFIGURACIÓN CENTRAL PROFESIONAL
 */

// 1. ENTORNO Y URLS
define('APP_ENV', 'development'); // Cambiar a 'production' en el servidor real
define('BASE_URL', '/bingo_system/'); // URL base de tu sistema de bingo
define('LOGO_URL', BASE_URL . 'assets/logo.webp'); // Ruta a tu logo

// 2. BASE DE DATOS
define('DB_HOST', 'sistemxrifadb.mysql.db');
define('DB_NAME', 'sistemxrifadb');
define('DB_USER', 'sistemxrifadb');
define('DB_PASS', 'Khiskhere123'); // <-- ¡TU CONTRASEÑA REAL VA AQUÍ!

// 3. ENVÍO DE CORREO (SMTP)
define('MAIL_HOST', 'ssl0.ovh.net');
define('MAIL_PORT', 587);
define('MAIL_SMTPSECURE', 'tls');
define('MAIL_USERNAME', 'soporte@sistemaderifasmaracay.com');
define('MAIL_PASSWORD', 'Khiskhere1!'); // <-- ¡TU CONTRASEÑA REAL VA AQUÍ!
define('MAIL_FROM_ADDRESS', 'soporte@sistemaderifasmaracay.com');
define('MAIL_FROM_NAME', 'Bingo Maracay');

// 4. REDES SOCIALES Y CONTACTO (Tomado de tu config original)
define('CONTACT_PHONE', '+584243529962');
define('WHATSAPP_PHONE', '584243529962');
define('FACEBOOK_URL', 'https://www.facebook.com/profile.php?id=61580861197642');
define('INSTAGRAM_URL', 'https://www.instagram.com/sistemaderifasmaracay');
define('TIKTOK_URL', 'https://www.tiktok.com/@tusistemaderifas');

// 5. ZONA HORARIA Y MANEJO DE ERRORES
date_default_timezone_set('America/Caracas');

if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}