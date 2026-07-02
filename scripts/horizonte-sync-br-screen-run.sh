#!/usr/bin/env bash
# Invocado pelo GNU screen — wrapper resistente a SIGHUP/TERM acidental e reinício após falha.
# Não executar directamente; use horizonte-sync-br-screen.sh start
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

NOHUP_LOG="$ROOT/storage/logs/horizonte-sync-br-nohup.log"
WANTED_FILE="$ROOT/storage/logs/horizonte-sync-br.wanted"
RESTART_MAX="${HORIZONTE_SYNC_BR_SCREEN_RESTART_MAX:-50}"
RESTART_SLEEP="${HORIZONTE_SYNC_BR_SCREEN_RESTART_SLEEP:-60}"
SIGNAL_SLEEP="${HORIZONTE_SYNC_BR_SIGNAL_RESTART_SLEEP:-15}"

mkdir -p storage/logs

# Ignorar sinais no runner — o loop decide com base em horizonte-sync-br.wanted
trap '' HUP TERM INT

log_runner() {
  echo "[$(date -Iseconds)] [runner] $*" >>"$NOHUP_LOG"
}

wanted_active() {
  [[ -f "$WANTED_FILE" ]]
}

log_runner "Iniciado (PID $$, user=$(whoami), PPID=$PPID, TTY=${TTY:-none}, wanted=$(wanted_active && echo sim || echo nao))"

if [[ ! -x "$ROOT/scripts/horizonte-sync-br-continue.sh" ]]; then
  log_runner "ERRO: continue.sh não executável."
  exit 1
fi

if ! wanted_active; then
  log_runner "AVISO: wanted ausente — a sair (use start para activar)."
  exit 0
fi

export HORIZONTE_SAEB_MEMORY_LIMIT="${HORIZONTE_SAEB_MEMORY_LIMIT:-2048M}"
export HORIZONTE_EDUCACENSO_MEMORY_LIMIT="${HORIZONTE_EDUCACENSO_MEMORY_LIMIT:-1024M}"
export HORIZONTE_CADUNICO_FILL_GAPS="${HORIZONTE_CADUNICO_FILL_GAPS:-true}"
export HORIZONTE_SYNC_BR_MAX_ROUNDS="${HORIZONTE_SYNC_BR_MAX_ROUNDS:-200}"

RESTART=0

while wanted_active; do
  set +e
  if command -v setsid >/dev/null 2>&1; then
    setsid "$ROOT/scripts/horizonte-sync-br-continue.sh" >>"$NOHUP_LOG" 2>&1
  else
    "$ROOT/scripts/horizonte-sync-br-continue.sh" >>"$NOHUP_LOG" 2>&1
  fi
  EXIT=$?
  set -e

  if ! wanted_active; then
    log_runner "Flag wanted removida — runner a terminar (último código ${EXIT})."
    exit 0
  fi

  if (( EXIT == 0 )); then
    log_runner "Sync concluído com sucesso — a remover wanted e terminar."
    rm -f "$WANTED_FILE"
    exit 0
  fi

  if (( EXIT == 130 || EXIT == 143 )); then
    log_runner "Sinal durante sync (código ${EXIT}) — wanted activo, reinício em ${SIGNAL_SLEEP}s…"
    RESTART=0
    sleep "$SIGNAL_SLEEP"
    continue
  fi

  RESTART=$((RESTART + 1))
  if (( RESTART >= RESTART_MAX )); then
    log_runner "ERRO: ${RESTART} falhas consecutivas — parar (wanted mantido para ensure/cron)."
    exit "$EXIT"
  fi

  log_runner "Sync terminou com código ${EXIT} — reinício ${RESTART}/${RESTART_MAX} em ${RESTART_SLEEP}s…"
  sleep "$RESTART_SLEEP"
done

log_runner "Loop terminado."
exit 0
