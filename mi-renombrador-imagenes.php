<?php
/**
 * Plugin Name:          Toc Toc SEO Images
 * Plugin URI:           https://toctoc.ky/
 * Description:          Renombra archivos incluyendo nombre de página/producto. Genera Título, Alt text y Leyenda únicos usando Google AI (Gemini Vision) o métodos variados. Incluye procesamiento masivo de imágenes antiguas y compresión sin pérdida visible. Soporta JPG, PNG, WebP, GIF, AVIF. Permite seleccionar el idioma de salida para la IA.
 * Version:              3.6.0
 * Author:               Toc Toc Marketing
 * Author URI:           https://toctoc.ky/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          mi-renombrador-imagenes
 * Domain Path:          /languages
 */

// Evitar acceso directo al archivo
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

// Define el nombre de la opción para guardar en la BD
define('MRI_SETTINGS_OPTION_NAME', 'mri_google_ai_options');
define('MRI_PLUGIN_SLUG', 'mi-renombrador-imagenes'); // Para identificar assets

// --- Sección: Configuración y Página de Ajustes ---

/**
 * Añade las páginas de opciones al menú de WordPress.
 */
function mri_google_ai_add_admin_menu() {
    // Página de Ajustes Generales bajo "Ajustes"
    add_options_page(
        __('Renombrador Imágenes Inteligente (Google AI + Compresión)', 'mi-renombrador-imagenes'), // Título de la página
        __('Renombrador Imágenes IA', 'mi-renombrador-imagenes'),       // Título del menú
        'manage_options',                                               // Capacidad requerida
        'mri_google_ai_settings',                                       // Slug de la página
        'mri_google_ai_options_page_html'                               // Función que muestra el HTML
    );

    // Página de Procesamiento Masivo bajo "Medios"
    add_media_page(
        __('Procesar Imágenes Antiguas (IA + Compresión)', 'mi-renombrador-imagenes'), // Título de la página
        __('Procesar Imágenes Antiguas IA', 'mi-renombrador-imagenes'), // Título del menú
        'manage_options', // Capacidad (o 'upload_files' si quieres permitir a editores?)
        'mri_bulk_process_page', // Slug único
        'mri_render_bulk_process_page' // Función que muestra el HTML
    );
}
add_action( 'admin_menu', 'mri_google_ai_add_admin_menu' );

/**
 * Registra los ajustes usando la Settings API.
 */
