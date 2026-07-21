#!/usr/bin/env bash
# PHP CLI com pdo_sqlite carregado a partir de tools/php-ext (sem apt root).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EXT="$ROOT/tools/php-ext"
SQLITE_SO="$EXT/sqlite3.so"
PDO_SO="$EXT/pdo_sqlite.so"

ensure_ext() {
  if [[ -f "$SQLITE_SO" && -f "$PDO_SO" ]]; then
    return 0
  fi
  echo "A obter php8.4-sqlite3 (deb) para tools/php-ext…" >&2
  mkdir -p "$EXT" /tmp/php-sqlite-deb-clio
  (
    cd /tmp/php-sqlite-deb-clio
    apt-get download php8.4-sqlite3 >/dev/null
    dpkg-deb -x ./*.deb ./extract
    cp -f ./extract/usr/lib/php/*/sqlite3.so "$SQLITE_SO"
    cp -f ./extract/usr/lib/php/*/pdo_sqlite.so "$PDO_SO"
  )
}

ensure_ext
exec php -d extension="$SQLITE_SO" -d extension="$PDO_SO" "$@"
