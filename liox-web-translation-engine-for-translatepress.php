<?php
/**
 * Plugin Name: LioX Web Translation Engine with Microsoft Azure Translator for TranslatePress
 * Plugin URI: https://github.com/lioxweb/liox-web-translation-engine-for-translatepress
 * Description: Adds Microsoft Azure Translator as an automatic translation engine for TranslatePress.
 * Version: 1.1.4
 * Author: LioX Web
 * Author URI: https://lioxweb.com/
 * Text Domain: liox-web-translation-engine-for-translatepress
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: translatepress-multilingual
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LWX_AZURE_TRANSLATOR_VERSION', '1.1.4' );
define( 'LWX_AZURE_TRANSLATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LWX_AZURE_ENGINE', 'liox_azure_translator' );

/*
 * Register directly with WordPress hooks. The trp_before_running_hooks bridge
 * below also registers through TranslatePress' internal loader, covering
 * different TranslatePress load orders.
 */
add_filter( 'trp_machine_translation_engines', 'lwx_azure_add_engine', 15 );
add_filter( 'trp_automatic_translation_engines_classes', 'lwx_azure_add_engine_class', 15 );
add_action( 'trp_machine_translation_extra_settings_middle', 'lwx_azure_render_settings', 15 );
add_filter( 'trp_machine_translation_sanitize_settings', 'lwx_azure_sanitize_settings', 15, 2 );
add_filter( 'trp_get_default_trp_machine_translation_settings', 'lwx_azure_default_settings', 15 );
add_action( 'trp_before_running_hooks', 'lwx_azure_register_with_trp_loader', 1, 1 );
add_action( 'admin_notices', 'lwx_azure_dependency_notice' );

/**
 * Register hooks through TranslatePress' internal loader as a compatibility path.
 *
 * @param mixed $loader TranslatePress hooks loader.
 * @return void
 */
function lwx_azure_register_with_trp_loader( $loader ) {
    if ( ! is_object( $loader ) || ! method_exists( $loader, 'add_filter' ) || ! method_exists( $loader, 'add_action' ) ) {
        return;
    }

    $loader->add_filter( 'trp_machine_translation_engines', null, 'lwx_azure_add_engine', 15, 1 );
    $loader->add_filter( 'trp_automatic_translation_engines_classes', null, 'lwx_azure_add_engine_class', 15, 1 );
    $loader->add_action( 'trp_machine_translation_extra_settings_middle', null, 'lwx_azure_render_settings', 15, 1 );
    $loader->add_filter( 'trp_machine_translation_sanitize_settings', null, 'lwx_azure_sanitize_settings', 15, 2 );
    $loader->add_filter( 'trp_get_default_trp_machine_translation_settings', null, 'lwx_azure_default_settings', 15, 1 );
}

/**
 * Add Azure to the TranslatePress engine selector.
 *
 * @param array $engines Existing engines.
 * @return array
 */
function lwx_azure_add_engine( $engines ) {
    $engines  = is_array( $engines ) ? $engines : array();
    $by_value = array();

    foreach ( $engines as $engine ) {
        if ( is_array( $engine ) && ! empty( $engine['value'] ) ) {
            $by_value[ (string) $engine['value'] ] = $engine;
        }
    }

    $by_value[ LWX_AZURE_ENGINE ] = array(
        'value' => LWX_AZURE_ENGINE,
        'label' => __( 'Microsoft Azure Translator (LioX Web)', 'liox-web-translation-engine-for-translatepress' ),
    );

    return array_values( $by_value );
}

/**
 * Load and register the Azure translator class.
 *
 * @param array $classes Engine => class mappings.
 * @return array
 */
function lwx_azure_add_engine_class( $classes ) {
    $classes = is_array( $classes ) ? $classes : array();

    if ( ! class_exists( 'TRP_Machine_Translator' ) ) {
        return $classes;
    }

    require_once LWX_AZURE_TRANSLATOR_DIR . 'includes/class-lwx-azure-machine-translator.php';
    $classes[ LWX_AZURE_ENGINE ] = 'LWX_Azure_Machine_Translator';

    return $classes;
}

/**
 * Add plugin defaults to TranslatePress machine translation settings.
 *
 * @param array $defaults Existing defaults.
 * @return array
 */
function lwx_azure_default_settings( $defaults ) {
    $defaults = is_array( $defaults ) ? $defaults : array();

    $custom = array(
        'lwx-azure-api-key'         => '',
        'lwx-azure-region'          => '',
        'lwx-azure-endpoint'        => 'https://api.cognitive.microsofttranslator.com',
        'lwx-azure-target-languages'=> '',
        'lwx-azure-monthly-limit'    => '1900000',
    );

    foreach ( $custom as $key => $value ) {
        if ( ! isset( $defaults[ $key ] ) ) {
            $defaults[ $key ] = $value;
        }
    }

    return $defaults;
}