function mri_google_ai_settings_init() {
    register_setting(
        'mri_google_ai_options_group',   // Nombre del grupo
        MRI_SETTINGS_OPTION_NAME,        // Nombre de la opción en la BD
        'mri_google_ai_options_sanitize' // Función de sanitización
    );

    // Sección General
    add_settings_section(
        'mri_google_ai_general_section',
        __('Configuración General', 'mi-renombrador-imagenes'),
        'mri_google_ai_general_section_callback',
        'mri_google_ai_settings'
    );
    add_settings_field('enable_rename', __('Activar Renombrado', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'enable_rename', 'label' => __('Renombrar archivos usando Título Página/Producto (si existe) y Título Imagen (puede ser generado por IA).', 'mi-renombrador-imagenes')]);
    // --- Nuevos campos para compresión ---
    add_settings_field('enable_compression', __('Activar Compresión', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'enable_compression', 'label' => __('Comprimir imágenes automáticamente al subirlas o procesarlas masivamente (sin pérdida visible).', 'mi-renombrador-imagenes')]);
    add_settings_field('jpeg_quality', __('Calidad JPEG/WebP (Compresión)', 'mi-renombrador-imagenes'), 'mri_google_ai_field_number_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'jpeg_quality', 'min' => 60, 'max' => 100, 'step' => 1, 'desc' => __('Nivel de calidad para JPEG y WebP (0-100). Recomendado: 82-90 para buen balance calidad/tamaño. PNG/GIF usan compresión sin pérdida.', 'mi-renombrador-imagenes')]);
    add_settings_field('use_imagick_if_available', __('Usar Imagick si está disponible', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'use_imagick_if_available', 'label' => __('Priorizar la extensión Imagick para la compresión si está instalada en el servidor (generalmente ofrece mejores resultados). Si no, usará GD.', 'mi-renombrador-imagenes')]);
    // --- Fin Nuevos campos compresión ---
    add_settings_field('enable_alt', __('Activar Generación Alt', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'enable_alt', 'label' => __('Generar automáticamente el texto alternativo.', 'mi-renombrador-imagenes')]);
    add_settings_field('overwrite_alt', __('Sobrescribir Alt', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'overwrite_alt', 'label' => __('Reemplazar Alt text existente. Si no, solo se añade si está vacío.', 'mi-renombrador-imagenes')]);
    add_settings_field('enable_caption', __('Activar Generación Leyenda', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'enable_caption', 'label' => __('Generar automáticamente la leyenda.', 'mi-renombrador-imagenes')]);
    add_settings_field('overwrite_caption', __('Sobrescribir Leyenda', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_general_section', ['id' => 'overwrite_caption', 'label' => __('Reemplazar leyenda existente. Si no, solo se añade si está vacía.', 'mi-renombrador-imagenes')]);


    // Sección Integración Google AI (Gemini)
    add_settings_section(
        'mri_google_ai_ia_section',
        __('Integración con Google AI (Gemini)', 'mi-renombrador-imagenes'),
        'mri_google_ai_ia_section_callback',
        'mri_google_ai_settings'
    );
    add_settings_field('gemini_api_key', __('Clave API Google AI (Gemini)', 'mi-renombrador-imagenes'), 'mri_google_ai_field_text_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'gemini_api_key', 'type' => 'password', 'desc' => __('Introduce tu clave API de Google AI Studio / Google Cloud.', 'mi-renombrador-imagenes')]);
    add_settings_field('gemini_model', __('Modelo Gemini a usar', 'mi-renombrador-imagenes'), 'mri_google_ai_field_text_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'gemini_model', 'type' => 'text', 'desc' => __('Recomendado: gemini-1.5-flash-latest (rápido, multimodal) o gemini-1.5-pro-latest (más potente, multimodal).', 'mi-renombrador-imagenes')]);
    add_settings_field(
        'ai_output_language',
        __('Idioma para Metadatos IA', 'mi-renombrador-imagenes'),
        'mri_google_ai_field_select_callback', // Nuevo callback para select
        'mri_google_ai_settings',
        'mri_google_ai_ia_section',
        [
            'id' => 'ai_output_language',
            'options' => mri_get_supported_languages(), // Función para obtener idiomas
            'desc' => __('Selecciona el idioma en el que deseas que la IA genere el Título, Texto Alternativo y Leyenda.', 'mi-renombrador-imagenes')
        ]
    );
    add_settings_field('enable_ai_title', __('Usar IA para Título (Vision)', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'enable_ai_title', 'label' => __('Generar título del adjunto analizando la imagen con Google AI (requiere API Key y modelo multimodal).', 'mi-renombrador-imagenes')]);
    add_settings_field('overwrite_title', __('Sobrescribir Título', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'overwrite_title', 'label' => __('Reemplazar título existente del adjunto con el generado por IA. Si no, solo se genera si el título está vacío o es genérico (igual al nombre de archivo).', 'mi-renombrador-imagenes')]);
    add_settings_field('enable_ai_alt', __('Usar IA para Alt Text (Vision)', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'enable_ai_alt', 'label' => __('Generar Alt text analizando la imagen con Google AI (requiere API Key y modelo multimodal).', 'mi-renombrador-imagenes')]);
    add_settings_field('enable_ai_caption', __('Usar IA para Leyenda (Vision)', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'enable_ai_caption', 'label' => __('Generar leyenda analizando la imagen con Google AI (requiere API Key y modelo multimodal).', 'mi-renombrador-imagenes')]);
    add_settings_field('include_seo_in_ai_prompt', __('Incluir Contexto SEO en Prompt IA', 'mi-renombrador-imagenes'), 'mri_google_ai_field_checkbox_callback', 'mri_google_ai_settings', 'mri_google_ai_ia_section', ['id' => 'include_seo_in_ai_prompt', 'label' => __('Enviar nombre de producto/página y/o focus keyword a la IA como contexto adicional para Título/Alt/Leyenda (solo en subida nueva).', 'mi-renombrador-imagenes')]);
}
add_action( 'admin_init', 'mri_google_ai_settings_init' );

// Callbacks para secciones
function mri_google_ai_general_section_callback() {
    echo '<p>' . esc_html__('Configura las opciones de procesamiento automático (renombrado, compresión, metadatos) para las imágenes subidas.', 'mi-renombrador-imagenes') . '</p>';
}
function mri_google_ai_ia_section_callback() {
    echo '<p>' . esc_html__('Configura la integración con Google AI (Gemini) para analizar imágenes y generar Título, Alt Text y Leyendas más inteligentes. Requiere una API key de Google AI Studio o Google Cloud y un modelo multimodal (ej: gemini-1.5-flash-latest).', 'mi-renombrador-imagenes') . '</p>';
    echo '<p><strong>' . esc_html__('IMPORTANTE:', 'mi-renombrador-imagenes') . '</strong> ' . esc_html__('El uso de la API de Google AI (especialmente con análisis de imagen) puede tener costos asociados (revisa su política de precios y niveles gratuitos) y ralentizará significativamente la subida de imágenes y el procesamiento masivo.', 'mi-renombrador-imagenes') . '</p>';
}

// Callback para campos checkbox
function mri_google_ai_field_checkbox_callback( $args ) {
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $id = $args['id'];
    $label = $args['label'];
    $value = isset( $options[$id] ) ? $options[$id] : 0;
    echo '<label for="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '_' . $id ) . '">';
    echo '<input type="checkbox" id="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '_' . $id ) . '" name="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '[' . $id . ']' ) . '" value="1" ' . checked( 1, $value, false ) . ' /> ';
    echo esc_html( $label );
    echo '</label>';
    if (isset($args['desc'])) {
        echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
    }
}

// Callback para campos de texto/password
function mri_google_ai_field_text_callback( $args ) {
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $id = $args['id'];
    $type = isset($args['type']) ? $args['type'] : 'text';
    $desc = isset($args['desc']) ? $args['desc'] : '';
    $value = isset( $options[$id] ) ? $options[$id] : '';
    echo '<input type="' . esc_attr($type) . '" id="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '_' . $id ) . '" name="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '[' . $id . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
    if ($desc) {
        echo '<p class="description">' . wp_kses_post($desc) . '</p>'; // Permitir HTML básico en descripciones
    }
}

// --- NUEVO: Callback para campos numéricos ---
function mri_google_ai_field_number_callback( $args ) {
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $id = $args['id'];
    $desc = isset($args['desc']) ? $args['desc'] : '';
    $min = isset($args['min']) ? $args['min'] : 0;
    $max = isset($args['max']) ? $args['max'] : 100;
    $step = isset($args['step']) ? $args['step'] : 1;
    $value = isset( $options[$id] ) ? $options[$id] : '';

    echo '<input type="number" id="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '_' . $id ) . '" name="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '[' . $id . ']' ) . '" value="' . esc_attr( $value ) . '" class="small-text" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" />';
    if ($desc) {
        echo '<p class="description">' . wp_kses_post($desc) . '</p>';
    }
}
// --- Fin NUEVO: Callback número ---

// Callback para campos select
function mri_google_ai_field_select_callback( $args ) {
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $id = $args['id'];
    $desc = isset($args['desc']) ? $args['desc'] : '';
    $select_options = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
    $current_value = isset( $options[$id] ) ? $options[$id] : '';

    if (empty($select_options)) {
        echo '<p>' . esc_html__('Error: No se definieron opciones para este campo.', 'mi-renombrador-imagenes') . '</p>';
        return;
    }

    echo '<select id="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '_' . $id ) . '" name="' . esc_attr( MRI_SETTINGS_OPTION_NAME . '[' . $id . ']' ) . '">';
    foreach ($select_options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($current_value, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';

    if ($desc) {
        echo '<p class="description">' . wp_kses_post($desc) . '</p>';
    }
}

// Función para obtener idiomas soportados
function mri_get_supported_languages() {
    // Puedes expandir esta lista según necesites
    return [
        'es' => __('Español', 'mi-renombrador-imagenes'),
        'en' => __('Inglés', 'mi-renombrador-imagenes'),
        'fr' => __('Francés', 'mi-renombrador-imagenes'),
        'de' => __('Alemán', 'mi-renombrador-imagenes'),
        'it' => __('Italiano', 'mi-renombrador-imagenes'),
        'pt' => __('Portugués', 'mi-renombrador-imagenes'),
        // Añade más idiomas aquí si es necesario (clave => Nombre)
    ];
}

// Valores por defecto para las opciones
function mri_google_ai_get_default_options() {
    return [
        'enable_rename'            => 1,
        'enable_compression'       => 1, // Activado por defecto
        'jpeg_quality'             => 85, // Calidad por defecto
        'use_imagick_if_available' => 1, // Usar Imagick por defecto si está
        'enable_alt'               => 1,
        'overwrite_alt'            => 1,
        'enable_caption'           => 1,
        'overwrite_caption'        => 0,
        'gemini_api_key'           => '',
        'gemini_model'             => 'gemini-1.5-flash-latest',
        'ai_output_language'       => 'es', // Idioma por defecto: Español
        'enable_ai_title'          => 0,
        'overwrite_title'          => 0,
        'enable_ai_alt'            => 0,
        'enable_ai_caption'        => 0,
        'include_seo_in_ai_prompt' => 1,
    ];
}

// Sanitización de las opciones al guardar
function mri_google_ai_options_sanitize( $input ) {
    $sanitized_input = [];
    $defaults = mri_google_ai_get_default_options();
    $supported_languages = mri_get_supported_languages(); // Obtener idiomas soportados

    foreach ( array_keys($defaults) as $key ) {
        if ( $key === 'gemini_api_key' || $key === 'gemini_model' ) {
            $sanitized_input[$key] = isset( $input[$key] ) ? sanitize_text_field( trim($input[$key]) ) : '';
        } elseif ( $key === 'ai_output_language' ) { // Sanitizar idioma
             $submitted_lang = isset($input[$key]) ? sanitize_key($input[$key]) : $defaults['ai_output_language'];
             // Validar si el idioma está en nuestra lista de soportados
             $sanitized_input[$key] = array_key_exists($submitted_lang, $supported_languages) ? $submitted_lang : $defaults['ai_output_language'];
        } elseif ( $key === 'jpeg_quality' ) { // Sanitizar calidad JPEG
             $quality = isset( $input[$key] ) ? absint($input[$key]) : $defaults['jpeg_quality'];
             $sanitized_input[$key] = max( 0, min( 100, $quality ) ); // Asegurar entre 0 y 100
        } else {
            // Checkboxes
            $sanitized_input[$key] = isset( $input[$key] ) && $input[$key] == 1 ? 1 : 0;
        }
    }

    // --- Validaciones y advertencias (sin cambios aquí, pero podrían añadirse para Imagick) ---
    // ... (código de validaciones existentes para API Key y modelo) ...
     $needs_ia = $sanitized_input['enable_ai_title'] || $sanitized_input['enable_ai_alt'] || $sanitized_input['enable_ai_caption'];
     if ( $needs_ia && empty($sanitized_input['gemini_api_key']) ) {
           add_settings_error(MRI_SETTINGS_OPTION_NAME, 'missing_api_key', __('Se ha activado una función de IA pero no se ha introducido la Clave API de Google AI. La generación por IA no funcionará.', 'mi-renombrador-imagenes'), 'warning');
     }
     if ( $needs_ia && empty($sanitized_input['gemini_model']) ) {
           add_settings_error(MRI_SETTINGS_OPTION_NAME, 'missing_model', __('Se ha activado una función de IA pero no se ha especificado un Modelo Gemini. Usando el modelo por defecto: ' . $defaults['gemini_model'] . '. Introduce un modelo compatible (ej: gemini-1.5-flash-latest).', 'mi-renombrador-imagenes'), 'warning');
           if (empty($sanitized_input['gemini_model'])) $sanitized_input['gemini_model'] = $defaults['gemini_model'];
     }
     if ( $needs_ia && !empty($sanitized_input['gemini_model']) ) {
           $model_lower = strtolower($sanitized_input['gemini_model']);
           // Asumir que 1.5 y 'vision' son multimodales
           if ( strpos($model_lower, 'gemini-1.5') === false && strpos($model_lower, 'vision') === false ) {
                 add_settings_error(MRI_SETTINGS_OPTION_NAME, 'non_multimodal_model', __('ADVERTENCIA: El modelo seleccionado (' . esc_html($sanitized_input['gemini_model']) . ') podría no ser multimodal. Para analizar imágenes (generar título, alt o caption por IA basado en visión), se recomienda usar gemini-1.5-flash-latest o gemini-1.5-pro-latest. El análisis de imagen podría fallar.', 'mi-renombrador-imagenes'), 'warning');
           }
     }

    // Advertencia si Imagick se quiere usar pero no está disponible
    if ($sanitized_input['enable_compression'] && $sanitized_input['use_imagick_if_available'] && !(extension_loaded('imagick') && class_exists('Imagick'))) {
         add_settings_error(MRI_SETTINGS_OPTION_NAME, 'imagick_not_found', __('Se ha seleccionado usar Imagick para la compresión, pero la extensión no está instalada o activada en el servidor. Se usará la librería GD como alternativa para los formatos soportados (JPEG, PNG, GIF, WebP).', 'mi-renombrador-imagenes'), 'info'); // 'info' en lugar de 'warning'
    }


    return $sanitized_input;
}

// HTML de la página de opciones de ajustes
function mri_google_ai_options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors(); // Muestra errores/advertencias de sanitización ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'mri_google_ai_options_group' );
            do_settings_sections( 'mri_google_ai_settings' );
            submit_button( __('Guardar Cambios', 'mi-renombrador-imagenes') );
            ?>
        </form>
        <hr>
        <h2><?php esc_html_e('Notas Importantes', 'mi-renombrador-imagenes'); ?></h2>
        <ul>
            <li><?php esc_html_e('La compresión de imágenes intentará reducir el tamaño del archivo sin pérdida visible de calidad. Se recomienda usar Imagick (si está disponible en tu hosting) para mejores resultados.', 'mi-renombrador-imagenes'); ?></li>
            <li><?php esc_html_e('La compresión se aplica a JPG, PNG, GIF y WebP. AVIF puede ser comprimido si Imagick lo soporta en tu servidor. SVG no se comprime.', 'mi-renombrador-imagenes'); ?></li>
            <li><?php esc_html_e('La generación de metadatos con IA (Título, Alt, Leyenda) requiere una Clave API de Google AI válida y un modelo multimodal (ej: gemini-1.5-flash-latest, gemini-1.5-pro-latest).', 'mi-renombrador-imagenes'); ?></li>
            <li><?php esc_html_e('Puedes seleccionar el idioma deseado para las respuestas generadas por la IA en la sección de configuración de Google AI.', 'mi-renombrador-imagenes'); ?></li>
            <li><?php esc_html_e('El análisis de imágenes (IA) y la compresión pueden incrementar el tiempo de subida/procesamiento y consumir recursos del servidor.', 'mi-renombrador-imagenes'); ?></li>
             <li><?php esc_html_e('Las respuestas de la IA se limpian automáticamente para eliminar frases introductorias y formato básico antes de guardarlas. La limpieza intenta eliminar también información extra como estadísticas si la IA las añade.', 'mi-renombrador-imagenes'); ?></li>
             <li><?php esc_html_e('El renombrado de archivos se basa en el título del adjunto (que puede ser generado por IA si está activo) y el título de la página/producto asociado (solo en subida nueva).', 'mi-renombrador-imagenes'); ?></li>
             <li><?php esc_html_e('Para procesar imágenes antiguas (incluyendo compresión si está activa), ve a Medios -> Procesar Imágenes Antiguas IA.', 'mi-renombrador-imagenes'); ?></li>
             <li><?php esc_html_e('Los tipos de imagen soportados para análisis visual por IA incluyen JPG, PNG, WebP, GIF, AVIF. SVG no se analiza visualmente.', 'mi-renombrador-imagenes'); ?></li>
        </ul>
    </div>
    <?php
}
// --- Fin Sección: Configuración y Página de Ajustes ---


// --- Sección: Ayudante IA (Google AI - Gemini con Vision) ---
// ... (La función mri_llamar_google_ai_api y mri_clean_ai_response permanecen IGUALES) ...
/**
 * Llama a la API de Google AI (Gemini) para texto o análisis de imagen.
 */
function mri_llamar_google_ai_api( $prompt, $api_key, $model = 'gemini-1.5-flash-latest', $max_tokens = 150, $image_data_base64 = null, $image_mime_type = null ) {
    if ( empty($api_key) ) { error_log('MRI Plugin Google AI: API Key no configurada.'); return false; }
    if ( empty($model) ) { error_log('MRI Plugin Google AI: Modelo Gemini no configurado.'); return false; }
    if ( !empty($image_data_base64) && empty($image_mime_type) ) { error_log('MRI Plugin Google AI: Se proporcionaron datos de imagen pero no el tipo MIME.'); return false; }

    $api_endpoint_base = 'https://generativelanguage.googleapis.com/v1beta/models/';
    $api_url = $api_endpoint_base . $model . ':generateContent?key=' . $api_key;

    $parts = [];
    $parts[] = ['text' => $prompt];
    if (!empty($image_data_base64) && !empty($image_mime_type)) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $image_mime_type,
                'data' => $image_data_base64
            ]
        ];
    }

    $request_body = json_encode([
        'contents' => [ [ 'parts' => $parts ] ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => absint($max_tokens),
            // 'response_mime_type' => 'text/plain', // Considerar si ayuda a la limpieza
        ],
         'safetySettings' => [
             [ 'category' => 'HARM_CATEGORY_HARASSMENT',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
             [ 'category' => 'HARM_CATEGORY_HATE_SPEECH',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
             [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
             [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ]
         ]
    ]);

    $args = [
        'body'    => $request_body,
        'headers' => [ 'Content-Type'  => 'application/json', ],
        'timeout' => 90, // Timeout más largo para IA
        'sslverify' => true,
    ];

    $response = wp_remote_post( $api_url, $args );

    if ( is_wp_error( $response ) ) {
        error_log( 'MRI Plugin Google AI Error (WP_Error): ' . $response->get_error_message() );
        return false;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    $decoded_body = json_decode( $response_body, true );

    if ( $response_code !== 200 ) {
        $error_message = '(No message found)';
        if (isset($decoded_body['error']['message'])) {
             $error_message = $decoded_body['error']['message'];
        } elseif (!empty($response_body)) { $error_message = $response_body; }
        error_log( "MRI Plugin Google AI Error (HTTP $response_code): " . $error_message . " - URL: " . $api_url );
        if (isset($decoded_body['error']['details'])) { error_log("MRI Plugin Google AI Error Details: " . json_encode($decoded_body['error']['details'])); }
        return false;
    }
    if ( empty($decoded_body['candidates']) ) {
         $block_reason = isset($decoded_body['promptFeedback']['blockReason']) ? $decoded_body['promptFeedback']['blockReason'] : 'Unknown reason or empty candidates array';
         $safety_ratings_log = isset($decoded_body['promptFeedback']['safetyRatings']) ? json_encode($decoded_body['promptFeedback']['safetyRatings']) : 'N/A';
          error_log( 'MRI Plugin Google AI Error: No candidates returned. Reason: ' . $block_reason . ' SafetyRatings: ' . $safety_ratings_log . ' - Full Response: ' . mb_substr($response_body, 0, 500)); // Loguear solo parte de la respuesta larga
         return false;
    }
    if ( isset( $decoded_body['candidates'][0]['content']['parts'][0]['text'] ) ) {
        $generated_text = trim($decoded_body['candidates'][0]['content']['parts'][0]['text']);
        // Limpieza MÁS básica aquí, la principal se hace fuera
        $generated_text = preg_replace('/^```(?:json|text)?\s*/', '', $generated_text);
        $generated_text = preg_replace('/\s*```$/', '', $generated_text);
        return !empty($generated_text) ? $generated_text : false;
    } else {
         $finish_reason = isset($decoded_body['candidates'][0]['finishReason']) ? $decoded_body['candidates'][0]['finishReason'] : 'N/A';
         $safety_ratings_log = isset($decoded_body['candidates'][0]['safetyRatings']) ? json_encode($decoded_body['candidates'][0]['safetyRatings']) : 'N/A';
         error_log( 'MRI Plugin Google AI Error: Unexpected response structure or no text found. FinishReason: ' . $finish_reason . '. SafetyRatings: ' . $safety_ratings_log . ' - Full Response: ' . mb_substr($response_body, 0, 500));
         return false;
    }
}

/**
 * Limpia la respuesta de la IA eliminando frases introductorias, formato básico,
 * y trata de eliminar información extra no deseada (ej: estadísticas, texto conversacional posterior).
 */
function mri_clean_ai_response( $text ) {
    if ( empty($text) ) {
        return '';
    }

    // 1. Eliminar frases introductorias complejas (Regex existente mejorada ligeramente)
    $cleaned_text = preg_replace(
        '/^' . // Inicio de línea
        '(?:' . // Grupo de patrones alternativos (sin captura)
            // Patrón 1: Frases comunes tipo "Aquí tienes..." (adaptado)
            '(?:(?:claro|ok|perfecto|vale|bien|bueno|sure|voici|ecco|aqui\s*está),?\s*)?' .
            '(?:(?:aquí|here)\s*tienes?|este\s*es|(?:it|this)\s*is|c\'est|è\s*ecco)\b.*?' .
            '[:;]' .
            '|' .
            // Patrón 2: Frases descriptivas tipo "Un texto alternativo..." (adaptado)
            '(?:(?:[Uu]n|[Aa]n?|[Ee]l|[Ll]a|[Tt]he|[Uu]ne?)\s+' .
            '(?:texto\s+alternativo|alt\s*text|t[íi]tulo|title|leyenda|caption|descripci[oó]n|description|respuesta|answer)\b).*?' .
            '[:;]' .
        ')' . // Fin del grupo de patrones alternativos
        '\s*' . // Cero o más espacios después del delimitador
        '/iu', // Case-insensitive y Unicode
        '', // Reemplazar con nada
        $text,
        1 // Reemplazar solo la primera ocurrencia
    );

    // 2. Eliminar marcadores de Markdown y comillas externas
    $cleaned_text = str_replace('**', '', $cleaned_text);
    $cleaned_text = trim($cleaned_text, '"\'');
    $cleaned_text = trim($cleaned_text); // Trim inicial

    // 3. Intentar eliminar información extra *después* de la descripción principal.
    $metadata_patterns = [
        '\d+(\.\d+)?K\s+\w+', // Ej: 45.6K Instagram
        '\d+(\.\d+)?M\s+\w+', // Ej: 8.4M views
        '\b(Instagram|TikTok|Facebook|YouTube|Twitter|views|followers|likes)\b' // Nombres de plataformas o palabras clave sueltas
    ];
    $end_of_first_sentence_pos = -1;
    if (preg_match('/(?<!\b(?:Mr|Mrs|Ms|Dr|St|Av|etc))\.\s?/', $cleaned_text, $matches, PREG_OFFSET_CAPTURE)) {
          $end_of_first_sentence_pos = $matches[0][1]; // Posición del punto
    }
    if ($end_of_first_sentence_pos !== -1) {
        $text_after_first_sentence = trim(substr($cleaned_text, $end_of_first_sentence_pos + 1));
        if (!empty($text_after_first_sentence)) {
            $found_metadata = false;
            foreach ($metadata_patterns as $pattern) {
                 if (preg_match('/(?:\b|\d)' . $pattern . '(?:\b|\d)/i', $text_after_first_sentence)) {
                    $found_metadata = true;
                    break;
                }
            }
            if ($found_metadata) {
                 $cleaned_text = trim(substr($cleaned_text, 0, $end_of_first_sentence_pos + 1));
            }
        }
    }

    // 4. Limpieza final de espacios en blanco
    $cleaned_text = trim($cleaned_text);

    // 5. Eliminar puntos finales si son el último caracter (opcional, estético)
    // if (substr($cleaned_text, -1) === '.') {
    //    $cleaned_text = rtrim($cleaned_text, '.');
    // }

    return $cleaned_text;
}
// --- Fin Sección: Ayudante IA ---


// --- Sección: Procesamiento Masivo ---
// ... (Las funciones mri_render_bulk_process_page, mri_enqueue_bulk_scripts,
//      mri_ajax_get_total_images_callback, mri_ajax_process_batch_callback
//      permanecen IGUALES, ya que llaman a la función principal que contendrá la compresión) ...
/**
 * Renderiza la página de administración para el procesamiento masivo.
 */
function mri_render_bulk_process_page() {
    if ( ! current_user_can( 'manage_options' ) ) { // O 'upload_files'
        wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'mi-renombrador-imagenes' ) );
    }
    ?>
    <div class="wrap" id="mri-bulk-wrap">
        <h1><?php esc_html_e( 'Procesar Imágenes Antiguas con IA y Compresión', 'mi-renombrador-imagenes' ); ?></h1>
        <p><?php esc_html_e( 'Usa esta herramienta para generar metadatos (Título, Alt Text, Leyenda), renombrar y/o comprimir imágenes que ya existen en tu biblioteca de medios, utilizando la configuración guardada en la página de ajustes del plugin.', 'mi-renombrador-imagenes' ); ?></p>
        <p><strong><?php esc_html_e( 'Importante:', 'mi-renombrador-imagenes' ); ?></strong> <?php esc_html_e( 'Este proceso puede tardar mucho tiempo y consumir recursos del servidor y cuota de API (si se usa IA). Se recomienda hacer una copia de seguridad antes de empezar. El proceso se ejecuta en lotes para evitar timeouts.', 'mi-renombrador-imagenes' ); ?></p>

        <?php
        $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
        $needs_ia_config = $options['enable_ai_title'] || $options['enable_ai_alt'] || $options['enable_ai_caption'];
        $can_run_something = $needs_ia_config || $options['enable_rename'] || $options['enable_alt'] || $options['enable_caption'] || $options['enable_compression']; // Añadido chequeo de compresión

        if ( $needs_ia_config && empty( $options['gemini_api_key'] ) ) {
            echo '<div class="notice notice-error"><p>';
            printf(
                wp_kses_post( __( '<strong>Error:</strong> La clave API de Google AI no está configurada. Ve a la <a href="%s">página de ajustes</a> para añadirla antes de poder procesar imágenes con IA.', 'mi-renombrador-imagenes' ) ),
                esc_url( admin_url( 'options-general.php?page=mri_google_ai_settings' ) )
            );
            echo '</p></div>';
            // No mostrar el botón de inicio si falta la API key y se necesita IA
            echo '</div>'; // Cierra wrap
            return;

        } elseif (!$can_run_something) {
             echo '<div class="notice notice-warning"><p>';
            printf(
                wp_kses_post( __( '<strong>Advertencia:</strong> Ninguna función de procesamiento (Renombrado, Compresión, Título IA, Alt IA, Leyenda IA) está activada en los <a href="%s">ajustes</a>. El procesado masivo no hará nada.', 'mi-renombrador-imagenes' ) ),
                esc_url( admin_url( 'options-general.php?page=mri_google_ai_settings' ) )
            );
            echo '</p></div>';
        }

        // Mostrar advertencia si se va a comprimir y Imagick no está
        if ($options['enable_compression'] && $options['use_imagick_if_available'] && !(extension_loaded('imagick') && class_exists('Imagick'))) {
             echo '<div class="notice notice-info"><p>';
             esc_html_e('Nota: La extensión Imagick no está disponible. La compresión usará GD (puede ser menos efectiva para algunos formatos).', 'mi-renombrador-imagenes');
             echo '</p></div>';
        }
        ?>

        <div id="mri-bulk-options">
             <p>
                 <label for="mri-criteria">
                     <input type="checkbox" id="mri-criteria" name="mri-criteria" value="missing_alt">
                     <?php esc_html_e( 'Procesar solo imágenes que NO tengan Texto Alternativo (Alt Text).', 'mi-renombrador-imagenes' ); ?>
                 </label>
                 <br>
                 <small><?php esc_html_e( 'Si no marcas esta opción, se intentarán procesar TODAS las imágenes de la biblioteca (tipos soportados). El ajuste "Sobrescribir" en la configuración general determinará si se reemplazan los metadatos existentes. La compresión se aplicará si está activa.', 'mi-renombrador-imagenes'); ?></small>
             </p>
        </div>

        <div id="mri-bulk-controls">
            <button type="button" id="mri-start-processing" class="button button-primary">
                <?php esc_html_e( 'Iniciar Procesamiento', 'mi-renombrador-imagenes' ); ?>
            </button>
            <button type="button" id="mri-stop-processing" class="button" style="display: none;">
                <?php esc_html_e( 'Detener Procesamiento', 'mi-renombrador-imagenes' ); ?>
            </button>
             <span class="spinner" id="mri-bulk-spinner" style="float: none; vertical-align: middle;"></span>
        </div>

        <div id="mri-bulk-progress" style="margin-top: 20px; display: none; border: 1px solid #ccc; padding: 10px; margin-bottom: 1em;">
            <label for="mri-progress-bar" style="display:block; margin-bottom: 5px;"><?php esc_html_e( 'Progreso:', 'mi-renombrador-imagenes' ); ?></label>
            <progress id="mri-progress-bar" value="0" max="100" style="width: 100%; height: 25px; display: block; margin-top: 5px;"></progress>
            <p id="mri-progress-text" style="margin: 5px 0 0 0; text-align: center; font-weight: bold;">0 / 0</p>
        </div>

        <div id="mri-bulk-log" style="margin-top: 20px; max-height: 300px; overflow-y: scroll; background: #f7f7f7; border: 1px solid #ccc; padding: 10px; display: none;">
            <h4><?php esc_html_e( 'Registro:', 'mi-renombrador-imagenes' ); ?></h4>
            <ul id="mri-log-list" style="list-style: none; padding: 0; margin: 0; font-size: 12px;"></ul>
        </div>

        <?php // Añadir nonce para seguridad AJAX ?>
        <?php wp_nonce_field( 'mri_bulk_process_nonce', 'mri_bulk_nonce' ); ?>

    </div>
    <style>
        /* Estilos movidos aquí para simplicidad, podrían ir en un archivo CSS separado */
        #mri-bulk-log li { margin-bottom: 5px; border-bottom: 1px dotted #eee; padding-bottom: 5px; }
        #mri-bulk-log li.mri-log-error { color: red; font-weight: bold;}
        #mri-bulk-log li.mri-log-success { color: green; }
        #mri-bulk-log li.mri-log-notice { color: orange; }
        #mri-bulk-log li.mri-log-info { color: #555; } /* Added for info logs */
    </style>
    <?php
}

/**
 * Encola los scripts y estilos necesarios para la página de procesamiento masivo.
 */
function mri_enqueue_bulk_scripts( $hook_suffix ) {
    // Solo cargar en nuestra página específica (media_page_mri_bulk_process_page)
    if ( 'media_page_mri_bulk_process_page' !== $hook_suffix ) {
        return;
    }

    // Obtener la URL base del plugin
    $plugin_url = plugin_dir_url( __FILE__ );
    $plugin_version = get_file_data(__FILE__, ['Version' => 'Version'], false)['Version'];

    // Encolar el script JS
    wp_enqueue_script(
        'mri-admin-batch',
        $plugin_url . 'admin-batch.js', // Asegúrate que este archivo exista en la raíz del plugin
        ['jquery'], // Dependencia de jQuery
        $plugin_version, // Versión del script (usa la del plugin)
        true // Cargar en el footer
    );

    // Pasar datos de PHP a JavaScript
    wp_localize_script(
        'mri-admin-batch', // Handle del script al que pasar datos
        'mri_bulk_params', // Nombre del objeto JavaScript
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mri_bulk_process_nonce' ),
            'text_start' => esc_html__( 'Iniciar Procesamiento', 'mi-renombrador-imagenes' ),
            'text_stop' => esc_html__( 'Detener Procesamiento', 'mi-renombrador-imagenes' ),
            'text_stopping' => esc_html__( 'Deteniendo...', 'mi-renombrador-imagenes' ),
            'text_processing' => esc_html__( 'Procesando...', 'mi-renombrador-imagenes' ),
            'text_complete' => esc_html__( 'Procesamiento completado.', 'mi-renombrador-imagenes' ),
            'text_error' => esc_html__( 'Ocurrió un error. Revisa el registro y/o el log de errores de PHP.', 'mi-renombrador-imagenes' ),
            'text_confirm_stop' => esc_html__( '¿Estás seguro de que quieres detener el procesamiento? Las imágenes del lote actual podrían no completarse.', 'mi-renombrador-imagenes' ),
            'action_total' => 'mri_get_total_images',
            'action_batch' => 'mri_process_batch'
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'mri_enqueue_bulk_scripts' );

/**
 * Callback AJAX para obtener el número total de imágenes a procesar.
 */
function mri_ajax_get_total_images_callback() {
    check_ajax_referer( 'mri_bulk_process_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) { // O 'upload_files'
        wp_send_json_error( ['message' => esc_html__( 'Permiso denegado.', 'mi-renombrador-imagenes' )], 403 );
    }

    $criteria = isset($_POST['criteria']) ? sanitize_text_field($_POST['criteria']) : 'all';
    // Incluir SVG aquí para el conteo, aunque no se comprima/analice con IA
    $mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'];

    $args = [
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => -1, // Necesitamos contar todos
        'fields' => 'ids', // Solo necesitamos IDs para contar eficientemente
        'post_mime_type' => $mime_types,
        'suppress_filters' => true, // Ignorar filtros de otros plugins para el conteo
    ];

    // Aplicar criterio de "solo sin alt text"
    if ($criteria === 'missing_alt') {
        $args['meta_query'] = [
             'relation' => 'OR',
             [
                 'key' => '_wp_attachment_image_alt',
                 'compare' => 'NOT EXISTS',
             ],
             [
                 'key' => '_wp_attachment_image_alt',
                 'value' => '',
                 'compare' => '=',
             ],
         ];
    }

    $query = new WP_Query($args);
    $total_images = $query->post_count;

    wp_send_json_success( ['total' => $total_images] );
}
add_action( 'wp_ajax_mri_get_total_images', 'mri_ajax_get_total_images_callback' );

/**
 * Callback AJAX para procesar un lote de imágenes.
 */
function mri_ajax_process_batch_callback() {
    check_ajax_referer( 'mri_bulk_process_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) { // O 'upload_files'
        wp_send_json_error( ['message' => esc_html__( 'Permiso denegado.', 'mi-renombrador-imagenes' )], 403 );
    }

    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
    // Reducir tamaño de lote si la compresión o IA están activas
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $intensive_task = $options['enable_compression'] || $options['enable_ai_title'] || $options['enable_ai_alt'] || $options['enable_ai_caption'];
    $default_batch_size = $intensive_task ? 3 : 10; // Lote más pequeño para tareas intensivas
    $batch_size = isset($_POST['batchSize']) ? absint($_POST['batchSize']) : $default_batch_size;
    $batch_size = max(1, min($batch_size, 15)); // Limitar tamaño de lote
    $criteria = isset($_POST['criteria']) ? sanitize_text_field($_POST['criteria']) : 'all';
    $mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml']; // Incluir SVG aquí también

    $args = [
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'fields' => 'ids',
        'post_mime_type' => $mime_types,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true, // Ignorar filtros de otros plugins
    ];

    // Aplicar criterio de "solo sin alt text"
    if ($criteria === 'missing_alt') {
        $args['meta_query'] = [
             'relation' => 'OR',
             [ 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS', ],
             [ 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=', ],
         ];
    }

    $query = new WP_Query($args);
    $attachment_ids = $query->posts;

    $processed_count = 0;
    $log_messages = [];

    if ( empty($attachment_ids) ) {
        wp_send_json_success( [
            'processedCount' => 0,
             // Corregido: Devolver array de objetos log
             'logMessages' => [ ['message' => esc_html__('No se encontraron más imágenes para procesar con los criterios seleccionados.', 'mi-renombrador-imagenes'), 'type' => 'notice'] ]
        ]);
        return; // Importante salir aquí
    }

    // Intentar aumentar el tiempo límite (puede no funcionar en todos los hostings)
    if (function_exists('set_time_limit')) {
         @set_time_limit(300); // 5 minutos (aumentado por compresión)
    }

    foreach ( $attachment_ids as $attachment_id ) {
        // Añadir log antes de procesar (JS lo maneja ahora)
        // $log_messages[] = sprintf(esc_html__('Procesando Imagen ID: %d...', 'mi-renombrador-imagenes'), $attachment_id);

        // Llamar a la función principal de procesamiento, indicando que es bulk
        try {
             // Limpiar cache de objeto para este post antes de procesar, puede ayudar con datos obsoletos
              wp_cache_delete( $attachment_id, 'posts' );
              wp_cache_delete( $attachment_id, 'post_meta' );

             // Añadir una bandera para evitar procesamiento doble si add_attachment se dispara de nuevo por update_post
             update_post_meta($attachment_id, '_mri_processing_bulk', true);

             // --- INICIO: Registro de lo que se va a hacer ---
             $options_current = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
             $actions_to_perform = [];
             if ($options_current['enable_rename']) $actions_to_perform[] = 'Renombrado';
             if ($options_current['enable_compression']) $actions_to_perform[] = 'Compresión';
             if ($options_current['enable_ai_title']) $actions_to_perform[] = 'Título IA';
             if ($options_current['enable_alt'] || $options_current['enable_ai_alt']) $actions_to_perform[] = 'Alt Text';
             if ($options_current['enable_caption'] || $options_current['enable_ai_caption']) $actions_to_perform[] = 'Leyenda';
             $actions_str = !empty($actions_to_perform) ? implode(', ', $actions_to_perform) : 'Ninguna acción activa';
             $log_messages[] = ['type' => 'info', 'message' => sprintf(esc_html__('ID %d: Iniciando (%s)...', 'mi-renombrador-imagenes'), $attachment_id, $actions_str)];
             // --- FIN: Registro acciones ---

            $result_message = mri_procesar_imagen_subida_google_ai($attachment_id, true); // true indica bulk process

            delete_post_meta($attachment_id, '_mri_processing_bulk'); // Eliminar bandera

            $processed_count++;
            // Usar el mensaje devuelto por la función principal si existe
            $log_message_text = is_string($result_message) && !empty($result_message)
                              ? $result_message
                              : sprintf(esc_html__('Imagen ID: %d procesada.', 'mi-renombrador-imagenes'), $attachment_id);
            $log_messages[] = ['type' => 'success', 'message' => $log_message_text];


        } catch (Exception $e) {
             delete_post_meta($attachment_id, '_mri_processing_bulk'); // Asegurarse de eliminar la bandera en caso de error
              // Devolver el mensaje de error específico de la excepción
             $log_messages[] = ['type' => 'error', 'message' => sprintf(esc_html__('Error procesando ID %d: %s', 'mi-renombrador-imagenes'), $attachment_id, $e->getMessage())];
             error_log("MRI Bulk Error processing ID $attachment_id: " . $e->getMessage());
             // Considerar si continuar o detener el lote en caso de error (actualmente continúa)
        }
         // Pausa opcional entre imágenes dentro del lote para no saturar la API/servidor
         sleep(1); // Pausa de 1 segundo (podría aumentarse si hay timeouts)
    }

    // $log_messages[] = sprintf(esc_html__('Lote completado. %d imágenes procesadas en este lote.', 'mi-renombrador-imagenes'), $processed_count); // Mensaje ya manejado por JS

    wp_send_json_success( [
        'processedCount' => $processed_count,
         // Enviar array de objetos log como está, JS lo interpretará
        'logMessages' => $log_messages
    ]);
}
add_action( 'wp_ajax_mri_process_batch', 'mri_ajax_process_batch_callback' );
// --- Fin Sección: Procesamiento Masivo ---


// --- Sección: Lógica Principal de Procesamiento (MODIFICADA para Bulk, Lenguaje y Compresión) ---

/**
 * Procesa la imagen subida O una imagen existente: renombra, COMPRIME, genera título/alt/caption con IA (Vision).
 *
 * @param int $attachment_id ID del adjunto.
 * @param bool $is_bulk_process Indica si la llamada viene del proceso masivo (omite contexto de post padre).
 * @return string|void Un mensaje de resumen para el log masivo, o nada en subida normal.
 * @throws Exception Si ocurre un error irrecuperable durante el proceso masivo.
 */
function mri_procesar_imagen_subida_google_ai( $attachment_id, $is_bulk_process = false ) {

    // --- Inicio: Obtener opciones y datos básicos ---
    $options = get_option( MRI_SETTINGS_OPTION_NAME, mri_google_ai_get_default_options() );
    $is_ai_title_enabled = !empty($options['enable_ai_title']);
    $is_ai_alt_enabled = !empty($options['enable_ai_alt']);
    $is_ai_caption_enabled = !empty($options['enable_ai_caption']);
    $is_rename_enabled = !empty($options['enable_rename']);
    $is_compression_enabled = !empty($options['enable_compression']); // Nueva opción
    $is_alt_fallback_enabled = !empty($options['enable_alt']);
    $is_caption_fallback_enabled = !empty($options['enable_caption']);

    $log_summary = []; // Para guardar resumen de acciones

    // Obtener idioma para la IA
    $ai_language_code = isset($options['ai_output_language']) ? $options['ai_output_language'] : 'es';
    $all_languages = mri_get_supported_languages();
    $ai_language_name = isset($all_languages[$ai_language_code]) ? $all_languages[$ai_language_code] : __('Español', 'mi-renombrador-imagenes'); // Nombre completo para el prompt

    // Si ninguna función está activa en settings, salir
    if (!$is_ai_title_enabled && !$is_ai_alt_enabled && !$is_ai_caption_enabled && !$is_rename_enabled && !$is_compression_enabled && !$is_alt_fallback_enabled && !$is_caption_fallback_enabled) {
        $msg = sprintf(__('ID %d: Ninguna función activa.', 'mi-renombrador-imagenes'), $attachment_id);
        if ($is_bulk_process) { return $msg; } // Devolver mensaje en bulk
        return;
    }

    $gemini_api_key = null; $gemini_model = null;
    $needs_api = $is_ai_title_enabled || $is_ai_alt_enabled || $is_ai_caption_enabled;
    if ($needs_api) {
        $gemini_api_key = !empty($options['gemini_api_key']) ? trim($options['gemini_api_key']) : null;
        $gemini_model = !empty($options['gemini_model']) ? trim($options['gemini_model']) : mri_google_ai_get_default_options()['gemini_model'];
        if (empty($gemini_api_key)) {
            $msg = sprintf(__('ID %d: Falta API Key para IA.', 'mi-renombrador-imagenes'), $attachment_id);
             if ($is_bulk_process) { $log_summary[] = $msg; /* Continúa sin IA */ }
             else { error_log("MRI Plugin: Skipping AI processing for ID $attachment_id - API Key missing."); }
             $needs_api = false; // Desactivar necesidad de API si falta la clave
        }
    }

    $mime_type = get_post_mime_type( $attachment_id );
    // Tipos soportados por el plugin en general (incluyendo SVG que no se comprime/analiza IA)
    $allowed_mime_types = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml' ];
    // Tipos compatibles con Gemini Vision API
    $gemini_compatible_mime_types = ['image/png', 'image/jpeg', 'image/webp', 'image/heic', 'image/heif', 'image/gif', 'image/avif'];
    // Tipos para los que intentaremos compresión
    $compressible_mime_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    if ( ! $mime_type || ! in_array( $mime_type, $allowed_mime_types ) ) {
         $msg = sprintf(__('ID %d: Tipo MIME no válido/soportado (%s).', 'mi-renombrador-imagenes'), $attachment_id, $mime_type);
         if ($is_bulk_process) { throw new Exception($msg); } // Error en bulk
         error_log("MRI Plugin: " . $msg);
         return;
    }
    $is_gemini_compatible_mime = in_array($mime_type, $gemini_compatible_mime_types);
    $is_compressible_mime = in_array($mime_type, $compressible_mime_types);
    $is_svg = ($mime_type === 'image/svg+xml');

    if ($needs_api && !$is_gemini_compatible_mime) {
         $log_msg = sprintf(__('ID %d: Tipo MIME (%s) no compatible con IA Vision.', 'mi-renombrador-imagenes'), $attachment_id, $mime_type);
         if ($is_bulk_process) { $log_summary[] = $log_msg; /* Continúa sin IA */ }
         else { error_log("MRI Plugin: " . $log_msg); }
         $needs_api = false; // Desactivar necesidad de API para este archivo
    }

    $ruta_archivo_original = get_attached_file( $attachment_id, true ); // true = Unfiltered path
     if ( ! $ruta_archivo_original || ! file_exists( $ruta_archivo_original ) || ! is_readable( $ruta_archivo_original ) ) {
         $log_msg = sprintf(__('Error al acceder al archivo para ID %d. Ruta: %s', 'mi-renombrador-imagenes'), $attachment_id, print_r($ruta_archivo_original, true));
         if ($is_bulk_process) { throw new Exception($log_msg); }
         else { error_log("MRI Plugin: " . $log_msg); return; }
     }

    // --- Obtener Título y Preparar Fallback ---
    $titulo_actual_adjunto = get_the_title( $attachment_id );
    $nombre_archivo_original_sin_ext = pathinfo( $ruta_archivo_original, PATHINFO_FILENAME );
    $titulo_base_para_fallback = $titulo_actual_adjunto;
    $titulo_era_vacio = empty($titulo_base_para_fallback);
    $titulo_era_generico = !$titulo_era_vacio && (sanitize_title($titulo_base_para_fallback) === sanitize_title($nombre_archivo_original_sin_ext));

    if ( $titulo_era_vacio || $titulo_era_generico ) {
         $titulo_formateado = ucwords(str_replace(['-', '_'], ' ', $nombre_archivo_original_sin_ext));
         $titulo_base_para_fallback = $titulo_formateado;

         if ( $titulo_era_vacio && !$is_ai_title_enabled ) {
             $update_data = ['ID' => $attachment_id, 'post_title' => sanitize_text_field($titulo_base_para_fallback)];
             remove_action('add_attachment', 'mri_attachment_processor', 20);
             wp_update_post($update_data);
             add_action('add_attachment', 'mri_attachment_processor', 20, 1);
             $titulo_actual_adjunto = $titulo_base_para_fallback;
             $log_summary[] = __('Título vacío, generado desde nombre archivo.', 'mi-renombrador-imagenes');
         } else {
             $titulo_actual_adjunto = $titulo_base_para_fallback;
         }
    } else {
         $titulo_base_para_fallback = sanitize_text_field( $titulo_actual_adjunto );
         $titulo_actual_adjunto = $titulo_base_para_fallback;
    }


    // --- Obtener Contexto (Post Padre, SEO Keyword) ---
    $parent_post_id = null; $parent_post_title = null; $product_name = null; $focus_keyword = null; $contexto_seo = ''; $parent_post_type = null;
    if (!$is_bulk_process && !empty($options['include_seo_in_ai_prompt'])) {
        $parent_post_id = wp_get_post_parent_id($attachment_id);
        if ( !$parent_post_id && isset($_REQUEST['post_id']) ) { $parent_post_id = absint($_REQUEST['post_id']); }

        if ( $parent_post_id > 0 ) {
            $parent_post = get_post( $parent_post_id );
            if ( $parent_post instanceof WP_Post ) {
                $parent_post_title = get_the_title( $parent_post_id );
                $parent_post_type = $parent_post->post_type;

                if ( $parent_post_type === 'product' && class_exists( 'WooCommerce' ) ) {
                    $product_name = $parent_post_title;
                    $contexto_seo .= sprintf(__(' Product Name: "%s".', 'mi-renombrador-imagenes'), $product_name);
                } elseif ($parent_post_title) {
                    $contexto_seo .= sprintf(__(' Page/Post Title: "%s".', 'mi-renombrador-imagenes'), $parent_post_title);
                }

                // Código para obtener Keyword SEO (Yoast, Rank Math, AIOSEO, SEOPress)
                $keyword_found = false;
                if ( function_exists('YoastSEO') || defined('WPSEO_VERSION') ) {
                     $focus_keyword = get_post_meta( $parent_post_id, '_yoast_wpseo_focuskw', true );
                     $keyword_found = !empty($focus_keyword);
                }
                if (!$keyword_found && defined('RANK_MATH_VERSION') ) {
                     $keywords_rm = get_post_meta( $parent_post_id, 'rank_math_focus_keyword', true );
                     if ( ! empty($keywords_rm) ) { $focus_keyword = explode(',', $keywords_rm)[0]; $keyword_found = true; }
                }
                if (!$keyword_found && (class_exists('All_in_One_SEO_Pack') || defined('AIOSEO_VERSION'))) {
                     try {
                         $aioseo_options = get_post_meta( $parent_post_id, '_aioseo_meta', true );
                         if (!empty($aioseo_options['keyphrases']['focus']['keyphrase'])) {
                              $focus_keyword = $aioseo_options['keyphrases']['focus']['keyphrase'];
                              $keyword_found = true;
                         } elseif (!empty($aioseo_options['keywords'])) {
                             $keywords_arr = maybe_unserialize($aioseo_options['keywords']);
                             if (is_array($keywords_arr) && !empty($keywords_arr[0])) {
                                 $focus_keyword = $keywords_arr[0];
                                 $keyword_found = true;
                             }
                         }
                     } catch (Exception $e) { /* Ignorar */ }
                }
                 if (!$keyword_found && defined('SEOPRESS_VERSION') ) {
                     $keywords_sp = get_post_meta( $parent_post_id, '_seopress_analysis_target_kw', true );
                     if ( ! empty($keywords_sp) ) { $focus_keyword = explode(',', $keywords_sp)[0]; $keyword_found = true; }
                 }
                if ($keyword_found && !empty($focus_keyword)) {
                     $focus_keyword = sanitize_text_field( trim($focus_keyword) );
                     $contexto_seo .= sprintf(__(' Main Keyword: "%s".', 'mi-renombrador-imagenes'), $focus_keyword);
                }
            }
        }
    }
    // --- Fin Obtener Datos y Contexto ---

    $imagen_base64 = null; // Se cargará solo si es necesario para la IA
    $titulo_generado_ia = false;
    $alt_generado_ia = false;
    $caption_generado_ia = false;

    // --- PASO 1: Generación de Título con IA (Vision) ---
    if ( $is_ai_title_enabled && $needs_api ) {
        $titulo_actual_para_comparar = get_the_title($attachment_id);
        $titulo_es_basico_o_vacio = empty($titulo_actual_para_comparar) || (sanitize_title($titulo_actual_para_comparar) === sanitize_title(pathinfo( get_attached_file( $attachment_id, true ), PATHINFO_FILENAME )));

        if ( ! empty( $options['overwrite_title'] ) || $titulo_es_basico_o_vacio ) {
             // Leer imagen solo si no se ha leído antes y es compatible
             if ($imagen_base64 === null && $is_gemini_compatible_mime) {
                  $image_content = @file_get_contents( $ruta_archivo_original );
                  if ( $image_content !== false ) { $imagen_base64 = base64_encode( $image_content ); unset($image_content); if (!$imagen_base64){ error_log("MRI Plugin: Error base64 encoding ID $attachment_id"); $imagen_base64 = null; } }
                  else { error_log("MRI Plugin: Error reading file for AI ID $attachment_id"); $imagen_base64 = null; }
             }

            if ( $imagen_base64 ) {
                $prompt_contexto_titulo = '';
                if (!empty($contexto_seo)) {
                    $prompt_contexto_titulo .= sprintf(__(' Context: The image is used on a page/product with the following details: %s', 'mi-renombrador-imagenes'), $contexto_seo);
                }
                $prompt_contexto_titulo .= sprintf(__(' The original filename was "%s".', 'mi-renombrador-imagenes'), esc_html(basename($ruta_archivo_original)));

                $prompt_titulo = sprintf(
                    /* translators: %1$s: Language name (e.g., Español, English), %2$s: Context string */
                    __('Generate the response in %1$s. Analyze this image. Generate a concise and descriptive title (5-10 words) suitable for this image as an attachment title on a website.%2$s Avoid generic phrases. Be specific. Provide ONLY the final title, without explanations or introductory text.', 'mi-renombrador-imagenes'),
                    $ai_language_name,
                    $prompt_contexto_titulo
                );

                $titulo_generado_api = mri_llamar_google_ai_api($prompt_titulo, $gemini_api_key, $gemini_model, 50, $imagen_base64, $mime_type);

                if ( $titulo_generado_api !== false && !empty(trim($titulo_generado_api)) ) {
                     $cleaned_title = mri_clean_ai_response($titulo_generado_api);
                     $nuevo_titulo = sanitize_text_field( $cleaned_title );
                     if (!empty($nuevo_titulo) && $nuevo_titulo !== $titulo_actual_para_comparar) {
                         $update_data = ['ID' => $attachment_id, 'post_title' => $nuevo_titulo];
                         remove_action('add_attachment', 'mri_attachment_processor', 20);
                         wp_update_post($update_data);
                         add_action('add_attachment', 'mri_attachment_processor', 20, 1);
                         $titulo_actual_adjunto = $nuevo_titulo;
                         $titulo_base_para_fallback = $nuevo_titulo;
                         $titulo_generado_ia = true;
                         $log_summary[] = __('Título IA generado.', 'mi-renombrador-imagenes');
                     } elseif (!empty($nuevo_titulo)) {
                         $titulo_generado_ia = true; // Ya existía o era igual
                         $log_summary[] = __('Título IA no cambió.', 'mi-renombrador-imagenes');
                     } else {
                         $log_summary[] = __('Título IA vacío post-limpieza.', 'mi-renombrador-imagenes');
                     }
                } else {
                     $log_summary[] = __('Fallo API Título IA.', 'mi-renombrador-imagenes');
                }
            } else if ($is_gemini_compatible_mime) { // Solo loguear error si debía leerse
                 $log_summary[] = __('Error lectura imagen para Título IA.', 'mi-renombrador-imagenes');
            }
        } else {
            $log_summary[] = __('Título existente conservado (no IA).', 'mi-renombrador-imagenes');
        }
    } // fin if $is_ai_title_enabled

    // Asegurarse de que $titulo_actual_adjunto tenga el valor más reciente
    if (!$titulo_generado_ia) {
        $titulo_actual_adjunto = $titulo_base_para_fallback;
    }
    // --- Fin PASO 1: Generación de Título ---


    // --- PASO 2: Renombrado de Archivo ---
    $renamed = false;
    if ( $is_rename_enabled && !empty($titulo_actual_adjunto) ) {
         $info_uploads = wp_upload_dir( null, false );
         if ( $info_uploads && empty($info_uploads['error']) ) {
             $directorio_base_uploads = trailingslashit($info_uploads['basedir']);
             $directorio_archivo = pathinfo( $ruta_archivo_original, PATHINFO_DIRNAME );
             $extension = strtolower( pathinfo( $ruta_archivo_original, PATHINFO_EXTENSION ) );

             if ( !empty($extension) && $directorio_archivo ) {
                 $nombre_base_final = '';
                 $titulo_imagen_slug = sanitize_title($titulo_actual_adjunto);
                 $nombre_prefijo = '';
                 if (!$is_bulk_process && !empty($parent_post_title) ) {
                      $titulo_padre_slug = sanitize_title($parent_post_title);
                      if (!empty($titulo_padre_slug) && $titulo_padre_slug !== $titulo_imagen_slug) {
                           $nombre_prefijo = $titulo_padre_slug . '-';
                      }
                 }

                 if (!empty($titulo_imagen_slug)) {
                     $nombre_base_final = $nombre_prefijo . $titulo_imagen_slug;
                 } else {
                     $nombre_base_final = sanitize_title($nombre_archivo_original_sin_ext);
                     if (empty($nombre_base_final)) { $nombre_base_final = 'imagen-' . $attachment_id; }
                 }

                 $max_len_base = 200;
                 if (mb_strlen($nombre_base_final) > $max_len_base) {
                     $nombre_base_final = mb_substr($nombre_base_final, 0, $max_len_base);
                     $nombre_base_final = preg_replace('/%[0-9a-f]?$/i', '', $nombre_base_final);
                     $nombre_base_final = rtrim($nombre_base_final, '-');
                 }

                 $nuevo_nombre_archivo_propuesto = $nombre_base_final . '.' . $extension;
                 $nombre_original_completo = basename( $ruta_archivo_original );

                 if ( $nombre_original_completo !== $nuevo_nombre_archivo_propuesto ) {
                      $nuevo_nombre_archivo_unico = wp_unique_filename( $directorio_archivo, $nuevo_nombre_archivo_propuesto );
                      $nueva_ruta_archivo = $directorio_archivo . DIRECTORY_SEPARATOR . $nuevo_nombre_archivo_unico;

                      if ($nombre_original_completo !== $nuevo_nombre_archivo_unico) {
                          global $wp_filesystem;
                          if ( empty( $wp_filesystem ) ) {
                              require_once ( ABSPATH . '/wp-admin/includes/file.php' );
                              WP_Filesystem();
                          }

                          if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem->is_writable($directorio_archivo) && $wp_filesystem->is_readable($ruta_archivo_original) ) {
                              $renombrado_exitoso = $wp_filesystem->move( $ruta_archivo_original, $nueva_ruta_archivo, true );

                              if ( $renombrado_exitoso ) {
                                  $ruta_relativa_nueva = ltrim(str_replace( $directorio_base_uploads, '', $nueva_ruta_archivo ), '/\\');
                                  update_post_meta( $attachment_id, '_wp_attached_file', $ruta_relativa_nueva );

                                  // Regenerar metadatos EXCEPTO para SVG
                                  if ( ! $is_svg ) {
                                      require_once(ABSPATH . 'wp-admin/includes/image.php');
                                      wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $nueva_ruta_archivo ) );
                                  }
                                  $ruta_archivo_original = $nueva_ruta_archivo; // ACTUALIZAR RUTA PARA SIGUIENTES PASOS
                                  $log_summary[] = sprintf(__('Renombrado a %s.', 'mi-renombrador-imagenes'), basename($nueva_ruta_archivo));
                                  $renamed = true; // Marcar como renombrado
                              } else {
                                  $log_msg = sprintf(__('Fallo renombrado: No se pudo mover archivo %s.', 'mi-renombrador-imagenes'), $attachment_id);
                                  if ($is_bulk_process) { error_log("MRI Bulk Error: " . $log_msg); $log_summary[] = $log_msg; } else { error_log("MRI Error: " . $log_msg); }
                              }
                          } else {
                               $log_msg = sprintf(__('Fallo renombrado: Permisos/WP_Filesystem %s.', 'mi-renombrador-imagenes'), $attachment_id);
                               if ($is_bulk_process) { error_log("MRI Bulk Error: " . $log_msg); $log_summary[] = $log_msg; } else { error_log("MRI Error: " . $log_msg); }
                          }
                      } else {
                          $log_summary[] = __('Renombrado no necesario (nombre único igual).', 'mi-renombrador-imagenes');
                      }
                 } else {
                     $log_summary[] = __('Renombrado no necesario (nombre igual).', 'mi-renombrador-imagenes');
                 }
            }
         } else {
             $log_msg = sprintf(__('Fallo renombrado: Error directorio uploads %s.', 'mi-renombrador-imagenes'), $attachment_id);
             if ($is_bulk_process) { error_log("MRI Bulk Error: " . $log_msg); $log_summary[] = $log_msg; } else { error_log("MRI Error: " . $log_msg); }
         }
    } elseif ($is_rename_enabled) {
        $log_summary[] = __('Renombrado omitido (título vacío).', 'mi-renombrador-imagenes');
    }
    // --- Fin PASO 2: Renombrado ---


    // --- PASO 2.5: Compresión de Imagen ---
    $compressed = false;
    if ( $is_compression_enabled && $is_compressible_mime ) {
        $file_path = $ruta_archivo_original; // Usar la ruta posiblemente actualizada por el renombrado
        $original_size = @filesize($file_path);

        // Comprobar permisos de escritura ANTES de intentar comprimir
        if (!is_writable($file_path)) {
            $log_msg = sprintf(__('Compresión omitida: Archivo no escribible %s.', 'mi-renombrador-imagenes'), $attachment_id);
            if ($is_bulk_process) { error_log("MRI Bulk Error: " . $log_msg); $log_summary[] = $log_msg; } else { error_log("MRI Error: " . $log_msg); }
        }
        // Comprobar si las librerías necesarias están cargadas
        elseif ($options['use_imagick_if_available'] && extension_loaded('imagick') && class_exists('Imagick')) {
            // --- Usar Imagick ---
            try {
                $imagick = new Imagick($file_path);
                $format = $imagick->getImageFormat();

                // Establecer compresión y calidad según formato
                 if ($format == 'JPEG') {
                    $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $imagick->setImageCompressionQuality($options['jpeg_quality']);
                 } elseif ($format == 'PNG') {
                    $imagick->setImageCompression(Imagick::COMPRESSION_ZIP); // O COMPRESSION_LZW, probar cuál va mejor
                    $imagick->setImageCompressionQuality(9); // Nivel de compresión para PNG (0-9)
                 } elseif ($format == 'GIF') {
                    $imagick = $imagick->optimizeImageLayers(); // Optimizar capas/frames GIF
                 } elseif ($format == 'WEBP') {
                     // Imagick puede necesitar configuración específica para WebP lossless/lossy
                     $imagick->setImageFormat('WEBP'); // Asegurar formato
                     $imagick->setImageCompressionQuality($options['jpeg_quality']); // Calidad para WebP lossy
                     // Considerar añadir opción para webp lossless: $imagick->setOption('webp:lossless', 'true');
                 } elseif ($format == 'AVIF') {
                     $imagick->setImageFormat('AVIF');
                     $imagick->setImageCompressionQuality($options['jpeg_quality']); // AVIF también usa calidad
                 }

                 // Quitar metadata extra (EXIF, etc.) puede reducir tamaño
                 $imagick->stripImage();

                // Guardar imagen
                if ($imagick->writeImage($file_path)) {
                    $compressed = true;
                } else {
                     $log_summary[] = __('Fallo compresión Imagick (writeImage).', 'mi-renombrador-imagenes');
                }

                $imagick->clear();
                $imagick->destroy();

            } catch (ImagickException $e) {
                 $log_msg = sprintf(__('Error Compresión Imagick ID %s: %s', 'mi-renombrador-imagenes'), $attachment_id, $e->getMessage());
                 if ($is_bulk_process) { error_log("MRI Bulk Error: " . $log_msg); $log_summary[] = $log_msg; } else { error_log("MRI Error: " . $log_msg); }
            }

        } elseif (extension_loaded('gd')) {
            // --- Usar GD (Fallback) ---
            $image_resource = null;
            $gd_supported = false;

             ini_set('memory_limit', '256M'); // Intentar aumentar memoria para GD

            switch ($mime_type) {
                case 'image/jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $image_resource = @imagecreatefromjpeg($file_path);
                        if ($image_resource && function_exists('imagejpeg')) {
                            if (imagejpeg($image_resource, $file_path, $options['jpeg_quality'])) {
                                $compressed = true;
                            }
                            $gd_supported = true;
                        }
                    }
                    break;
                case 'image/png':
                    if (function_exists('imagecreatefrompng')) {
                        $image_resource = @imagecreatefrompng($file_path);
                        if ($image_resource && function_exists('imagepng')) {
                            imagealphablending($image_resource, false); // Necesario para transparencia
                            imagesavealpha($image_resource, true);     // Necesario para transparencia
                            if (imagepng($image_resource, $file_path, 9)) { // Nivel 9 = max compresión GD
                                $compressed = true;
                            }
                             $gd_supported = true;
                        }
                    }
                    break;
                case 'image/gif':
                     if (function_exists('imagecreatefromgif')) {
                        $image_resource = @imagecreatefromgif($file_path);
                        // GD no suele recomprimir bien GIFs, solo lo volvemos a guardar
                        if ($image_resource && function_exists('imagegif')) {
                            if (imagegif($image_resource, $file_path)) {
                                $compressed = true; // Marcar como procesado, aunque la compresión sea mínima/nula
                            }
                            $gd_supported = true;
                        }
                     }
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image_resource = @imagecreatefromwebp($file_path);
                        if ($image_resource && function_exists('imagewebp')) {
                            // GD WebP usa calidad 0-100 como JPEG
                            if (imagewebp($image_resource, $file_path, $options['jpeg_quality'])) {
                                $compressed = true;
                            }
                            $gd_supported = true;
                        }
                    }
                    break;
            }

            if ($image_resource) {
                imagedestroy($image_resource);
            }

            if (!$gd_supported) {
                 $log_summary[] = sprintf(__('Compresión GD no soportada para %s.', 'mi-renombrador-imagenes'), $mime_type);
            } elseif (!$compressed && $gd_supported) {
                 $log_summary[] = __('Fallo compresión GD (guardado).', 'mi-renombrador-imagenes');
            }

        } else {
            // Ni Imagick ni GD disponibles
             $log_summary[] = __('Compresión omitida: No hay librerías (Imagick/GD).', 'mi-renombrador-imagenes');
        }

        // Si se comprimió, actualizar metadata de WP
        if ($compressed) {
            clearstatcache(); // Limpiar cache de estado de archivo
            $new_size = @filesize($file_path);
            if ($new_size && $original_size && $new_size < $original_size) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file_path ) );
                $reduction = round((1 - $new_size / $original_size) * 100);
                $log_summary[] = sprintf(__('Comprimido (%s%% red.).', 'mi-renombrador-imagenes'), $reduction);
            } elseif ($new_size && $original_size && $new_size >= $original_size) {
                // No hubo reducción o incluso aumentó (puede pasar con PNGs ya optimizados)
                $log_summary[] = __('Comprimido (sin reducción).', 'mi-renombrador-imagenes');
                // Considerar revertir si el tamaño aumentó? Por ahora no lo hacemos.
            } else {
                 $log_summary[] = __('Comprimido (error tamaño).', 'mi-renombrador-imagenes');
            }
        }
    } elseif ($is_compression_enabled && !$is_compressible_mime) {
         $log_summary[] = sprintf(__('Compresión no aplicable a %s.', 'mi-renombrador-imagenes'), $mime_type);
    }
    // --- Fin PASO 2.5: Compresión ---


    // --- PASO 3: Generación de Texto ALT ---
    if ( $is_alt_fallback_enabled ) {
        $alt_existente = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        $alt_generado_valor = false;

        if ( ! empty( $options['overwrite_alt'] ) || empty( $alt_existente ) ) {
            // Intentar generar con IA
            if ( $is_ai_alt_enabled && $needs_api ) {
                 // Leer imagen solo si no se ha leído antes y es compatible
                if ($imagen_base64 === null && $is_gemini_compatible_mime) {
                     $image_content_alt = @file_get_contents( $ruta_archivo_original ); // Leer de la ruta (posiblemente comprimida)
                     if ($image_content_alt !== false) { $imagen_base64 = base64_encode($image_content_alt); unset($image_content_alt); if (!$imagen_base64){ /* log error */} }
                     else { /* log error */ $imagen_base64 = null; }
                }

                if ( $imagen_base64 ) {
                    $prompt_contexto_alt = '';
                     if (!empty($titulo_actual_adjunto)) { $prompt_contexto_alt .= sprintf(__(' Image title is "%s".', 'mi-renombrador-imagenes'), esc_html($titulo_actual_adjunto)); }
                     if (!empty($contexto_seo)) { $prompt_contexto_alt .= sprintf(__(' Context: %s', 'mi-renombrador-imagenes'), $contexto_seo); }

                    $prompt_alt = sprintf(
                         /* translators: %1$s: Language name (e.g., Español, English), %2$s: Context string */
                         __('Generate the response in %1$s. Analyze this image. Generate a concise and descriptive alt text (maximum 125 characters) useful for accessibility and SEO.%2$s Do not use phrases like "image of" or "picture of". Provide ONLY the final alt text, without explanations or introductory text.', 'mi-renombrador-imagenes'),
                         $ai_language_name,
                         $prompt_contexto_alt
                    );

                    $alt_generado_api = mri_llamar_google_ai_api($prompt_alt, $gemini_api_key, $gemini_model, 60, $imagen_base64, $mime_type);

                    if ( $alt_generado_api !== false && !empty(trim($alt_generado_api)) ) {
                         $cleaned_alt = mri_clean_ai_response($alt_generado_api);
                         if (!empty($cleaned_alt)) {
                             $alt_generado_valor = $cleaned_alt;
                             $alt_generado_ia = true;
                         } else {
                             if ($is_bulk_process) { error_log("MRI Bulk Notice ID $attachment_id: AI Alt generation resulted in empty string after cleaning."); }
                         }
                    } else {
                         if ($is_bulk_process) { error_log("MRI Bulk Notice ID $attachment_id: AI Alt generation failed or returned empty."); }
                    }
                } else if ($is_gemini_compatible_mime) {
                    if ($is_bulk_process) { error_log("MRI Bulk Error ID $attachment_id: Could not read image file for AI Alt generation."); }
                }
            } // fin if AI alt enabled

            // Fallback si la IA no generó Alt
            if ( $alt_generado_valor === false ) {
                $alt_parts = [];
                if (!$is_bulk_process) {
                    if ($product_name) { $alt_parts[] = $product_name; }
                    elseif ($parent_post_title) { $alt_parts[] = $parent_post_title; }
                }
                 if (!empty($titulo_actual_adjunto)) { $alt_parts[] = $titulo_actual_adjunto; }
                 else { $alt_parts[] = ucwords(str_replace(['-', '_'], ' ', pathinfo( $ruta_archivo_original, PATHINFO_FILENAME ))); }

                if (!$is_bulk_process && $focus_keyword) { $alt_parts[] = $focus_keyword; }

                $alt_generado_fallback = implode(' - ', array_unique(array_filter($alt_parts)));
                 if (empty($alt_generado_fallback)) {
                     $alt_generado_fallback = !empty($titulo_actual_adjunto) ? $titulo_actual_adjunto : ucwords(str_replace(['-', '_'], ' ', pathinfo( $ruta_archivo_original, PATHINFO_FILENAME )));
                 }
                 $alt_generado_valor = $alt_generado_fallback;
                 $log_action = $alt_generado_ia ? __('Alt IA generado.', 'mi-renombrador-imagenes') : __('Alt fallback generado.', 'mi-renombrador-imagenes');
                 $log_summary[] = $log_action;
            } elseif ($alt_generado_ia) {
                 $log_summary[] = __('Alt IA generado.', 'mi-renombrador-imagenes'); // Log si la IA tuvo éxito
            }

            // Guardar el Alt generado
            $final_alt_text = sanitize_text_field( $alt_generado_valor );
            $max_alt_length = 125;
            if (mb_strlen($final_alt_text) > $max_alt_length) {
                $final_alt_text = mb_substr($final_alt_text, 0, $max_alt_length);
                $last_space = mb_strrpos($final_alt_text, ' ');
                if ($last_space !== false) {
                    $final_alt_text = mb_substr($final_alt_text, 0, $last_space);
                }
            }

            if ( !empty($final_alt_text) && ($final_alt_text !== $alt_existente) ) {
                 update_post_meta( $attachment_id, '_wp_attachment_image_alt', $final_alt_text );
                 // No añadir al log aquí, ya se hizo antes
            } elseif (empty($final_alt_text) && !empty($alt_existente) && !empty( $options['overwrite_alt'] ) ){
                 delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );
                 $log_summary[] = __('Alt existente borrado (overwrite).', 'mi-renombrador-imagenes');
            } elseif (!empty($alt_existente)) {
                 $log_summary[] = __('Alt existente conservado.', 'mi-renombrador-imagenes');
            }

        } else { // No sobrescribir y no estaba vacío
             $log_summary[] = __('Alt existente conservado.', 'mi-renombrador-imagenes');
        }
    } // fin if alt enabled
    // --- Fin PASO 3: Alt Text ---


    // --- PASO 4: Generación de Leyenda (Caption) ---
    if ( $is_caption_fallback_enabled ) {
        $adjunto_post = get_post( $attachment_id );
        $leyenda_existente = $adjunto_post ? $adjunto_post->post_excerpt : '';
        $caption_generado_valor = false;

        if ( $adjunto_post && ( ! empty( $options['overwrite_caption'] ) || empty( $leyenda_existente ) ) ) {
            // Intentar generar con IA
            if ( $is_ai_caption_enabled && $needs_api ) {
                // Leer imagen solo si no se ha leído antes y es compatible
                if ($imagen_base64 === null && $is_gemini_compatible_mime) {
                     $image_content_caption = @file_get_contents( $ruta_archivo_original );
                     if ($image_content_caption !== false) { $imagen_base64 = base64_encode($image_content_caption); unset($image_content_caption); if (!$imagen_base64){ /* log error */} }
                     else { /* log error */ $imagen_base64 = null; }
                }

                if ( $imagen_base64 ) {
                     $prompt_contexto_caption = '';
                     if (!empty($titulo_actual_adjunto)) { $prompt_contexto_caption .= sprintf(__(' Image title: "%s".', 'mi-renombrador-imagenes'), esc_html($titulo_actual_adjunto)); }
                     if (!empty($contexto_seo)) { $prompt_contexto_caption .= sprintf(__(' Context: %s', 'mi-renombrador-imagenes'), $contexto_seo); }

                     $prompt_caption = sprintf(
                         /* translators: %1$s: Language name (e.g., Español, English), %2$s: Context string */
                         __('Generate the response in %1$s. Analyze this image. Generate a brief and descriptive caption (1-2 short sentences) that provides interesting context or information about the image to display below it.%2$s Provide ONLY the final caption, without explanations or introductory text.', 'mi-renombrador-imagenes'),
                         $ai_language_name,
                         $prompt_contexto_caption
                     );

                    $caption_generado_api = mri_llamar_google_ai_api($prompt_caption, $gemini_api_key, $gemini_model, 100, $imagen_base64, $mime_type);

                    if ( $caption_generado_api !== false && !empty(trim($caption_generado_api)) ) {
                         $cleaned_caption = mri_clean_ai_response($caption_generado_api);
                         if (!empty($cleaned_caption)) {
                             $caption_generado_valor = $cleaned_caption;
                             $caption_generado_ia = true;
                         } else {
                              if ($is_bulk_process) { error_log("MRI Bulk Notice ID $attachment_id: AI Caption generation resulted in empty string after cleaning."); }
                         }
                    } else {
                         if ($is_bulk_process) { error_log("MRI Bulk Notice ID $attachment_id: AI Caption generation failed or returned empty."); }
                    }
                     unset($imagen_base64); // Limpiar base64 ahora
                } else if ($is_gemini_compatible_mime) {
                    if ($is_bulk_process) { error_log("MRI Bulk Error ID $attachment_id: Could not read image file for AI Caption generation."); }
                    unset($imagen_base64);
                }
            } // fin if AI caption enabled

            // Fallback
            if ( $caption_generado_valor === false ) {
                $caption_generado_valor = !empty($titulo_actual_adjunto) ? $titulo_actual_adjunto : '';
                $log_action = $caption_generado_ia ? __('Leyenda IA generada.', 'mi-renombrador-imagenes') : __('Leyenda fallback generada.', 'mi-renombrador-imagenes');
                $log_summary[] = $log_action;
            } elseif ($caption_generado_ia) {
                 $log_summary[] = __('Leyenda IA generada.', 'mi-renombrador-imagenes');
            }

            // Guardar la Leyenda
            $final_caption_text = wp_kses_post( trim($caption_generado_valor) );

            if ( ($final_caption_text !== $leyenda_existente) ) {
                $update_data = ['ID' => $attachment_id, 'post_excerpt' => $final_caption_text];
                remove_action('add_attachment', 'mri_attachment_processor', 20);
                wp_update_post($update_data);
                add_action('add_attachment', 'mri_attachment_processor', 20, 1);
            } elseif (empty($final_caption_text) && !empty($leyenda_existente) && !empty( $options['overwrite_caption'] )) {
                 $update_data = ['ID' => $attachment_id, 'post_excerpt' => ''];
                 remove_action('add_attachment', 'mri_attachment_processor', 20);
                 wp_update_post($update_data);
                 add_action('add_attachment', 'mri_attachment_processor', 20, 1);
                 $log_summary[] = __('Leyenda existente borrada (overwrite).', 'mi-renombrador-imagenes');
            } elseif (!empty($leyenda_existente)) {
                 $log_summary[] = __('Leyenda existente conservada.', 'mi-renombrador-imagenes');
            }

        } else { // No sobrescribir y no estaba vacía
             $log_summary[] = __('Leyenda existente conservada.', 'mi-renombrador-imagenes');
        }
    } // fin if caption enabled
    // --- Fin PASO 4: Leyenda ---

    // Limpieza final por si acaso
    unset($imagen_base64);

    // Devolver resumen para el log masivo
    if ($is_bulk_process) {
        $final_log = sprintf(__('ID %d: ', 'mi-renombrador-imagenes'), $attachment_id);
        if (empty($log_summary)) {
            $final_log .= __('Sin cambios.', 'mi-renombrador-imagenes');
        } else {
            $final_log .= implode(' ', $log_summary);
        }
        return $final_log;
    }

} // --- Fin función mri_procesar_imagen_subida_google_ai ---

