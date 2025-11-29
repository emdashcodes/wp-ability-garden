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

# Start wp-env
echo "Starting WordPress environment..."
npx wp-env start

# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready..."
sleep 5

# Activate plugins
echo "Activating plugins..."
npx wp-env run cli wp plugin activate gutenberg 2>/dev/null || true
npx wp-env run cli wp plugin activate wp-ability-toolkit 2>/dev/null || true

# Import database if it exists
if [ -f "site/database.sql" ]; then
    echo "Importing existing database..."
    npx wp-env run cli wp db import /var/www/html/site/database.sql 2>/dev/null || true
fi

echo ""
echo "=========================================="
echo "  Environment Ready!"
echo "=========================================="
echo ""
echo "  WordPress: http://localhost:8888"
echo "  Admin:     http://localhost:8888/wp-admin/"
echo "  Username:  admin"
echo "  Password:  password"
echo "=========================================="
