#!/usr/bin/env bash
# Inicia o loop Horizonte sync BR numa sessão GNU screen (sobrevive a fecho SSH).
#
# Uso (recomendado em produção):
#   ./scripts/horizonte-sync-br-screen.sh start
#   ./scripts/horizonte-sync-br-screen.sh attach    # rever output em tempo real
#   ./scripts/horizonte-sync-br-screen.sh status
#   ./scripts/horizonte-sync-br-screen.sh stop
#
# Desanexar da sessão: Ctrl+A, depois D
set -euo pipefail

SESSION="${HORIZONTE_SYNC_BR_SCREEN:-horizonte-sync-br}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NOHUP_LOG="$ROOT/storage/logs/horizonte-sync-br-nohup.log"
CONTINUE="$ROOT/scripts/horizonte-sync-br-continue.sh"

usage() {
  cat <<EOF
Uso: $(basename "$0") {start|attach|status|stop}

  start   — nova sessão screen detached (continua após fechar SSH)
  attach  — ligar à sessão em curso (Ctrl+A D para desanexar)
  status  — estado da sessão screen + PID do sync (se existir)
  stop    — encerrar sessão screen (interrompe o loop)

Logs:
  $NOHUP_LOG
  storage/logs/horizonte-sync-br-YYYYMMDD.log

Alternativa manual:
  screen -S ${SESSION} -dm bash -lc 'cd ${ROOT} && ./scripts/horizonte-sync-br-continue.sh 2>&1 | tee -a storage/logs/horizonte-sync-br-nohup.log'
EOF
}

require_screen() {
  if ! command -v screen >/dev/null 2>&1; then
    echo "GNU screen não encontrado. Instale: sudo apt install screen" >&2
    exit 1
  fi
}

session_running() {
  screen -list 2>/dev/null | grep -qE "[[:space:]][0-9]+\.${SESSION}[[:space:]]"
}

cmd="${1:-start}"

case "$cmd" in
  start)
    require_screen
    if session_running; then
      echo "Sessão screen '${SESSION}' já activa."
      echo "  Ver:  $0 attach"
      echo "  Parar: $0 stop"
      exit 1
    fi
    if [[ ! -x "$CONTINUE" ]]; then
      echo "Script não executável: $CONTINUE" >&2
      exit 1
    fi
    mkdir -p "$ROOT/storage/logs"
    cd "$ROOT"
    screen -dmS "$SESSION" env \
      HORIZONTE_SAEB_MEMORY_LIMIT="${HORIZONTE_SAEB_MEMORY_LIMIT:-2048M}" \
      HORIZONTE_CADUNICO_FILL_GAPS="${HORIZONTE_CADUNICO_FILL_GAPS:-true}" \
      HORIZONTE_SYNC_BR_MAX_ROUNDS="${HORIZONTE_SYNC_BR_MAX_ROUNDS:-200}" \
      bash -c "./scripts/horizonte-sync-br-continue.sh 2>&1 | tee -a storage/logs/horizonte-sync-br-nohup.log"
    sleep 1
    if session_running; then
      echo "Horizonte sync BR iniciado em screen '${SESSION}'."
      echo "  Sobrevive a fecho SSH — desanexar com Ctrl+A, D após attach."
      echo "  Ver sessão:  $0 attach"
      echo "  Ver log:     tail -f storage/logs/horizonte-sync-br-nohup.log"
      echo "  Estado:      $0 status"
    else
      echo "ERRO: screen não arrancou. Verifique: screen -list" >&2
      exit 1
    fi
    ;;
  attach)
    require_screen
    if ! session_running; then
      echo "Nenhuma sessão '${SESSION}' activa. Inicie com: $0 start" >&2
      exit 1
    fi
    exec screen -r "$SESSION"
    ;;
  status)
    require_screen
    echo "=== screen ==="
    if session_running; then
      screen -list 2>/dev/null | grep -E "${SESSION}" || true
    else
      echo "Sessão '${SESSION}' não activa."
    fi
    echo ""
    echo "=== sync BR (pid/lock) ==="
    pid_file="$ROOT/storage/logs/horizonte-sync-br.pid"
    lock_file="$ROOT/storage/logs/horizonte-sync-br.lock"
    if [[ -f "$pid_file" ]]; then
      echo "PID file: $(cat "$pid_file")"
    else
      echo "PID file: (ausente — loop pode não ter iniciado ou já terminou)"
    fi
    if [[ -f "$lock_file" ]]; then
      if command -v flock >/dev/null 2>&1; then
        if flock -n "$lock_file" true 2>/dev/null; then
          echo "Lock: livre (ficheiro órfão?)"
        else
          echo "Lock: em uso (sync em curso)"
        fi
      else
        echo "Lock: $lock_file"
      fi
    fi
    if pgrep -f "horizonte-sync-br-continue.sh" >/dev/null 2>&1; then
      echo "Processo: horizonte-sync-br-continue.sh activo"
    fi
    if pgrep -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1; then
      echo "Processo: horizonte:fortnightly-feed activo"
    fi
    ;;
  stop)
    require_screen
    if session_running; then
      screen -S "$SESSION" -X quit
      sleep 1
      if session_running; then
        echo "AVISO: sessão ainda activa — tente: screen -S ${SESSION} -X quit" >&2
        exit 1
      fi
      echo "Sessão '${SESSION}' terminada."
    else
      echo "Nenhuma sessão '${SESSION}' activa."
      pid_file="$ROOT/storage/logs/horizonte-sync-br.pid"
      if [[ -f "$pid_file" ]]; then
        pid=$(cat "$pid_file" 2>/dev/null || true)
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
          echo "A terminar processo órfão PID ${pid}…"
          kill "$pid" 2>/dev/null || true
        fi
      fi
    fi
    ;;
  -h|--help|help)
    usage
    ;;
  *)
    usage >&2
    exit 1
    ;;
esac
