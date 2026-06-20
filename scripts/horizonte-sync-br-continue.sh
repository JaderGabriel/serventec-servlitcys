#!/usr/bin/env bash
# Completa o abastecimento Horizonte BR (--all) em loop até não haver fases/UFs/anos pendentes.
#
# Uso:
#   ./scripts/horizonte-sync-br-continue.sh
#   nohup ./scripts/horizonte-sync-br-continue.sh >> storage/logs/horizonte-sync-br-nohup.log 2>&1 &
#
# Interromper: kill $(cat storage/logs/horizonte-sync-br.lock 2>/dev/null)  # ou Ctrl+C se em foreground
set -euo pipefail
cd "$(dirname "$0")/.."

LOG="storage/logs/horizonte-sync-br-$(date +%Y%m%d).log"
LOCK_FILE="storage/logs/horizonte-sync-br.lock"
PID_FILE="storage/logs/horizonte-sync-br.pid"
MAX_ROUNDS="${HORIZONTE_SYNC_BR_MAX_ROUNDS:-200}"
SLEEP_OK="${HORIZONTE_SYNC_BR_SLEEP_OK:-45}"
SLEEP_PARTIAL="${HORIZONTE_SYNC_BR_SLEEP_PARTIAL:-20}"
SLEEP_ERROR="${HORIZONTE_SYNC_BR_SLEEP_ERROR:-90}"
ROUND=0

mkdir -p storage/logs

log() { echo "[$(date -Iseconds)] $*" | tee -a "$LOG"; }

has_pending() {
  local pending
  pending=$(php artisan horizonte:fortnightly-feed --dry-run -v 2>&1 || true)
  echo "$pending" | grep -qE 'Pendente \(--all\)|IBGE pendente|SAEB pendente|Abastecimento parcial'
}

cleanup() {
  rm -f "$PID_FILE"
  log "Sync BR interrompido (sinal recebido)."
  exit 130
}

trap cleanup INT TERM

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "Outro horizonte-sync-br-continue.sh já está a correr (lock: $LOCK_FILE)." >&2
  exit 1
fi

echo $$ >"$PID_FILE"

if pgrep -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1; then
  log "AVISO: já existe 'php artisan horizonte:fortnightly-feed' activo."
  log "Interrompa-o (Ctrl+C) ou aguarde terminar — evite duas importações em paralelo."
  log "A aguardar até 30 minutos…"
  waited=0
  while pgrep -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1 && (( waited < 1800 )); do
    sleep 30
    waited=$((waited + 30))
  done
  if pgrep -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1; then
    log "ERRO: feed manual ainda activo após espera — abortar para evitar conflito."
    exit 1
  fi
  log "Feed manual terminou — a continuar com o loop."
fi

log "Horizonte sync BR — início (máx. ${MAX_ROUNDS} rondas, log: ${LOG})"
export HORIZONTE_SAEB_MEMORY_LIMIT="${HORIZONTE_SAEB_MEMORY_LIMIT:-2048M}"
export HORIZONTE_CADUNICO_FILL_GAPS="${HORIZONTE_CADUNICO_FILL_GAPS:-true}"
export HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP="${HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP:-1}"
export HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP="${HORIZONTE_FORTNIGHTLY_SAEB_YEARS_PER_STEP:-1}"

log "Env: SAEB_MEM=${HORIZONTE_SAEB_MEMORY_LIMIT} CADUNICO_FILL_GAPS=${HORIZONTE_CADUNICO_FILL_GAPS} IBGE_UFS/step=${HORIZONTE_FORTNIGHTLY_IBGE_UFS_PER_STEP}"

while (( ROUND < MAX_ROUNDS )); do
  ROUND=$((ROUND + 1))
  log "—— Ronda ${ROUND}/${MAX_ROUNDS} ——"

  set +e
  OUT=$(php artisan horizonte:fortnightly-feed --all --continue 2>&1)
  EXIT=$?
  set -e
  echo "$OUT" | tee -a "$LOG"

  if echo "$OUT" | grep -qiE 'Allowed memory size|out of memory|Killed'; then
    log "OOM detectado — nova ronda após ${SLEEP_ERROR}s (confirme HORIZONTE_SAEB_MEMORY_LIMIT)."
    sleep "$SLEEP_ERROR"
    continue
  fi

  if echo "$OUT" | grep -q "Nenhum abastecimento --all em curso para continuar"; then
    if has_pending; then
      log "Idle mas dry-run ainda reporta pendências — nova ronda."
      sleep "$SLEEP_PARTIAL"
      continue
    fi
    log "Pipeline --all concluído (idle + dry-run limpo)."
    break
  fi

  if echo "$OUT" | grep -qE 'Abastecimento Horizonte concluído|Abastecimento parcial'; then
    if echo "$OUT" | grep -q "Abastecimento parcial"; then
      log "Parcial — retomar em ${SLEEP_PARTIAL}s."
      sleep "$SLEEP_PARTIAL"
      continue
    fi
    if has_pending; then
      log "Mensagem de conclusão mas dry-run ainda pendente — nova ronda."
      sleep "$SLEEP_PARTIAL"
      continue
    fi
    log "Concluído sem pendências no dry-run."
    break
  fi

  if (( EXIT != 0 )); then
    log "Artisan saiu com código ${EXIT} — nova ronda em ${SLEEP_ERROR}s."
    sleep "$SLEEP_ERROR"
    continue
  fi

  log "Ronda terminada — pausa ${SLEEP_OK}s antes da próxima verificação."
  sleep "$SLEEP_OK"
done

if (( ROUND >= MAX_ROUNDS )); then
  log "AVISO: limite de rondas (${MAX_ROUNDS}) atingido — verifique pendências manualmente."
fi

log "Invalidando cache do mapa Horizonte"
php artisan cache:clear 2>&1 | tee -a "$LOG" || true

log "Cobertura final (hub):"
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$c = app(App\Services\Admin\HorizonteImportHubStatusService::class)->build()['coverage'];
foreach (\$c as \$k => \$v) { echo \"  \$k: \$v\n\"; }
" 2>&1 | tee -a "$LOG"

rm -f "$PID_FILE"
log "Horizonte sync BR — terminado (rondas: ${ROUND})"
