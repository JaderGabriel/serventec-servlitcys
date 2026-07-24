#!/usr/bin/env bash
# Corre a suite PHPUnit (Unit + Feature) com pdo_sqlite disponível.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
chmod +x "$ROOT/scripts/php-with-sqlite.sh"
"$ROOT/scripts/php-with-sqlite.sh" artisan config:clear --ansi
exec "$ROOT/scripts/php-with-sqlite.sh" artisan test "$@"
