<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Microsoft Azure AI Translator engine for TranslatePress.
 */
class LWX_Azure_Machine_Translator extends TRP_Machine_Translator {

    const DEFAULT_ENDPOINT  = 'https://api.cognitive.microsofttranslator.com';
    const API_VERSION       = '3.0';
    const MAX_REQUEST_ITEMS = 25;
    const MAX_REQUEST_CHARS = 4800; // Azure limit is 5000; keep a small safety margin.

    /**
     * Translate an array of strings while preserving TranslatePress array keys.
     *
     * @param array       $new_strings          Strings to translate.
     * @param string      $target_language_code TranslatePress target language code.
     * @param string|null $source_language_code TranslatePress source language code.
     * @return array
     */
    public function translate_array( $new_strings, $target_language_code, $source_language_code = null ) {
        if ( null === $source_language_code ) {
            $source_language_code = isset( $this->settings['default-language'] ) ? $this->settings['default-language'] : '';
        }

        if ( empty( $new_strings ) || ! is_array( $new_strings ) || ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
            return array();
        }

        if ( get_transient( 'lwx_azure_translation_throttle' ) ) {
            return array();
        }

        $source = $this->map_language_code( $source_language_code );
        $target = $this->map_language_code( $target_language_code );

        if ( '' === $source || '' === $target || $source === $target || ! $this->target_is_allowed( $target ) ) {
            return array();
        }

        $translated    = array();
        $pending       = array();
        $pending_chars = 0;

        foreach ( $new_strings as $key => $string ) {
            $string = (string) $string;
            $length = $this->string_length( $string );

            if ( $length > self::MAX_REQUEST_CHARS ) {
                if ( ! empty( $pending ) ) {
                    $translated += $this->translate_batch_and_count( $pending, $source, $target );
                    $pending       = array();
                    $pending_chars = 0;
                }

                $long_translation = $this->translate_long_string( $string, $source, $target );
                if ( null !== $long_translation ) {
                    $translated[ $key ] = $long_translation;
                    $this->machine_translator_logger->count_towards_quota( array( $string ) );
                }

                if ( $this->machine_translator_logger->quota_exceeded() ) {
                    break;
                }
                continue;
            }

            $would_exceed_items = count( $pending ) >= self::MAX_REQUEST_ITEMS;
            $would_exceed_chars = ! empty( $pending ) && ( $pending_chars + $length > self::MAX_REQUEST_CHARS );

            if ( $would_exceed_items || $would_exceed_chars ) {
                $translated += $this->translate_batch_and_count( $pending, $source, $target );
                $pending       = array();
                $pending_chars = 0;

                if ( $this->machine_translator_logger->quota_exceeded() ) {
                    break;
                }
            }

            $pending[ $key ] = $string;
            $pending_chars  += $length;
        }

        if ( ! empty( $pending ) && ! $this->machine_translator_logger->quota_exceeded() ) {
            $translated += $this->translate_batch_and_count( $pending, $source, $target );
        }

        return $translated;
    }

    /**
     * Translate one normal-sized batch and update TranslatePress character quota only on success.
     *
     * @param array  $batch  Keyed strings.
     * @param string $source Azure source code.
     * @param string $target Azure target code.
     * @return array
     */
    private function translate_batch_and_count( $batch, $source, $target ) {
        $result = $this->perform_translation_request( $batch, $source, $target );
        if ( ! $result['success'] ) {
            if ( 'lwx_azure_monthly_limit_reached' !== $result['error_code'] ) {
                $this->apply_failure_throttle( $result['http_code'] );
            }
            return array();
        }

        $this->machine_translator_logger->count_towards_quota( array_values( $batch ) );
        return $result['translations'];
    }

