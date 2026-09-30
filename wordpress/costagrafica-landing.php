<?php
/**
 * Plugin Name: Costa Gráfica Landing
 * Description: Muestra la página onepage de Costa Gráfica en la portada y recibe su formulario de contacto (correo + respaldo en "Mensajes"). Desactívalo para volver al sitio de WordPress.
 * Version: 1.3.0
 * Author: Costa Gráfica
 */

if (!defined('ABSPATH')) {
    exit;
}

const CG_CONTACT_TO = 'info@costagrafica.com'; // Valor por defecto; se cambia en Ajustes → Costa Gráfica.
const CG_POST_TYPE  = 'cg_mensaje';
const CG_OPTION_TO  = 'cg_contact_to';
const CG_OPTION_ON  = 'cg_form_enabled';

function cg_form_enabled()
{
    return (bool) get_option(CG_OPTION_ON, false);
}

/** Correos que reciben el formulario (separados por coma). */
function cg_contact_recipients()
{
    $emails = array_filter(array_map('trim', explode(',', (string) get_option(CG_OPTION_TO, CG_CONTACT_TO))), 'is_email');
    return $emails ? array_values($emails) : array(CG_CONTACT_TO);
}

/* ---------------------------------------------------------------------------
 * Ajustes → Costa Gráfica
 * ------------------------------------------------------------------------- */

add_action('admin_init', function () {
    register_setting('cg_settings', CG_OPTION_TO, array(
        'type'              => 'string',
        'default'           => CG_CONTACT_TO,
        'sanitize_callback' => function ($value) {
            $emails  = array_filter(array_map('trim', explode(',', (string) $value)));
            $valid   = array_filter(array_map('sanitize_email', $emails), 'is_email');
            if (!$valid || count($valid) !== count($emails)) {
                add_settings_error(CG_OPTION_TO, 'cg_invalid_email', 'Revisa los correos: alguno no es válido. No se guardaron los cambios.');
                return get_option(CG_OPTION_TO, CG_CONTACT_TO);
            }
            return implode(', ', $valid);
        },
    ));

    register_setting('cg_settings', CG_OPTION_ON, array(
        'type'              => 'boolean',
        'default'           => false,
        'sanitize_callback' => function ($value) {
            return $value ? 1 : 0;
        },
    ));

    add_settings_section('cg_form', 'Formulario de contacto', '__return_false', 'costagrafica');

    add_settings_field(CG_OPTION_ON, 'Mostrar formulario', function () {
        printf(
            '<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> Mostrar el formulario de contacto en la portada</label>'
            . '<p class="description">Después de cambiarlo, limpia la caché de GoDaddy y Cloudflare para verlo en el sitio.</p>',
            esc_attr(CG_OPTION_ON),
            checked(cg_form_enabled(), true, false)
        );
    }, 'costagrafica', 'cg_form', array('label_for' => CG_OPTION_ON));

    add_settings_field(CG_OPTION_TO, 'Enviar mensajes a', function () {
        printf(
            '<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s">'
            . '<p class="description">Para varios destinatarios, sepáralos con coma.</p>',
            esc_attr(CG_OPTION_TO),
            esc_attr(implode(', ', cg_contact_recipients()))
        );
    }, 'costagrafica', 'cg_form', array('label_for' => CG_OPTION_TO));
});

add_action('admin_menu', function () {
    add_options_page('Costa Gráfica', 'Costa Gráfica', 'manage_options', 'costagrafica', function () {
        ?>
        <div class="wrap">
            <h1>Costa Gráfica</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('cg_settings');
                do_settings_sections('costagrafica');
                submit_button('Guardar cambios');
                ?>
            </form>
        </div>
        <?php
    });
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=costagrafica')) . '">Ajustes</a>');
    return $links;
});

/* ---------------------------------------------------------------------------
 * Portada
 * ------------------------------------------------------------------------- */

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

    if (!cg_form_enabled()) {
        $html = preg_replace('#\s*<!-- cg:form -->.*?<!-- /cg:form -->#s', '', $html);
    }

    // Rutas relativas del HTML -> URLs del plugin, y endpoint del formulario.
    $html = str_replace(
        array('"logo/', '"fonts/', '"https://costagrafica.com/logo/', 'var FORM_ENDPOINT = "";'),
        array(
            '"' . $base . 'logo/',
            '"' . $base . 'fonts/',
            '"' . $base . 'logo/',
            'var FORM_ENDPOINT = ' . wp_json_encode(rest_url('costagrafica/v1/contacto')) . ';',
        ),
        $html
    );

    status_header(200);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}, 0);

/* ---------------------------------------------------------------------------
 * Respaldo de mensajes en wp-admin
 * ------------------------------------------------------------------------- */

add_action('init', function () {
    register_post_type(CG_POST_TYPE, array(
        'labels' => array(
            'name'          => 'Mensajes',
            'singular_name' => 'Mensaje',
            'menu_name'     => 'Mensajes',
            'all_items'     => 'Todos los mensajes',
            'edit_item'     => 'Mensaje',
            'search_items'  => 'Buscar mensajes',
            'not_found'     => 'No hay mensajes',
        ),
        'public'       => false,
        'show_ui'      => true,
        'menu_icon'    => 'dashicons-email-alt',
        'supports'     => array('title', 'editor'),
        'capabilities' => array('create_posts' => 'do_not_allow'),
        'map_meta_cap' => true,
    ));
});

