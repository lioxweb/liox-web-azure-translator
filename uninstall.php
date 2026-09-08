<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$lwx_azure_settings = get_option( 'trp_machine_translation_settings', array() );
if ( is_array( $lwx_azure_settings ) ) {
    foreach ( array( 'lwx-azure-api-key', 'lwx-azure-region', 'lwx-azure-endpoint', 'lwx-azure-target-languages', 'lwx-azure-monthly-limit' ) as $lwx_azure_key ) {
        unset( $lwx_azure_settings[ $lwx_azure_key ] );
    }
    update_option( 'trp_machine_translation_settings', $lwx_azure_settings );
}

delete_transient( 'lwx_azure_supported_languages' );
delete_transient( 'lwx_azure_translation_throttle' );


// Remove local monthly usage counters created by LioX Web Azure Translator.
global $wpdb;
$lwx_azure_like = $wpdb->esc_like( 'lwx_azure_monthly_usage_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup removes only plugin-owned usage options.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $lwx_azure_like ) );