// --- Enganchar el procesador al subir nueva imagen ---
/**
 * Función wrapper para llamar al procesador principal desde el hook add_attachment.
 */
function mri_attachment_processor( $attachment_id ) {
    if ( wp_is_post_revision($attachment_id) ) return;
    if ( get_post_meta($attachment_id, '_mri_processing_bulk', true) ) return; // Ya procesado por bulk
    if ( get_post_meta($attachment_id, '_mri_processing_upload', true) ) return; // Evitar doble ejecución en subida

    // Marcar como procesado en esta petición
    update_post_meta($attachment_id, '_mri_processing_upload', true);

    try {
        mri_procesar_imagen_subida_google_ai($attachment_id, false); // false indica NO es bulk
    } catch (Exception $e) {
        error_log("MRI Upload Processing Error ID $attachment_id: " . $e->getMessage());
    } finally {
        // Asegurarse de eliminar la bandera
        delete_post_meta($attachment_id, '_mri_processing_upload');
    }
}
// Usar prioridad 20 para ejecutarse después de metadatos iniciales
add_action( 'add_attachment', 'mri_attachment_processor', 20, 1 );


// --- Funciones adicionales ---
/** Carga el textdomain para traducciones. */
function mri_google_ai_load_textdomain() { load_plugin_textdomain( 'mi-renombrador-imagenes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); }
add_action( 'plugins_loaded', 'mri_google_ai_load_textdomain' );

// --- Limpieza en la desinstalación (Opcional) ---
/*
function mri_plugin_uninstall() {
    delete_option(MRI_SETTINGS_OPTION_NAME);
    // delete_post_meta_by_key('_mri_processing_bulk');
    // delete_post_meta_by_key('_mri_processing_upload');
}
register_uninstall_hook(__FILE__, 'mri_plugin_uninstall');
*/

?>