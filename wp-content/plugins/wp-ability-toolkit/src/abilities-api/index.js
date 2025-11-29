/**
 * WordPress Abilities API
 *
 * This entry point bundles @wordpress/abilities for registration
 * as the `wp-abilities` script handle in WordPress.
 *
 * Exports the full API to window.wp.abilities
 */
import * as abilities from '@wordpress/abilities';

// Expose on wp global for WordPress script system
window.wp = window.wp || {};
window.wp.abilities = abilities;

// Also export for module usage
export * from '@wordpress/abilities';
