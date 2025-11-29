/**
 * WordPress Abilities API
 *
 * This entry point bundles @wordpress/abilities for registration
 * as the `wp-abilities` script handle in WordPress.
 *
 * Exports the full API to window.wp.abilities
 *
 * IMPORTANT: We override getAbilities to use select() instead of resolveSelect()
 * because the resolver fetches from server and REPLACES the store state,
 * wiping out client-registered abilities. This is a bug in @wordpress/abilities.
 */
import * as abilities from '@wordpress/abilities';
import { select } from '@wordpress/data';

// Expose on wp global for WordPress script system
window.wp = window.wp || {};

/**
 * Fixed getAbilities that uses select() instead of resolveSelect()
 * to preserve client-registered abilities.
 *
 * The original getAbilities uses resolveSelect which triggers a resolver
 * that fetches server-side abilities and REPLACES the entire store state,
 * wiping out any client-registered abilities.
 *
 * @param {Object} args - Optional filter arguments
 * @param {string} args.category - Filter by category
 * @returns {Promise<Array>} Array of abilities
 */
async function getAbilitiesFixed(args = {}) {
	// Use select() which returns current state without triggering resolver
	return select(abilities.store).getAbilities(args);
}

// Create a patched abilities object with our fixed getAbilities
window.wp.abilities = {
	...abilities,
	getAbilities: getAbilitiesFixed,
};

// Also export for module usage
export * from '@wordpress/abilities';

// Override getAbilities export
export { getAbilitiesFixed as getAbilities };