    /**
     * Translate a string larger than Azure's per-request character limit.
     *
     * @param string $string Original string.
     * @param string $source Azure source code.
     * @param string $target Azure target code.
     * @return string|null
     */
    private function translate_long_string( $string, $source, $target ) {
        $parts       = $this->split_long_string( $string );
        $translations = array();

        foreach ( $parts as $part ) {
            $result = $this->perform_translation_request( array( 0 => $part ), $source, $target );
            if ( ! $result['success'] || ! isset( $result['translations'][0] ) ) {
                if ( 'lwx_azure_monthly_limit_reached' !== $result['error_code'] ) {
                    $this->apply_failure_throttle( $result['http_code'] );
                }
                return null;
            }
            $translations[] = trim( (string) $result['translations'][0] );
        }

        return implode( "\n", $translations );
    }

    /**
     * Split a long string into safe Unicode chunks, preferring natural boundaries.
     *
     * @param string $text Text to split.
     * @return array
     */
    private function split_long_string( $text ) {
        $parts     = array();
        $remaining = (string) $text;
        $max       = self::MAX_REQUEST_CHARS;

        while ( $this->string_length( $remaining ) > $max ) {
            $candidate = $this->string_substr( $remaining, 0, $max );
            $min_break = (int) floor( $max * 0.60 );
            $cut       = 0;

            foreach ( array( "\n", '. ', '! ', '? ', '; ', ', ', ' ' ) as $needle ) {
                $pos = $this->string_strrpos( $candidate, $needle );
                if ( false !== $pos && $pos >= $min_break && $pos > $cut ) {
                    $cut = $pos + $this->string_length( $needle );
                }
            }

            if ( $cut <= 0 ) {
                $cut = $max;
            }

            $parts[]   = $this->string_substr( $remaining, 0, $cut );
            $remaining = $this->string_substr( $remaining, $cut );
        }

        if ( '' !== $remaining ) {
            $parts[] = $remaining;
        }

        return $parts;
    }

    /**
     * Call Azure Translator and parse a batch response.
     *
     * @param array  $batch  Keyed strings.
     * @param string $source Azure source language.
     * @param string $target Azure target language.
     * @return array
     */
    private function perform_translation_request( $batch, $source, $target ) {
        $response = $this->send_request( array_values( $batch ), $source, $target );
        $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

        $this->log_request( $batch, $response, $source, $target );

        $return = array(
            'success'      => false,
            'translations' => array(),
            'http_code'    => $code,
            'error_code'   => is_wp_error( $response ) ? (string) $response->get_error_code() : '',
        );

        if ( is_wp_error( $response ) || 200 !== $code ) {
            return $return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || count( $body ) !== count( $batch ) ) {
            return $return;
        }

        $keys         = array_keys( $batch );
        $translations = array();

        foreach ( $body as $index => $item ) {
            if ( ! isset( $keys[ $index ] ) || ! isset( $item['translations'][0] ) || ! array_key_exists( 'text', $item['translations'][0] ) ) {
                return $return;
            }

            $text = (string) $item['translations'][0]['text'];
            if ( '' === $text && '' !== (string) $batch[ $keys[ $index ] ] ) {
                return $return;
            }

            $translations[ $keys[ $index ] ] = $text;
        }

        $return['success']      = true;
        $return['translations'] = $translations;
        return $return;
    }

    /**
     * Send a raw Azure Translator request.
     *
     * @param array  $strings Plain strings.
     * @param string $source  Azure language code.
     * @param string $target  Azure language code.
     * @return array|WP_Error
     */
    public function send_request( $strings, $source, $target ) {
        $characters = 0;
        foreach ( (array) $strings as $string ) {
            $characters += $this->string_length( (string) $string );
        }

        $monthly_limit = $this->get_monthly_limit();
        if ( $monthly_limit > 0 && ! lwx_azure_reserve_monthly_usage( $characters, $monthly_limit ) ) {
            $used = lwx_azure_get_monthly_usage();

            return new WP_Error(
                'lwx_azure_monthly_limit_reached',
                sprintf(
                    /* translators: 1: characters already used, 2: configured monthly hard limit. */
                    __( 'LioX Web Azure monthly hard limit reached (%1$s / %2$s characters). No request was sent to Microsoft Azure.', 'liox-web-azure-translator-for-translatepress' ),
                    number_format_i18n( $used ),
                    number_format_i18n( $monthly_limit )
                )
            );
        }

        $body = array();
        foreach ( $strings as $string ) {
            $body[] = array( 'Text' => (string) $string );
        }

        $headers = array(
            'Content-Type'              => 'application/json; charset=UTF-8',
            'Ocp-Apim-Subscription-Key' => $this->get_api_key(),
        );

        $region = $this->get_region();
        if ( '' !== $region ) {
            $headers['Ocp-Apim-Subscription-Region'] = $region;
        }

        $url = add_query_arg(
            array(
                'api-version' => self::API_VERSION,
                'from'        => $source,
                'to'          => $target,
                'textType'    => 'plain',
            ),
            $this->get_translate_endpoint()
        );

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 45,
                'headers' => $headers,
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( ! is_wp_error( $response ) ) {
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( 200 === $code ) {
                $this->cache_validation_result( true, '', 43200 );
            } elseif ( in_array( $code, array( 401, 403 ), true ) ) {
                $this->cache_validation_result( false, $this->get_azure_error_message( $code, wp_remote_retrieve_body( $response ) ), 300 );
            }
        }

        return $response;
    }

