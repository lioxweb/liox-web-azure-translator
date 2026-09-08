<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$settings = get_option( 'trp_machine_translation_settings', array() );
if ( is_array( $settings ) ) {
    foreach ( array( 'lwx-azure-api-key', 'lwx-azure-region', 'lwx-azure-endpoint', 'lwx-azure-target-languages', 'lwx-azure-monthly-limit' ) as $key ) {
        unset( $settings[ $key ] );
    }
    update_option( 'trp_machine_translation_settings', $settings );
}

delete_transient( 'lwx_azure_supported_languages' );
delete_transient( 'lwx_azure_translation_throttle' );


// Remove local monthly usage counters created by LioX Web Azure Translator.
global $wpdb;
$like = $wpdb->esc_like( 'lwx_azure_monthly_usage_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
