# Bug: @wordpress/abilities npm package wipes client-registered abilities

**Date discovered:** 2024-11-29
**Affects:** `@wordpress/abilities` npm package v0.2.0
**Fixed in:** `wordpress/abilities-api` Composer package v0.4.0

## Summary

The `@wordpress/abilities` npm package (v0.2.0) has a critical bug where client-side registered abilities are wiped from the store when `getAbilities()` is called.

## Root Cause

Two issues in the npm package:

### 1. Reducer replaces state instead of merging

In `store/reducer.js`, the `RECEIVE_ABILITIES` action handler:

```javascript
// BUG in v0.2.0 - creates empty state, wiping client abilities
case RECEIVE_ABILITIES: {
  const newState = {};  // <-- Problem: starts fresh
  action.abilities.forEach((ability) => {
    newState[ability.name] = sanitizeAbility(ability);
  });
  return newState;  // Client abilities are gone!
}
```

### 2. getAbilities uses resolveSelect which triggers resolver

In `api.js`:

```javascript
// BUG: resolveSelect triggers the resolver
export async function getAbilities(args = {}) {
  return resolveSelect(store).getAbilities(args);
}
```

The resolver fetches server-side abilities and dispatches `RECEIVE_ABILITIES`, which replaces the entire store state.

## Symptoms

```javascript
// Register a client-side ability
await registerAbility(myAbility);
console.log('Registered!');

// Try to get all abilities
const abilities = await getAbilities();
console.log(abilities);  // [] - empty! Client ability is gone
```

## Fix in v0.4.0

The Composer package `wordpress/abilities-api` v0.4.0 fixes both issues:

### 1. Reducer now merges state

```javascript
// FIXED in v0.4.0 - spreads existing state first
case RECEIVE_ABILITIES: {
  if (!action.abilities) return state;
  const newState = { ...state };  // <-- Preserves existing abilities
  action.abilities.forEach((ability) => {
    newState[ability.name] = sanitizeAbility(ability);
  });
  return newState;
}
```

### 2. Resolver checks for client abilities before fetching

```javascript
// FIXED: Only fetches if no client abilities exist
function getAbilities() {
  return async ({ dispatch, registry, select }) => {
    // If any abilities have callbacks (client-side), skip server fetch
    if (select.getAbilities().some(a => !a.callback)) {
      return;
    }
    // Otherwise fetch from server...
  };
}
```

## Workaround for npm package

If you must use the npm package, you can override `getAbilities`:

```javascript
import * as abilities from '@wordpress/abilities';
import { select } from '@wordpress/data';

// Use select() instead of resolveSelect() to avoid triggering resolver
async function getAbilitiesFixed(args = {}) {
  return select(abilities.store).getAbilities(args);
}

window.wp.abilities = {
  ...abilities,
  getAbilities: getAbilitiesFixed,
};
```

**However**, this workaround has issues with bundled `@wordpress/data` copies creating separate store registries.

## Recommended Solution

Use the Composer package instead of npm:

```json
{
  "require": {
    "wordpress/abilities-api": "v0.4.0"
  }
}
```

Then register the pre-built JS from `vendor/wordpress/abilities-api/packages/client/build/index.js` as your `wp-abilities` script handle.

## Related Files

- `wp-ability-toolkit/includes/class-plugin.php` - registers wp-abilities script from vendor
- `wp-ability-toolkit/webpack.config.js` - externalizes @wordpress/abilities to wp.abilities global
