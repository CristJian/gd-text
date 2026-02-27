<?php

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Plugin
{
    private static $instance;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post_gdtp_template', array($this, 'save_template_meta'));

        add_shortcode('gd_text_poster', array($this, 'render_shortcode'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_assets'));

        add_action('wp_ajax_gdtp_preview', array($this, 'handle_preview'));
        add_action('wp_ajax_gdtp_generate', array($this, 'handle_generate'));
        add_action('wp_ajax_nopriv_gdtp_generate', array($this, 'handle_generate'));
    }

    public function register_post_type()
    {
        register_post_type('gdtp_template', array(
            'labels' => array(
                'name' => __('Posters', 'gdtp'),
                'singular_name' => __('Poster', 'gdtp'),
                'add_new_item' => __('Crear nuevo poster', 'gdtp'),
                'edit_item' => __('Editar poster', 'gdtp'),
                'menu_name' => __('Posters Dinámicos', 'gdtp'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-format-image',
            'supports' => array('title'),
        ));
    }

    public function register_meta_box()
    {
        add_meta_box(
            'gdtp_template_builder',
            __('Configuración del Poster', 'gdtp'),
            array($this, 'render_meta_box'),
            'gdtp_template',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('gdtp_save_template', 'gdtp_nonce');
        $config = get_post_meta($post->ID, '_gdtp_config', true);
        $config = is_array($config) ? $config : array();

        $defaults = array(
            'format' => '1:1',
            'width' => 1080,
            'height' => 1080,
            'background_color' => '#0f172a',
            'background_image_id' => 0,
            'font_file' => '',
            'elements' => array(
                array(
                    'type' => 'text',
                    'label' => 'Título principal',
                    'text' => '{user_name}',
                    'x' => 120,
                    'y' => 420,
                    'width' => 840,
                    'height' => 180,
                    'font_size' => 68,
                    'color' => '#ffffff',
                    'align_x' => 'center',
                    'align_y' => 'center',
                ),
                array(
                    'type' => 'photo',
                    'label' => 'Foto de usuario',
                    'x' => 420,
                    'y' => 130,
                    'width' => 240,
                    'height' => 240,
                    'shape' => 'circle',
                ),
            ),
            'enable_download' => 1,
            'enable_share' => 1,
            'enable_pdf' => 1,
        );

        $config = wp_parse_args($config, $defaults);
        ?>
        <div class="gdtp-builder" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
            <p><?php esc_html_e('Define formato, fondo y elementos. Puedes usar variables: {user_name}, {email}, {date}.', 'gdtp'); ?></p>

            <label><strong><?php esc_html_e('Formato', 'gdtp'); ?></strong></label>
            <select name="gdtp_format" class="gdtp-format">
                <option value="1:1" <?php selected($config['format'], '1:1'); ?>>1:1 (1080x1080)</option>
                <option value="9:16" <?php selected($config['format'], '9:16'); ?>>9:16 (1080x1920)</option>
                <option value="16:9" <?php selected($config['format'], '16:9'); ?>>16:9 (1920x1080)</option>
                <option value="custom" <?php selected($config['format'], 'custom'); ?>>Custom</option>
            </select>

            <div class="gdtp-size">
                <label>W: <input type="number" name="gdtp_width" value="<?php echo esc_attr((int) $config['width']); ?>" /></label>
                <label>H: <input type="number" name="gdtp_height" value="<?php echo esc_attr((int) $config['height']); ?>" /></label>
            </div>

            <div class="gdtp-background">
                <label><?php esc_html_e('Color de fondo', 'gdtp'); ?>
                    <input type="text" name="gdtp_background_color" value="<?php echo esc_attr($config['background_color']); ?>" />
                </label>
                <label><?php esc_html_e('ID imagen de fondo (Media Library)', 'gdtp'); ?>
                    <input type="number" name="gdtp_background_image_id" value="<?php echo esc_attr((int) $config['background_image_id']); ?>" />
                </label>
                <label><?php esc_html_e('Ruta fuente TTF/OTF (opcional)', 'gdtp'); ?>
                    <input type="text" name="gdtp_font_file" value="<?php echo esc_attr($config['font_file']); ?>" class="widefat" />
                </label>
            </div>

            <h4><?php esc_html_e('Elementos (JSON)', 'gdtp'); ?></h4>
            <textarea name="gdtp_elements" rows="14" class="widefat code"><?php echo esc_textarea(wp_json_encode($config['elements'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>

            <p>
                <label><input type="checkbox" name="gdtp_enable_download" value="1" <?php checked((int) $config['enable_download'], 1); ?> /> <?php esc_html_e('Habilitar descarga', 'gdtp'); ?></label>
                <label><input type="checkbox" name="gdtp_enable_share" value="1" <?php checked((int) $config['enable_share'], 1); ?> /> <?php esc_html_e('Habilitar compartir', 'gdtp'); ?></label>
                <label><input type="checkbox" name="gdtp_enable_pdf" value="1" <?php checked((int) $config['enable_pdf'], 1); ?> /> <?php esc_html_e('Habilitar PDF (requiere Dompdf)', 'gdtp'); ?></label>
            </p>

            <h4><?php esc_html_e('Vista previa', 'gdtp'); ?></h4>
            <p>
                <button type="button" class="button button-secondary gdtp-preview-button" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php esc_html_e('Generar preview', 'gdtp'); ?></button>
            </p>
            <div class="gdtp-preview-wrap"><img class="gdtp-preview-image" alt="Preview" /></div>

            <p><em><?php esc_html_e('Shortcode:', 'gdtp'); ?> [gd_text_poster id="<?php echo esc_attr($post->ID); ?>"]</em></p>
        </div>
        <?php
    }

    public function save_template_meta($post_id)
    {
        if (!isset($_POST['gdtp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gdtp_nonce'])), 'gdtp_save_template')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $format = isset($_POST['gdtp_format']) ? sanitize_text_field(wp_unslash($_POST['gdtp_format'])) : '1:1';
        $width = isset($_POST['gdtp_width']) ? max(100, (int) $_POST['gdtp_width']) : 1080;
        $height = isset($_POST['gdtp_height']) ? max(100, (int) $_POST['gdtp_height']) : 1080;
        $backgroundColor = isset($_POST['gdtp_background_color']) ? sanitize_hex_color(wp_unslash($_POST['gdtp_background_color'])) : '#000000';
        $backgroundImageId = isset($_POST['gdtp_background_image_id']) ? (int) $_POST['gdtp_background_image_id'] : 0;
        $fontFile = isset($_POST['gdtp_font_file']) ? sanitize_text_field(wp_unslash($_POST['gdtp_font_file'])) : '';

        $elementsRaw = isset($_POST['gdtp_elements']) ? wp_unslash($_POST['gdtp_elements']) : '[]';
        $elements = json_decode($elementsRaw, true);
        if (!is_array($elements)) {
            $elements = array();
        }

        $config = array(
            'format' => $format,
            'width' => $width,
            'height' => $height,
            'background_color' => $backgroundColor ?: '#000000',
            'background_image_id' => $backgroundImageId,
            'font_file' => $fontFile,
            'elements' => $elements,
            'enable_download' => isset($_POST['gdtp_enable_download']) ? 1 : 0,
            'enable_share' => isset($_POST['gdtp_enable_share']) ? 1 : 0,
            'enable_pdf' => isset($_POST['gdtp_enable_pdf']) ? 1 : 0,
        );

        update_post_meta($post_id, '_gdtp_config', $config);
    }

    public function enqueue_admin_assets($hook)
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'gdtp_template') {
            return;
        }

        wp_enqueue_style('gdtp-admin', GDTP_URL . 'assets/css/admin.css', array(), GDTP_VERSION);
        wp_enqueue_script('gdtp-admin', GDTP_URL . 'assets/js/admin.js', array('jquery'), GDTP_VERSION, true);

        wp_localize_script('gdtp-admin', 'GDTPAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdtp_preview_nonce'),
        ));
    }

    public function enqueue_front_assets()
    {
        wp_enqueue_style('gdtp-front', GDTP_URL . 'assets/css/front.css', array(), GDTP_VERSION);
        wp_enqueue_script('gdtp-front', GDTP_URL . 'assets/js/front.js', array('jquery'), GDTP_VERSION, true);
        wp_localize_script('gdtp-front', 'GDTPFront', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdtp_generate_nonce'),
            'strings' => array(
                'generating' => __('Generando poster...', 'gdtp'),
                'error' => __('Error al generar el poster.', 'gdtp'),
            ),
        ));
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array('id' => 0), $atts, 'gd_text_poster');
        $id = (int) $atts['id'];
        if (!$id) {
            return '';
        }

        $config = get_post_meta($id, '_gdtp_config', true);
        if (!is_array($config)) {
            return '';
        }

        ob_start();
        ?>
        <div class="gdtp-front" data-template-id="<?php echo esc_attr($id); ?>" data-download="<?php echo esc_attr((int) $config['enable_download']); ?>" data-share="<?php echo esc_attr((int) $config['enable_share']); ?>" data-pdf="<?php echo esc_attr((int) $config['enable_pdf']); ?>">
            <div class="gdtp-form">
                <label><?php esc_html_e('Nombre', 'gdtp'); ?>
                    <input type="text" name="user_name" placeholder="Juan Pérez" required>
                </label>
                <label><?php esc_html_e('Email', 'gdtp'); ?>
                    <input type="email" name="email" placeholder="correo@ejemplo.com">
                </label>
                <label><?php esc_html_e('Foto (opcional)', 'gdtp'); ?>
                    <input type="file" name="photo" accept="image/*">
                </label>
                <div class="gdtp-actions">
                    <button class="button gdtp-generate"><?php esc_html_e('Generar', 'gdtp'); ?></button>
                    <button class="button gdtp-download" style="display:none;"><?php esc_html_e('Descargar PNG', 'gdtp'); ?></button>
                    <button class="button gdtp-download-pdf" style="display:none;"><?php esc_html_e('Descargar PDF', 'gdtp'); ?></button>
                    <button class="button gdtp-share" style="display:none;"><?php esc_html_e('Compartir', 'gdtp'); ?></button>
                </div>
                <p class="gdtp-status"></p>
            </div>
            <div class="gdtp-result">
                <img alt="Poster generado" class="gdtp-result-image" style="display:none;" />
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function handle_preview()
    {
        check_ajax_referer('gdtp_preview_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('No autorizado.', 'gdtp')), 403);
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $config = get_post_meta($postId, '_gdtp_config', true);
        if (!is_array($config)) {
            wp_send_json_error(array('message' => __('Plantilla inválida.', 'gdtp')), 400);
        }

        $renderer = new GDTP_Renderer();
        $result = $renderer->render($config, array(
            'user_name' => 'Usuario Demo',
            'email' => 'demo@example.com',
            'date' => wp_date('Y-m-d'),
            'photo_path' => '',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array('url' => $result['url']));
    }

    public function handle_generate()
    {
        check_ajax_referer('gdtp_generate_nonce', 'nonce');

        $postId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $config = get_post_meta($postId, '_gdtp_config', true);
        if (!is_array($config)) {
            wp_send_json_error(array('message' => __('Plantilla inválida.', 'gdtp')), 400);
        }

        $userName = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        $photoPath = '';
        if (!empty($_FILES['photo']) && !empty($_FILES['photo']['tmp_name'])) {
            $uploaded = wp_handle_upload($_FILES['photo'], array('test_form' => false));
            if (!isset($uploaded['error']) && !empty($uploaded['file'])) {
                $photoPath = $uploaded['file'];
            }
        }

        $renderer = new GDTP_Renderer();
        $result = $renderer->render($config, array(
            'user_name' => $userName,
            'email' => $email,
            'date' => wp_date('Y-m-d'),
            'photo_path' => $photoPath,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        $response = array('png' => $result['url']);

        if (!empty($config['enable_pdf'])) {
            $pdf = $renderer->build_pdf($result['path']);
            if (!is_wp_error($pdf)) {
                $response['pdf'] = $pdf['url'];
            }
        }

        wp_send_json_success($response);
    }
}

require_once GDTP_PATH . 'includes/class-gdtp-renderer.php';
