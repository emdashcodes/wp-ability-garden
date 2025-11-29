<?php
/**
 * Local Development Helpers - Force tunnel URL everywhere
 */

// Force HTTPS early
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;

// Enable application passwords
add_filter( 'wp_is_application_passwords_available', '__return_true' );

// Tunnel URL constant for reuse
define( 'LOCAL_DEV_TUNNEL_URL', 'https://ability-garden.emdashcodes.dev' );

/**
 * Replace localhost URLs with tunnel URL
 */
function local_dev_replace_url( $url ) {
    if ( empty( $url ) ) {
        return $url;
    }
    return str_replace(
        array( 'http://localhost:8888', 'https://localhost:8888', 'http://localhost' ),
        LOCAL_DEV_TUNNEL_URL,
        $url
    );
}

// Core URL filters - run late (PHP_INT_MAX) to ensure we're the last to modify
add_filter( 'option_siteurl', function() { return LOCAL_DEV_TUNNEL_URL; }, PHP_INT_MAX );
add_filter( 'option_home', function() { return LOCAL_DEV_TUNNEL_URL; }, PHP_INT_MAX );

// URL generation filters
add_filter( 'plugins_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'content_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'site_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'home_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'admin_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'includes_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'network_site_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'network_home_url', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'network_admin_url', 'local_dev_replace_url', PHP_INT_MAX );

// Script and style sources - critical for preventing mixed content
add_filter( 'script_loader_src', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'style_loader_src', 'local_dev_replace_url', PHP_INT_MAX );

// Theme URLs
add_filter( 'template_directory_uri', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'stylesheet_directory_uri', 'local_dev_replace_url', PHP_INT_MAX );
add_filter( 'theme_root_uri', 'local_dev_replace_url', PHP_INT_MAX );

// Upload directory
add_filter( 'upload_dir', function( $uploads ) {
    $uploads['url'] = local_dev_replace_url( $uploads['url'] );
    $uploads['baseurl'] = local_dev_replace_url( $uploads['baseurl'] );
    return $uploads;
}, PHP_INT_MAX );

// REST API URL
add_filter( 'rest_url', 'local_dev_replace_url', PHP_INT_MAX );

// Catch-all for any remaining URLs in script data
add_filter( 'wp_localize_script', function( $l10n ) {
    if ( is_array( $l10n ) ) {
        array_walk_recursive( $l10n, function( &$value ) {
            if ( is_string( $value ) ) {
                $value = local_dev_replace_url( $value );
            }
        });
    }
    return $l10n;
}, PHP_INT_MAX );