/**
 * Sanitize settings submitted on TranslatePress -> Automatic Translation.
 *
 * @param array $settings Sanitized settings being built by TranslatePress.
 * @param array $raw      Raw machine-translation settings.
 * @return array
 */
function lwx_azure_sanitize_settings( $settings, $raw ) {
    $settings = is_array( $settings ) ? $settings : array();
    $raw      = is_array( $raw ) ? $raw : array();

    if ( isset( $raw['lwx-azure-api-key'] ) ) {
        $settings['lwx-azure-api-key'] = sanitize_text_field( wp_unslash( $raw['lwx-azure-api-key'] ) );
    }

    if ( isset( $raw['lwx-azure-region'] ) ) {
        $region = strtolower( sanitize_text_field( wp_unslash( $raw['lwx-azure-region'] ) ) );
        $region = preg_replace( '/[^a-z0-9-]/', '', $region );
        $settings['lwx-azure-region'] = $region;
    }

    if ( isset( $raw['lwx-azure-endpoint'] ) ) {
        $endpoint = esc_url_raw( wp_unslash( $raw['lwx-azure-endpoint'] ), array( 'https' ) );
        if ( empty( $endpoint ) || 0 !== strpos( $endpoint, 'https://' ) ) {
            $endpoint = 'https://api.cognitive.microsofttranslator.com';
        }
        $settings['lwx-azure-endpoint'] = untrailingslashit( $endpoint );
    }

    if ( isset( $raw['lwx-azure-target-languages'] ) ) {
        $settings['lwx-azure-target-languages'] = lwx_azure_sanitize_language_list( $raw['lwx-azure-target-languages'] );
    }

    if ( isset( $raw['lwx-azure-monthly-limit'] ) ) {
        $monthly_limit = absint( wp_unslash( $raw['lwx-azure-monthly-limit'] ) );
        $settings['lwx-azure-monthly-limit'] = (string) min( $monthly_limit, 1000000000 );
    }

    return $settings;
}

/**
 * Normalize a comma/space separated language-code list.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function lwx_azure_sanitize_language_list( $value ) {
    $value = sanitize_text_field( wp_unslash( (string) $value ) );
    if ( '' === trim( $value ) ) {
        return '';
    }

    $parts = preg_split( '/[\s,;|]+/', $value );
    if ( ! is_array( $parts ) ) {
        return '';
    }

    $codes = array();
    foreach ( $parts as $part ) {
        $part = trim( str_replace( '_', '-', (string) $part ) );
        $part = preg_replace( '/[^A-Za-z0-9-]/', '', $part );
        if ( '' !== $part ) {
            $codes[] = $part;
        }
    }

    return implode( ',', array_values( array_unique( $codes ) ) );
}

/**
 * Current Azure usage period. We use UTC to keep the counter aligned with a cloud-service month boundary.
 *
 * @return string YYYYMM.
 */
function lwx_azure_current_usage_period() {
    return gmdate( 'Ym' );
}

/**
 * Option name used for the current month's local Azure character counter.
 *
 * @param string $period Optional YYYYMM period.
 * @return string
 */
function lwx_azure_monthly_usage_option_name( $period = '' ) {
    $period = preg_replace( '/[^0-9]/', '', (string) $period );
    if ( 6 !== strlen( $period ) ) {
        $period = lwx_azure_current_usage_period();
    }

    return 'lwx_azure_monthly_usage_' . $period;
}

/**
 * Read the locally tracked number of Azure input characters sent this month.
 *
 * @return int
 */
function lwx_azure_get_monthly_usage() {
    return max( 0, (int) get_option( lwx_azure_monthly_usage_option_name(), 0 ) );
}

/**
 * Atomically reserve characters before an Azure translation request is sent.
 * This prevents parallel WordPress requests from stepping over the configured hard limit.
 *
 * @param int $characters Number of source characters about to be sent.
 * @param int $limit      Monthly hard limit. Zero disables the plugin cap.
 * @return bool True when the request is allowed and the characters were reserved.
 */