add_filter('manage_' . CG_POST_TYPE . '_posts_columns', function () {
    return array(
        'cb'       => '<input type="checkbox">',
        'title'    => 'Asunto',
        'nombre'   => 'Nombre',
        'email'    => 'Email',
        'telefono' => 'Teléfono',
        'correo'   => 'Correo enviado',
        'date'     => 'Fecha',
    );
});

add_action('manage_' . CG_POST_TYPE . '_posts_custom_column', function ($column, $post_id) {
    $value = get_post_meta($post_id, '_cg_' . $column, true);
    switch ($column) {
        case 'email':
            printf('<a href="mailto:%1$s">%1$s</a>', esc_attr($value));
            break;
        case 'telefono':
            printf('<a href="https://wa.me/%s" target="_blank" rel="noopener">%s</a>', esc_attr(preg_replace('/\D/', '', $value)), esc_html($value));
            break;
        case 'correo':
            echo $value ? 'Sí' : '<strong style="color:#b32d2e">No</strong>';
            break;
        case 'nombre':
            echo esc_html($value);
            break;
    }
}, 10, 2);

add_action('add_meta_boxes_' . CG_POST_TYPE, function ($post) {
    add_meta_box('cg_contacto', 'Datos de contacto', function ($post) {
        foreach (array('nombre' => 'Nombre', 'telefono' => 'Teléfono (WhatsApp)', 'email' => 'Email') as $key => $label) {
            printf('<p><strong>%s:</strong> %s</p>', esc_html($label), esc_html(get_post_meta($post->ID, '_cg_' . $key, true)));
        }
    }, null, 'side', 'high');
});

/* ---------------------------------------------------------------------------
 * Endpoint del formulario: POST /wp-json/costagrafica/v1/contacto
 * ------------------------------------------------------------------------- */

add_action('rest_api_init', function () {
    register_rest_route('costagrafica/v1', '/contacto', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'cg_handle_contact',
    ));
});

function cg_handle_contact(WP_REST_Request $request)
{
    if (!cg_form_enabled()) {
        return new WP_Error('cg_disabled', 'El formulario no está disponible.', array('status' => 403));
    }

    // Campo trampa: los bots lo llenan, las personas no lo ven.
    if ($request->get_param('website') !== null && $request->get_param('website') !== '') {
        return array('ok' => true);
    }

    // Máximo 5 envíos por IP cada 10 minutos. Detrás de Cloudflare/GoDaddy, REMOTE_ADDR es el proxy.
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0])
        ?: ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip_key = 'cg_contact_' . md5($ip);
    $count  = (int) get_transient($ip_key);
    if ($count >= 5) {
        return new WP_Error('cg_rate_limit', 'Demasiados envíos. Intenta más tarde.', array('status' => 429));
    }
    set_transient($ip_key, $count + 1, 10 * MINUTE_IN_SECONDS);

    $data = array(
        'nombre'   => mb_substr(sanitize_text_field((string) $request->get_param('nombre')), 0, 200),
        'telefono' => mb_substr(sanitize_text_field((string) $request->get_param('telefono')), 0, 50),
        'email'    => mb_substr(sanitize_email((string) $request->get_param('email')), 0, 200),
        'asunto'   => mb_substr(sanitize_text_field((string) $request->get_param('asunto')), 0, 200),
        'mensaje'  => mb_substr(sanitize_textarea_field((string) $request->get_param('mensaje')), 0, 5000),
    );

    foreach ($data as $value) {
        if ($value === '') {
            return new WP_Error('cg_invalid', 'Faltan campos.', array('status' => 400));
        }
    }
    if (!is_email($data['email'])) {
        return new WP_Error('cg_invalid', 'Email inválido.', array('status' => 400));
    }

    $post_id = wp_insert_post(array(
        'post_type'    => CG_POST_TYPE,
        'post_status'  => 'private',
        'post_title'   => $data['asunto'],
        'post_content' => $data['mensaje'],
        'meta_input'   => array(
            '_cg_nombre'   => $data['nombre'],
            '_cg_telefono' => $data['telefono'],
            '_cg_email'    => $data['email'],
        ),
    ), true);

    $body = "Nuevo mensaje desde costagrafica.com\n\n"
        . "Nombre: {$data['nombre']}\n"
        . "Teléfono (WhatsApp): {$data['telefono']}\n"
        . "Email: {$data['email']}\n"
        . "Asunto: {$data['asunto']}\n\n"
        . "{$data['mensaje']}\n";

    $sent = wp_mail(
        cg_contact_recipients(),
        'Contacto web: ' . $data['asunto'],
        $body,
        array(sprintf('Reply-To: %s <%s>', str_replace(array('<', '>', '"'), '', $data['nombre']), $data['email']))
    );

    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_cg_correo', $sent ? 1 : 0);
    }

    // Si no se pudo ni guardar ni enviar, el mensaje se perdería: avisar al usuario.
    if (is_wp_error($post_id) && !$sent) {
        return new WP_Error('cg_failed', 'No se pudo enviar.', array('status' => 500));
    }

    return array('ok' => true);
}
