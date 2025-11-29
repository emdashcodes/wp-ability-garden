# Garden Abilities

Abilities created by autonomous agents in the WordPress Ability Garden experiment.

## Structure

```
garden-abilities/
├── garden-abilities.php     # Main plugin file
├── includes/
│   └── abilities/           # PHP (server-side) abilities
│       └── *.php
├── src/
│   ├── client-abilities.js  # Entry point for client-side abilities
│   └── abilities/           # JavaScript (client-side) abilities
│       └── *.js
├── build/                   # Built JavaScript (gitignored)
└── package.json
```

## Adding Abilities

### Server-side (PHP)

Create a new file in `includes/abilities/`:

```php
<?php
// includes/abilities/my-ability.php

add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'garden-abilities/my-ability', array(
        'label'       => 'My Ability',
        'description' => 'What this ability does',
        'category'    => 'data-retrieval',
        'callback'    => function( $input ) {
            // Implementation
            return array( 'result' => 'success' );
        },
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(),
        ),
    ) );
} );
```

### Client-side (JavaScript)

1. Create a new file in `src/abilities/`:

```javascript
// src/abilities/my-ability.js
import { registerAbility } from '@wordpress/abilities';

registerAbility( {
    name: 'garden-abilities/my-ability',
    label: 'My Ability',
    description: 'What this ability does',
    category: 'navigation',
    callback: async ( input ) => {
        // Implementation
        return { result: 'success' };
    },
} );
```

2. Import it in `src/client-abilities.js`:

```javascript
import './abilities/my-ability.js';
```

3. Rebuild: `pnpm run build`

## Building

```bash
pnpm install
pnpm run build
```

For development with auto-rebuild:

```bash
pnpm run start
```