function lwx_azure_reserve_monthly_usage( $characters, $limit ) {
    global $wpdb;

    $characters = max( 0, (int) $characters );
    $limit      = max( 0, (int) $limit );

    if ( 0 === $characters || 0 === $limit ) {
        return true;
    }

    if ( $characters > $limit ) {
        return false;
    }

    $option_name = lwx_azure_monthly_usage_option_name();

    // Ensure the row exists. add_option() is safe if another request creates it first.
    add_option( $option_name, 0, '', 'no' );

    // Atomic update is intentional here so concurrent requests cannot exceed the configured monthly cap.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- A single atomic UPDATE is required for concurrency safety.
    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = CAST(option_value AS UNSIGNED) + %d
             WHERE option_name = %s
               AND CAST(option_value AS UNSIGNED) + %d <= %d",
            $characters,
            $option_name,
            $characters,
            $limit
        )
    );

    wp_cache_delete( $option_name, 'options' );

    return 1 === (int) $updated;
}

/**
 * Render Azure settings inside TranslatePress' Automatic Translation page.
 *
 * @param array $mt_settings Machine translation settings.
 * @return void
 */
function lwx_azure_render_settings( $mt_settings ) {
    static $rendered = false;
    if ( $rendered ) {
        return;
    }
    $rendered = true;

    $mt_settings = is_array( $mt_settings ) ? $mt_settings : array();
    $engine      = isset( $mt_settings['translation-engine'] ) ? (string) $mt_settings['translation-engine'] : '';

    $show_error    = false;
    $error_message = '';

    if ( LWX_AZURE_ENGINE === $engine && class_exists( 'TRP_Translate_Press' ) ) {
        $trp = TRP_Translate_Press::get_trp_instance();
        if ( is_object( $trp ) && method_exists( $trp, 'get_component' ) ) {
            $translator = $trp->get_component( 'machine_translator' );
            if ( is_object( $translator ) && method_exists( $translator, 'check_api_key_validity' ) ) {
                $check = $translator->check_api_key_validity();
                if ( ! empty( $check['error'] ) ) {
                    $show_error    = true;
                    $error_message = ! empty( $check['message'] ) ? (string) $check['message'] : __( 'Azure Translator connection failed.', 'liox-web-translation-engine-for-translatepress' );
                }
            }
        }
    }

    $api_key  = isset( $mt_settings['lwx-azure-api-key'] ) ? (string) $mt_settings['lwx-azure-api-key'] : '';
    $region   = isset( $mt_settings['lwx-azure-region'] ) ? (string) $mt_settings['lwx-azure-region'] : '';
    $endpoint = ! empty( $mt_settings['lwx-azure-endpoint'] ) ? (string) $mt_settings['lwx-azure-endpoint'] : 'https://api.cognitive.microsofttranslator.com';
    $targets       = isset( $mt_settings['lwx-azure-target-languages'] ) ? (string) $mt_settings['lwx-azure-target-languages'] : '';
    $monthly_limit = isset( $mt_settings['lwx-azure-monthly-limit'] ) ? absint( $mt_settings['lwx-azure-monthly-limit'] ) : 1900000;
    $monthly_used  = lwx_azure_get_monthly_usage();
    $monthly_left  = 0 === $monthly_limit ? 0 : max( 0, $monthly_limit - $monthly_used );
    ?>
    <div class="trp-engine trp-automatic-translation-engine__container" id="<?php echo esc_attr( LWX_AZURE_ENGINE ); ?>">
        <span class="trp-primary-text-bold"><?php esc_html_e( 'Azure Translator API Key', 'liox-web-translation-engine-for-translatepress' ); ?></span>
        <div class="trp-automatic-translation-api-key-container">
            <input
                type="password"
                class="trp-text-input<?php echo $show_error ? ' trp-text-input-error' : ''; ?>"
                name="trp_machine_translation_settings[lwx-azure-api-key]"
                value="<?php echo esc_attr( $api_key ); ?>"
                autocomplete="new-password"
                placeholder="<?php esc_attr_e( 'Paste Key 1 or Key 2 from Azure', 'liox-web-translation-engine-for-translatepress' ); ?>"
            />
            <?php
            if ( LWX_AZURE_ENGINE === $engine && isset( $translator ) && is_object( $translator ) && method_exists( $translator, 'automatic_translation_svg_output' ) ) {
                $translator->automatic_translation_svg_output( $show_error );
            }
            ?>
        </div>

        <?php if ( $show_error ) : ?>
            <span class="trp-error-inline trp-settings-error-text"><?php echo esc_html( $error_message ); ?></span>
        <?php endif; ?>

        <span class="trp-description-text">
            <?php esc_html_e( 'Use a key from Azure Portal -> Translator resource -> Keys and Endpoint.', 'liox-web-translation-engine-for-translatepress' ); ?>
        </span>

        <div class="trp-deepl-settings__container" style="margin-top:16px;">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Azure Region', 'liox-web-translation-engine-for-translatepress' ); ?></span>
            <input
                type="text"
                class="trp-text-input"
                name="trp_machine_translation_settings[lwx-azure-region]"
                value="<?php echo esc_attr( $region ); ?>"
                placeholder="westeurope"
            />
            <span class="trp-description-text">
                <?php esc_html_e( 'Required for regional or multi-service resources. Leave blank for a global single-service Translator resource.', 'liox-web-translation-engine-for-translatepress' ); ?>
            </span>
        </div>

        <div class="trp-deepl-settings__container" style="margin-top:16px;">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Azure Endpoint', 'liox-web-translation-engine-for-translatepress' ); ?></span>
            <input
                type="url"
                class="trp-text-input"
                name="trp_machine_translation_settings[lwx-azure-endpoint]"
                value="<?php echo esc_attr( $endpoint ); ?>"
            />
            <span class="trp-description-text">
                <?php esc_html_e( 'Normally leave the default. Paste a custom Azure endpoint only if your resource requires one.', 'liox-web-translation-engine-for-translatepress' ); ?>
            </span>
        </div>

        <div class="trp-deepl-settings__container" style="margin-top:16px;">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Azure Target Languages (optional)', 'liox-web-translation-engine-for-translatepress' ); ?></span>
            <input
                type="text"
                class="trp-text-input"
                name="trp_machine_translation_settings[lwx-azure-target-languages]"
                value="<?php echo esc_attr( $targets ); ?>"
                placeholder="en,fr,de,it,es,nl"
            />
            <span class="trp-description-text">
                <?php esc_html_e( 'Comma-separated Azure language codes. Leave blank to allow every supported target language, including English. If you use a list and want new English strings translated too, include en. Example: en,fr,de,it,es,nl.', 'liox-web-translation-engine-for-translatepress' ); ?>
            </span>
        </div>

        <div class="trp-deepl-settings__container" style="margin-top:16px;">
            <span class="trp-primary-text-bold"><?php esc_html_e( 'Azure Monthly Hard Limit (characters)', 'liox-web-translation-engine-for-translatepress' ); ?></span>
            <input
                type="number"
                min="0"
                step="1000"
                class="trp-text-input"
                name="trp_machine_translation_settings[lwx-azure-monthly-limit]"
                value="<?php echo esc_attr( $monthly_limit ); ?>"
                placeholder="1900000"
            />
            <span class="trp-description-text">
                <?php esc_html_e( 'LioX Web safety cap for Azure requests. Default: 1,900,000 characters per calendar month. Set 0 to disable this plugin-level cap.', 'liox-web-translation-engine-for-translatepress' ); ?>
            </span>
            <span class="trp-description-text" style="display:block;margin-top:6px;">
                <strong><?php esc_html_e( 'Current month:', 'liox-web-translation-engine-for-translatepress' ); ?></strong>
                <?php
                if ( 0 === $monthly_limit ) {
                    printf(
                        /* translators: %s: used character count. */
                        esc_html__( '%s characters tracked — hard limit disabled.', 'liox-web-translation-engine-for-translatepress' ),
                        esc_html( number_format_i18n( $monthly_used ) )
                    );
                } else {
                    printf(
                        /* translators: 1: used characters, 2: monthly limit, 3: remaining characters. */
                        esc_html__( '%1$s / %2$s used — %3$s remaining.', 'liox-web-translation-engine-for-translatepress' ),
                        esc_html( number_format_i18n( $monthly_used ) ),
                        esc_html( number_format_i18n( $monthly_limit ) ),
                        esc_html( number_format_i18n( $monthly_left ) )
                    );
                }
                ?>
            </span>
            <span class="trp-description-text" style="display:block;margin-top:4px;">
                <?php esc_html_e( 'The local counter resets automatically when the UTC calendar month changes. It counts Azure translation requests sent by this WordPress installation, including explicit API credential tests.', 'liox-web-translation-engine-for-translatepress' ); ?>
            </span>
        </div>
    </div>
    <?php
}

/**
 * Show a dependency notice only when TranslatePress is unavailable.
 *
 * @return void
 */
function lwx_azure_dependency_notice() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    if ( class_exists( 'TRP_Translate_Press' ) || defined( 'TRP_PLUGIN_VERSION' ) ) {
        return;
    }

    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'LioX Web Translation Engine:', 'liox-web-translation-engine-for-translatepress' ); ?></strong>
            <?php esc_html_e( 'TranslatePress must be installed and active for this plugin to work.', 'liox-web-translation-engine-for-translatepress' ); ?>
        </p>
    </div>
    <?php
}
