#!/usr/bin/env bash
# Invocado pelo GNU screen — wrapper resistente a SIGHUP e reinício após falha inesperada.
# Não executar directamente; use horizonte-sync-br-screen.sh start
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

NOHUP_LOG="$ROOT/storage/logs/horizonte-sync-br-nohup.log"
RESTART_MAX="${HORIZONTE_SYNC_BR_SCREEN_RESTART_MAX:-50}"
RESTART_SLEEP="${HORIZONTE_SYNC_BR_SCREEN_RESTART_SLEEP:-60}"

mkdir -p storage/logs

# Ignorar hangup (fecho SSH se alguém estiver attached sem Ctrl+A D)
trap '' HUP

log_runner() {
  echo "[$(date -Iseconds)] [runner] $*" >>"$NOHUP_LOG"
}

on_term() {
  log_runner "Recebeu TERM — a sair."
  exit 143
}
trap on_term TERM

log_runner "Iniciado (PID $$, user=$(whoami), PPID=$PPID, TTY=${TTY:-none})"

if [[ ! -x "$ROOT/scripts/horizonte-sync-br-continue.sh" ]]; then
  log_runner "ERRO: continue.sh não executável."
  exit 1
fi

export HORIZONTE_SAEB_MEMORY_LIMIT="${HORIZONTE_SAEB_MEMORY_LIMIT:-2048M}"
export HORIZONTE_CADUNICO_FILL_GAPS="${HORIZONTE_CADUNICO_FILL_GAPS:-true}"
export HORIZONTE_SYNC_BR_MAX_ROUNDS="${HORIZONTE_SYNC_BR_MAX_ROUNDS:-200}"

RESTART=0

while true; do
  set +e
  "$ROOT/scripts/horizonte-sync-br-continue.sh" >>"$NOHUP_LOG" 2>&1
  EXIT=$?
  set -e

  if (( EXIT == 0 )); then
    log_runner "Sync concluído com sucesso — runner a terminar."
    exit 0
  fi

  if (( EXIT == 130 || EXIT == 143 )); then
    log_runner "Sync interrompido (código ${EXIT}) — runner a terminar."
    exit "$EXIT"
  fi

  RESTART=$((RESTART + 1))
  if (( RESTART >= RESTART_MAX )); then
    log_runner "ERRO: ${RESTART} falhas consecutivas — parar (ver logs)."
    exit "$EXIT"
  fi

  log_runner "Sync terminou com código ${EXIT} — reinício ${RESTART}/${RESTART_MAX} em ${RESTART_SLEEP}s…"
  sleep "$RESTART_SLEEP"
done