    /**
     * Lightweight request used by TranslatePress to verify credentials.
     *
     * @return array|WP_Error
     */
    public function test_request() {
        return $this->send_request( array( 'TranslatePress test' ), 'en', 'fr' );
    }

    /**
     * Build a short fingerprint for the currently configured Azure credentials.
     * The API key itself is never stored in the transient name or value.
     *
     * @return string
     */
    private function get_credentials_fingerprint() {
        $parts = array(
            (string) $this->get_api_key(),
            (string) $this->get_region(),
            (string) $this->get_translate_endpoint(),
        );

        return substr( hash( 'sha256', implode( '|', $parts ) ), 0, 32 );
    }

    /**
     * Transient key used to cache credential validation for the current configuration.
     *
     * @return string
     */
    private function get_validation_transient_key() {
        return 'lwx_azure_api_check_' . $this->get_credentials_fingerprint();
    }

    /**
     * Store a credential validation result for a short period.
     *
     * @param bool   $valid   Whether the credentials are valid.
     * @param string $message Optional error message.
     * @param int    $ttl     Cache lifetime in seconds.
     * @return void
     */
    private function cache_validation_result( $valid, $message = '', $ttl = 43200 ) {
        set_transient(
            $this->get_validation_transient_key(),
            array(
                'valid'   => (bool) $valid,
                'message' => (string) $message,
            ),
            max( 60, (int) $ttl )
        );
    }

    /**
     * Return a cached validation result, if available.
     *
     * @return array|null
     */
    private function get_cached_validation_result() {
        $cached = get_transient( $this->get_validation_transient_key() );
        if ( ! is_array( $cached ) || ! array_key_exists( 'valid', $cached ) ) {
            return null;
        }

        return array(
            'message' => ! empty( $cached['message'] ) ? (string) $cached['message'] : '',
            'error'   => empty( $cached['valid'] ),
        );
    }

