#!/bin/bash
# WordPress Ability Garden - Environment Setup
# This script initializes the wp-env environment for ability development

set -e

echo "=========================================="
echo "  WordPress Ability Garden"
echo "  Autonomous Ability Development"
echo "=========================================="
echo ""

# Check for Docker
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed or not running"
    exit 1
fi

# Check if wp-env is available
if ! npx wp-env --version &> /dev/null; then
    echo "Installing @wordpress/env..."
    npm install @wordpress/env --save-dev
fi

# Stop any existing wp-env to ensure clean state
echo "Stopping any existing wp-env..."
npx wp-env stop 2>/dev/null || true

# Setup WooCommerce from trunk
WOOCOMMERCE_DEV_DIR="./woocommerce-dev"

if [ ! -d "$WOOCOMMERCE_DEV_DIR" ]; then
    echo ""
    echo "Setting up WooCommerce from trunk..."
    echo "=========================================="

    # Clone the monorepo (shallow for speed)
    echo "Cloning WooCommerce monorepo..."
    git clone --depth 1 --branch trunk https://github.com/woocommerce/woocommerce.git "$WOOCOMMERCE_DEV_DIR"

    # Install dependencies and build
    cd "$WOOCOMMERCE_DEV_DIR"

    echo "Installing dependencies (this may take a few minutes)..."
    pnpm install

    echo "Building WooCommerce plugin..."
    pnpm --filter='@woocommerce/plugin-woocommerce' build

    cd ..
    echo "WooCommerce trunk build complete!"
    echo ""
else
    echo "WooCommerce dev directory exists, skipping clone/build"
    echo "  (To update: cd woocommerce-dev && git pull && pnpm install && pnpm --filter='@woocommerce/plugin-woocommerce' build)"
fi

# Start wp-env
echo "Starting WordPress environment..."
npx wp-env start

# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready..."
sleep 5

# Set site URLs for Cloudflare tunnel
echo "Setting site URLs..."
npx wp-env run cli wp config set WP_SITEURL https://ability-garden.emdashcodes.dev
npx wp-env run cli wp config set WP_HOME https://ability-garden.emdashcodes.dev

# Activate plugins
echo "Activating plugins..."
npx wp-env run cli wp plugin activate woocommerce 2>/dev/null || true
npx wp-env run cli wp plugin activate jetpack 2>/dev/null || true
npx wp-env run cli wp plugin activate wp-ability-toolkit 2>/dev/null || true
npx wp-env run cli wp plugin activate garden-abilities 2>/dev/null || true

# Set up pretty permalinks
echo "Configuring permalinks..."
npx wp-env run cli -- bash -c 'cat > /var/www/html/.htaccess << "HTACCESS"
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS' 2>/dev/null || true
npx wp-env run cli wp rewrite structure '/%postname%/' --hard 2>/dev/null || true
npx wp-env run cli wp rewrite flush --hard 2>/dev/null || true

# Import database if it exists
if [ -f "site/database.sql" ]; then
    echo "Importing existing database..."
    npx wp-env run cli wp db import /var/www/html/site/database.sql 2>/dev/null || true
fi

# Create application password for API testing
echo "Setting up API authentication..."
APP_PASSWORD=$(npx wp-env run cli wp user application-password create admin 'Agent Testing' --porcelain 2>/dev/null | tr -d '\r\n' || true)
if [ -n "$APP_PASSWORD" ]; then
    echo "$APP_PASSWORD" > site/app-password.txt
    echo "  Application password saved to site/app-password.txt"
fi

echo ""
echo "=========================================="
echo "  Environment Ready!"
echo "=========================================="
echo ""
echo "  Site:      https://ability-garden.emdashcodes.dev"
echo "  Admin:     https://ability-garden.emdashcodes.dev/wp-admin/"
echo "  Username:  admin"
echo "  Password:  password"
echo ""
echo "  Start tunnel: cloudflared tunnel run ability-garden"
echo "=========================================="
