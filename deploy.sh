#!/usr/bin/env bash
# ============================================================
#  EVEC TOURS — Namecheap shared hosting deploy script
#  Run this via SSH from the project root (one level above public/)
#
#  Usage:
#    chmod +x deploy.sh
#    ./deploy.sh
# ============================================================
set -e

# ── Config ────────────────────────────────────────────────
# Adjust PHP path if Namecheap uses a versioned binary
PHP=${PHP_BIN:-php}
COMPOSER=${COMPOSER_BIN:-composer}
APP_ENV=prod

echo ""
echo "=== EVEC TOURS — Production Deploy ==="
echo ""

# ── 1. Check PHP version ──────────────────────────────────
echo "[1/7] Checking PHP version..."
PHP_VER=$($PHP -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "      PHP $PHP_VER detected"

# Namecheap may need: PHP_BIN=php8.2 ./deploy.sh
# Common paths: /usr/local/bin/php, /opt/cpanel/ea-php82/root/usr/bin/php

# ── 2. Check .env.local ───────────────────────────────────
echo "[2/7] Checking .env.local..."
if [ ! -f ".env.local" ]; then
    echo ""
    echo "  ERROR: .env.local not found."
    echo "  Copy .env.production.example to .env.local and fill in all values."
    echo ""
    exit 1
fi

if grep -q "your_" .env.local 2>/dev/null; then
    echo ""
    echo "  WARNING: .env.local still contains placeholder values (\"your_\")."
    echo "  Make sure all keys are filled in before running in production."
    echo ""
fi
echo "      .env.local found"

# ── 3. Install dependencies (no dev, optimised autoloader) ─
echo "[3/7] Installing Composer dependencies..."
$COMPOSER install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --classmap-authoritative \
    --prefer-dist

# ── 4. Clear & warm up the production cache ───────────────
echo "[4/7] Clearing cache..."
$PHP bin/console cache:clear --env=$APP_ENV --no-interaction

echo "      Warming up cache..."
$PHP bin/console cache:warmup --env=$APP_ENV --no-interaction

# ── 5. Run database migrations ────────────────────────────
echo "[5/7] Running database migrations..."
$PHP bin/console doctrine:migrations:migrate \
    --env=$APP_ENV \
    --no-interaction \
    --allow-no-migration

# ── 6. Set directory permissions ──────────────────────────
echo "[6/7] Setting permissions on var/ and public/uploads/..."
chmod -R 775 var/ 2>/dev/null || true
chmod -R 775 public/uploads/ 2>/dev/null || mkdir -p public/uploads && chmod -R 775 public/uploads/

# ── 7. Verify front controller is reachable ──────────────
echo "[7/7] Sanity check — public/index.php exists: $([ -f public/index.php ] && echo YES || echo MISSING)"

echo ""
echo "=== Deploy complete ==="
echo ""
echo "Next steps:"
echo "  • In cPanel → Domains, set the subdomain Document Root to: /public_html/<folder>/public"
echo "  • Add the Messenger cron job (see below)"
echo ""
echo "──────────────────────────────────────────────────────"
echo " Messenger worker cron (add in cPanel → Cron Jobs)"
echo " Run every minute:"
echo ""
echo "   $PHP /home/<cpanel_user>/public_html/<folder>/bin/console messenger:consume async --limit=10 --time-limit=55 --env=prod >> /dev/null 2>&1"
echo ""
echo " Also add the Symfony Scheduler (if used):"
echo ""
echo "   $PHP /home/<cpanel_user>/public_html/<folder>/bin/console scheduler:run --env=prod >> /dev/null 2>&1"
echo "──────────────────────────────────────────────────────"
echo ""
