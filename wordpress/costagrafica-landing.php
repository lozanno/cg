<?php
/**
 * Plugin Name: Costa Gráfica Landing
 * Description: Muestra la página onepage de Costa Gráfica en la portada. Desactívalo para volver al sitio de WordPress.
 * Version: 1.0.0
 * Author: Costa Gráfica
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', function () {
    if (!is_front_page() && !is_home()) {
        return;
    }

    $file = __DIR__ . '/site/index.html';
    if (!is_readable($file)) {
        return;
    }

    $base = plugin_dir_url(__FILE__) . 'site/';
    $html = file_get_contents($file);

    // Rutas relativas del HTML -> URLs del plugin.
    $html = str_replace(
        array('"logo/', '"fonts/', '"https://costagrafica.com/logo/'),
        array('"' . $base . 'logo/', '"' . $base . 'fonts/', '"' . $base . 'logo/'),
        $html
    );

    status_header(200);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}, 0);
