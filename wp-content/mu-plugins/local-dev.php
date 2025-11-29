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
