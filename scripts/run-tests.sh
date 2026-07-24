#!/usr/bin/env bash
# Corre a suite PHPUnit (Unit + Feature) com pdo_sqlite disponível.
# Usa vendor/bin/phpunit directamente: `artisan test` re-lança PHP sem -d extension.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
chmod +x "$ROOT/scripts/php-with-sqlite.sh"
"$ROOT/scripts/php-with-sqlite.sh" artisan config:clear --ansi >/dev/null
exec "$ROOT/scripts/php-with-sqlite.sh" vendor/bin/phpunit "$@"
