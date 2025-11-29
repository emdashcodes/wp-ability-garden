/**
 * Debug logging utility
 * Set WP_ABILITY_TOOLKIT_DEBUG in localStorage to enable debug logs
 */
const isDebugEnabled = () => {
    if (typeof window === 'undefined') {
        return false;
    }
    return localStorage.getItem('WP_ABILITY_TOOLKIT_DEBUG') === 'true';
};
export const debug = (...args) => {
    if (isDebugEnabled()) {
        console.log(...args);
    }
};
//# sourceMappingURL=debug.js.map