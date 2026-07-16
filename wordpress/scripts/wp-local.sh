#!/usr/bin/env bash
#
# wp-local.sh — run WP-CLI against the Local by Flywheel "visati" site (http://visati.local).
#
# Why this exists: Local's CLI php.exe loads NO php.ini by default, so mysqli/curl/etc.
# don't load and the DB connection fails. We enable extensions via -d flags and rely on
# the DB_HOST = 127.0.0.1:10005 fix already applied to wp-config.php.
#
# Usage:   bash wordpress/scripts/wp-local.sh <wp-cli command> [args...]
# Example: bash wordpress/scripts/wp-local.sh plugin list --status=active
#
# NOTE: paths are machine-specific (Local install + PHP version). If Local upgrades PHP,
# update the php-8.2.29+0 segment below. DB port (10005) is assigned per-site by Local.

set -euo pipefail

PHPEXE_BASH="/c/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe"
EXTWIN="C:/Users/K TECH/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/ext"
WPCLI="C:/Users/K TECH/AppData/Local/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar"
SITE="C:/Users/K TECH/Local Sites/visati/app/public"

exec "$PHPEXE_BASH" \
  -d extension_dir="$EXTWIN" \
  -d extension=mysqli \
  -d extension=pdo_mysql \
  -d extension=curl \
  -d extension=mbstring \
  -d extension=openssl \
  -d extension=zip \
  "$WPCLI" --path="$SITE" --allow-root "$@"
