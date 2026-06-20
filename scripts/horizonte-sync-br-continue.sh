#!/usr/bin/env bash
# Completa o abastecimento Horizonte BR (--all) até não haver fases/UFs/anos pendentes.
set -euo pipefail
cd "$(dirname "$0")/.."

LOG="storage/logs/horizonte-sync-br-$(date +%Y%m%d).log"
MAX_ROUNDS=120
ROUND=0

log() { echo "[$(date -Iseconds)] $*" | tee -a "$LOG"; }

log "Horizonte sync BR — início (máx. ${MAX_ROUNDS} rondas)"

while (( ROUND < MAX_ROUNDS )); do
  ROUND=$((ROUND + 1))
  log "Ronda ${ROUND}/${MAX_ROUNDS}"

  OUT=$(php artisan horizonte:fortnightly-feed --all --continue 2>&1) || true
  echo "$OUT" | tee -a "$LOG"

  if echo "$OUT" | grep -q "Nenhum abastecimento --all em curso para continuar"; then
    log "Pipeline --all concluído (idle)."
    break
  fi
  if echo "$OUT" | grep -q "Abastecimento Horizonte concluído"; then
    log "Mensagem de conclusão detectada."
    # Verificar se ainda há IBGE/SAEB parciais
    PENDING=$(php artisan horizonte:fortnightly-feed --dry-run -v 2>&1 || true)
    if echo "$PENDING" | grep -qE "IBGE pendente|SAEB pendente|Pendente \(--all\)"; then
      log "Ainda há pendências — nova ronda."
      sleep 30
      continue
    fi
    log "Sem pendências no dry-run — fim."
    break
  fi

  sleep 60
done

log "Invalidando cache do mapa Horizonte"
php artisan cache:clear 2>&1 | tee -a "$LOG" || true

log "Cobertura final:"
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$c = app(App\Services\Admin\HorizonteImportHubStatusService::class)->build()['coverage'];
foreach (\$c as \$k => \$v) { echo \"  \$k: \$v\n\"; }
" 2>&1 | tee -a "$LOG"

log "Horizonte sync BR — terminado"
