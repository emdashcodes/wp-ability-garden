<?php
/**
 * Local Development Helpers
 *
 * MU-plugin that enables development-friendly settings for localhost.
 *
 * @package AbilityGarden
 */

// Enable application passwords on localhost (normally requires HTTPS).
add_filter( 'wp_is_application_passwords_available', '__return_true' );

/**
 * Handle Cloudflare Tunnel - dynamic URL based on access method.
 *
 * When accessed via tunnel: use https://ability-garden.emdashcodes.dev
 * When accessed locally: use http://localhost:8888
 */
function ability_garden_get_site_url() {
	// Check if accessed via Cloudflare tunnel
	if ( ! empty( $_SERVER['HTTP_CF_VISITOR'] ) ||
	     ( ! empty( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], 'ability-garden.emdashcodes.dev' ) !== false ) ) {
		return 'https://ability-garden.emdashcodes.dev';
	}
	return 'http://localhost:8888';
}

// Override site URLs via filter hooks (takes precedence over constants and database)
add_filter( 'option_siteurl', 'ability_garden_get_site_url' );
add_filter( 'option_home', 'ability_garden_get_site_url' );

// Set HTTPS flag for Cloudflare requests
if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}
