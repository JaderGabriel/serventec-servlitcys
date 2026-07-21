#!/usr/bin/env bash
# Corre todos os testes do módulo Clio (Unit + Feature) com pdo_sqlite disponível.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
chmod +x "$ROOT/scripts/php-with-sqlite.sh"
exec "$ROOT/scripts/php-with-sqlite.sh" vendor/bin/phpunit --colors=always --filter Clio "$@"