    /**
     * Passive frontend availability checks must never generate billable/metered
     * Azure translation requests. A live probe is limited to normal admin screens.
     *
     * @return bool
     */
    private function should_run_live_credential_probe() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
            return false;
        }

        if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
            return false;
        }

        return true;
    }

    /**
     * Validate API credentials and configuration for TranslatePress' settings UI.
     *
     * @return array{message:string,error:bool}
     */
    public function check_api_key_validity() {
        $engine  = isset( $this->settings['trp_machine_translation_settings']['translation-engine'] ) ? (string) $this->settings['trp_machine_translation_settings']['translation-engine'] : '';
        $enabled = isset( $this->settings['trp_machine_translation_settings']['machine-translation'] ) ? (string) $this->settings['trp_machine_translation_settings']['machine-translation'] : 'no';

        if ( LWX_AZURE_ENGINE !== $engine || 'yes' !== $enabled ) {
            return array( 'message' => '', 'error' => false );
        }

        if ( null !== $this->correct_api_key ) {
            return $this->correct_api_key;
        }

        if ( empty( $this->get_api_key() ) ) {
            $this->correct_api_key = array(
                'message' => __( 'Please enter your Azure Translator API key.', 'liox-web-azure-translator-for-translatepress' ),
                'error'   => true,
            );
            return $this->correct_api_key;
        }

        $cached = $this->get_cached_validation_result();
        if ( null !== $cached ) {
            $this->correct_api_key = $cached;
            return $this->correct_api_key;
        }

        /*
         * TranslatePress can ask whether the API key is valid during normal
         * frontend rendering. Do not turn that passive check into a real Azure
         * translation request. Actual translation calls will validate the
         * credentials naturally, while normal admin screens may perform one
         * cached live probe.
         */
        if ( ! $this->should_run_live_credential_probe() ) {
            $this->correct_api_key = array( 'message' => '', 'error' => false );
            return $this->correct_api_key;
        }

        $response = $this->test_request();

        if ( is_wp_error( $response ) ) {
            $message = sprintf(
                /* translators: %s: WordPress HTTP error. */
                __( 'Azure connection error: %s', 'liox-web-azure-translator-for-translatepress' ),
                $response->get_error_message()
            );
            $this->cache_validation_result( false, $message, 300 );
            $this->correct_api_key = array(
                'message' => $message,
                'error'   => true,
            );
            return $this->correct_api_key;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 === $code ) {
            $this->cache_validation_result( true, '', 43200 );
            $this->correct_api_key = array( 'message' => '', 'error' => false );
            return $this->correct_api_key;
        }

        $message = $this->get_azure_error_message( $code, wp_remote_retrieve_body( $response ) );
        $this->cache_validation_result( false, $message, 300 );
        $this->correct_api_key = array(
            'message' => $message,
            'error'   => true,
        );
        return $this->correct_api_key;
    }

    /**
     * Get the configured local monthly hard limit.
     *
     * @return int Zero means disabled.
     */
    private function get_monthly_limit() {
        if ( ! isset( $this->settings['trp_machine_translation_settings']['lwx-azure-monthly-limit'] ) ) {
            return 1900000;
        }

        return max( 0, absint( $this->settings['trp_machine_translation_settings']['lwx-azure-monthly-limit'] ) );
    }

    /**
     * Get the configured API key.
     *
     * @return string|false
     */
    public function get_api_key() {
        $key = isset( $this->settings['trp_machine_translation_settings']['lwx-azure-api-key'] )
            ? trim( (string) $this->settings['trp_machine_translation_settings']['lwx-azure-api-key'] )
            : '';

        return '' !== $key ? $key : false;
    }

    /**
     * Return Azure-supported translation language codes for TranslatePress.
     * The languages endpoint does not require authentication.
     *
     * @return array
     */
    public function get_supported_languages() {
        $cached = get_transient( 'lwx_azure_supported_languages' );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $url = add_query_arg(
            array(
                'api-version' => self::API_VERSION,
                'scope'       => 'translation',
            ),
            self::DEFAULT_ENDPOINT . '/languages'
        );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body['translation'] ) && is_array( $body['translation'] ) ) {
                $languages = array_keys( $body['translation'] );
                set_transient( 'lwx_azure_supported_languages', $languages, DAY_IN_SECONDS );
                return $languages;
            }
        }

        // Conservative fallback covering common TranslatePress / e-commerce languages.
        return array(
            'ar','bg','cs','da','de','el','en','es','et','fi','fr','he','hr','hu','id','it','ja','ko','lt','lv',
            'nb','nl','pl','pt','ro','ru','sk','sl','sr-Cyrl','sr-Latn','sv','th','tr','uk','vi','zh-Hans','zh-Hant',
        );
    }

    /**
     * Convert TranslatePress language codes/locales to Azure language codes.
     *
     * @param array $languages TranslatePress language codes.
     * @return array
     */
    public function get_engine_specific_language_codes( $languages ) {
        $languages = (array) $languages;
        $iso_codes = array();

        if ( isset( $this->trp_languages ) && is_object( $this->trp_languages ) && method_exists( $this->trp_languages, 'get_iso_codes' ) ) {
            $iso_codes = (array) $this->trp_languages->get_iso_codes( $languages, false );
        }

        $codes = array();
        foreach ( $languages as $language ) {
            $candidate = isset( $iso_codes[ $language ] ) ? $iso_codes[ $language ] : $language;
            $code      = $this->map_language_code( $candidate );
            if ( '' !== $code ) {
                $codes[] = $code;
            }
        }

        return array_values( array_unique( $codes ) );
    }

    /**
     * Restrict Azure to an optional user-provided target-language whitelist.
     *
     * @param string $to_language TranslatePress target code.
     * @return bool
     */
    public function extra_request_validations( $to_language ) {
        return $this->target_is_allowed( $this->map_language_code( $to_language ) );
    }

    /**
     * Map a WordPress/TranslatePress locale to Azure's text translation code.
     *
     * @param string $language_code Locale/code.
     * @return string
     */
    private function map_language_code( $language_code ) {
        $raw        = trim( str_replace( '_', '-', (string) $language_code ) );
        $normalized = strtolower( $raw );

        $special = array(
            'zh-cn' => 'zh-Hans',
            'zh-sg' => 'zh-Hans',
            'zh-hans' => 'zh-Hans',
            'zh-tw' => 'zh-Hant',
            'zh-hk' => 'zh-Hant',
            'zh-mo' => 'zh-Hant',
            'zh-hant' => 'zh-Hant',
            'sr-latn' => 'sr-Latn',
            'sr-latn-rs' => 'sr-Latn',
            'sr-cyrl' => 'sr-Cyrl',
            'sr-cyrl-rs' => 'sr-Cyrl',
            'no-no' => 'nb',
            'nb-no' => 'nb',
        );

        if ( isset( $special[ $normalized ] ) ) {
            return $special[ $normalized ];
        }

        $parts = explode( '-', $normalized );
        $base  = isset( $parts[0] ) ? preg_replace( '/[^a-z]/', '', $parts[0] ) : '';

        return (string) $base;
    }

    /**
     * Check optional target language whitelist.
     *
     * @param string $target Azure code.
     * @return bool
     */
    private function target_is_allowed( $target ) {
        $raw = isset( $this->settings['trp_machine_translation_settings']['lwx-azure-target-languages'] )
            ? trim( (string) $this->settings['trp_machine_translation_settings']['lwx-azure-target-languages'] )
            : '';

        if ( '' === $raw ) {
            return true;
        }

        $allowed = preg_split( '/[\s,;|]+/', $raw );
        if ( ! is_array( $allowed ) ) {
            return true;
        }

        $normalized = array();
        foreach ( $allowed as $code ) {
            $mapped = $this->map_language_code( $code );
            if ( '' !== $mapped ) {
                $normalized[] = strtolower( $mapped );
            }
        }

        return in_array( strtolower( $target ), array_unique( $normalized ), true );
    }

    /**
     * Build the final /translate endpoint from the configured Azure base endpoint.
     *
     * @return string
     */
    private function get_translate_endpoint() {
        $endpoint = isset( $this->settings['trp_machine_translation_settings']['lwx-azure-endpoint'] )
            ? trim( (string) $this->settings['trp_machine_translation_settings']['lwx-azure-endpoint'] )
            : '';

        if ( '' === $endpoint ) {
            $endpoint = self::DEFAULT_ENDPOINT;
        }

        $endpoint = untrailingslashit( $endpoint );
        $path     = (string) wp_parse_url( $endpoint, PHP_URL_PATH );
        $host     = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) );

        if ( '/translate' === substr( strtolower( $path ), -10 ) ) {
            return $endpoint;
        }

        // Azure VNET/custom-domain Text Translation uses /translator/text/v3.0/translate.
        if ( false !== strpos( strtolower( $path ), '/translator/text/v3.0' ) ) {
            return $endpoint . '/translate';
        }

        if ( preg_match( '/\.cognitiveservices\.azure\.com$/', $host ) ) {
            return $endpoint . '/translator/text/v3.0/translate';
        }

        // Global and geography-specific Translator endpoints use /translate.
        return $endpoint . '/translate';
    }

    /**
     * Get optional Azure region.
     *
     * @return string
     */
    private function get_region() {
        $region = isset( $this->settings['trp_machine_translation_settings']['lwx-azure-region'] )
            ? strtolower( trim( (string) $this->settings['trp_machine_translation_settings']['lwx-azure-region'] ) )
            : '';

        return preg_replace( '/[^a-z0-9-]/', '', $region );
    }

    /**
     * Log a request using TranslatePress' own machine-translation logger.
     *
     * @param array                $batch    Keyed strings.
     * @param array|WP_Error       $response HTTP response.
     * @param string               $source   Source code.
     * @param string               $target   Target code.
     * @return void
     */
    private function log_request( $batch, $response, $source, $target ) {
        if ( ! is_object( $this->machine_translator_logger ) || ! method_exists( $this->machine_translator_logger, 'log' ) ) {
            return;
        }

        if ( is_wp_error( $response ) ) {
            $logged_response = array(
                'error'   => true,
                'message' => $response->get_error_message(),
            );
        } else {
            $logged_response = array(
                'code' => (int) wp_remote_retrieve_response_code( $response ),
                'body' => wp_remote_retrieve_body( $response ),
            );
        }

        $this->machine_translator_logger->log(
            array(
                'strings'     => serialize( $batch ),
                'response'    => serialize( $logged_response ),
                'lang_source' => $source,
                'lang_target' => $target,
            )
        );
    }

    /**
     * Avoid hammering Azure repeatedly after an API/network error.
     *
     * @param int $http_code HTTP status code, 0 for transport errors.
     * @return void
     */
    private function apply_failure_throttle( $http_code ) {
        $seconds = ( 429 === (int) $http_code ) ? 60 : 20;
        set_transient( 'lwx_azure_translation_throttle', 1, (int) apply_filters( 'lwx_azure_throttle_seconds', $seconds, $http_code ) );
    }

    /**
     * Human-friendly Azure error for TranslatePress admin.
     *
     * @param int    $code HTTP status.
     * @param string $body Raw response body.
     * @return string
     */
    private function get_azure_error_message( $code, $body ) {
        $remote = '';
        $json   = json_decode( (string) $body, true );
        if ( is_array( $json ) && ! empty( $json['error']['message'] ) ) {
            $remote = sanitize_text_field( (string) $json['error']['message'] );
        }

        switch ( (int) $code ) {
            case 400:
                $message = __( 'Azure rejected the request. Check the endpoint, region and language configuration.', 'liox-web-azure-translator-for-translatepress' );
                break;
            case 401:
            case 403:
                $message = __( 'Azure authentication failed. Check the API key and, for regional resources, the Azure Region.', 'liox-web-azure-translator-for-translatepress' );
                break;
            case 429:
                $message = __( 'Azure rate or quota limit was reached.', 'liox-web-azure-translator-for-translatepress' );
                break;
            case 404:
                $message = __( 'Azure Translator endpoint was not found. Check the Endpoint setting.', 'liox-web-azure-translator-for-translatepress' );
                break;
            default:
                $message = sprintf(
                    /* translators: %d: HTTP status code. */
                    __( 'Azure Translator returned HTTP %d.', 'liox-web-azure-translator-for-translatepress' ),
                    (int) $code
                );
                break;
        }

        if ( '' !== $remote ) {
            $message .= ' ' . $remote;
        }

        return $message;
    }

    /** Unicode-safe string length with fallback. */
    private function string_length( $text ) {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
    }

    /** Unicode-safe substring with fallback. */
    private function string_substr( $text, $start, $length = null ) {
        if ( function_exists( 'mb_substr' ) ) {
            return null === $length ? mb_substr( $text, $start, null, 'UTF-8' ) : mb_substr( $text, $start, $length, 'UTF-8' );
        }
        return null === $length ? substr( $text, $start ) : substr( $text, $start, $length );
    }

    /** Unicode-safe reverse strpos with fallback. */
    private function string_strrpos( $haystack, $needle ) {
        return function_exists( 'mb_strrpos' ) ? mb_strrpos( $haystack, $needle, 0, 'UTF-8' ) : strrpos( $haystack, $needle );
    }
}
