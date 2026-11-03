#!/usr/bin/env bash
set -euo pipefail
PROJECT="${1:-/var/www/sales-dashboard}"
PHP_BIN="${BUSINESS_ANALYTICS_PHP_BINARY:-$(command -v php)}"
HERE="$(cd "$(dirname "$0")" && pwd)"

echo "[1/8] Apply upgrade files/routes..."
"$PHP_BIN" "$HERE/apply_upgrade.php" "$PROJECT"

echo "[2/8] PHP syntax checks..."
"$PHP_BIN" -l "$PROJECT/routes/web.php"
"$PHP_BIN" -l "$PROJECT/app/Http/Controllers/BusinessAnalyticsController.php"
"$PHP_BIN" -l "$PROJECT/app/Http/Controllers/ProductStrategyAdvisorController.php"
"$PHP_BIN" -l "$PROJECT/app/Http/Controllers/BusinessAnalyticsSyncController.php"

echo "[3/8] Verify DomPDF dependency..."
"$PHP_BIN" -r "require '$PROJECT/vendor/autoload.php'; exit(class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')?0:1);" || { echo '[ERROR] barryvdh/laravel-dompdf tidak tersedia.'; exit 1; }

cd "$PROJECT"
echo "[4/8] Migration sqlite_targets..."
"$PHP_BIN" artisan migrate --database=sqlite_targets --force

echo "[5/8] Clear + compile views..."
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan view:cache

echo "[6/8] Standalone regression tests..."
"$PHP_BIN" tests/Unit/BusinessAnalytics/BusinessAnalyticsPdfPresenterStandaloneTest.php
"$PHP_BIN" tests/Unit/BusinessAnalytics/AnalyticsSyncProgressCalculatorStandaloneTest.php
"$PHP_BIN" tests/Unit/BusinessAnalytics/BackgroundSyncCommandBuilderStandaloneTest.php

echo "[7/8] Verify artisan command..."
"$PHP_BIN" artisan list | grep -q 'analytics:web-sync'

echo "[8/8] Verify routes..."
"$PHP_BIN" artisan route:list --path=business-analytics | grep -E 'export.pdf|sync|product-advisor'

echo 'UPGRADE v1.4 COMPLETE'
