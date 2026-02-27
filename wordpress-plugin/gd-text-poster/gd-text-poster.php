<?php
/**
 * Plugin Name: GD Text Dynamic Poster
 * Description: Crea posters dinámicos (1:1, 9:16, 16:9) con texto y foto de usuario usando gd-text. Incluye shortcode, previsualización, descarga y compartir.
 * Version: 1.0.0
 * Author: GD Text Team
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GDTP_VERSION', '1.0.0');
define('GDTP_FILE', __FILE__);
define('GDTP_PATH', plugin_dir_path(__FILE__));
define('GDTP_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'GDText\\') === 0) {
        $base = GDTP_PATH . 'includes/gdtext/';
        $relative = str_replace('GDText\\', '', $class);
        $file = $base . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    if (strpos($class, 'GDTP_') === 0) {
        $file = GDTP_PATH . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

require_once GDTP_PATH . 'includes/class-gdtp-plugin.php';
GDTP_Plugin::instance();
