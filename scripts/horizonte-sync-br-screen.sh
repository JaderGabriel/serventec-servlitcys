#!/usr/bin/env bash
# Inicia o loop Horizonte sync BR numa sessão GNU screen (sobrevive a fecho SSH).
#
# Uso (recomendado em produção):
#   ./scripts/horizonte-sync-br-screen.sh start
#   ./scripts/horizonte-sync-br-screen.sh attach    # rever output em tempo real
#   ./scripts/horizonte-sync-br-screen.sh status
#   ./scripts/horizonte-sync-br-screen.sh ensure    # cron: reinicia se wanted mas sem screen
#   ./scripts/horizonte-sync-br-screen.sh stop
#
# Desanexar da sessão: Ctrl+A, depois D  (NÃO feche o terminal SSH estando attached)
#
# OBRIGATÓRIO em produção (uma vez, com sudo):
#   loginctl enable-linger serventec
# Sem linger, o systemd mata o screen ao fechar SSH (mesmo detached).
set -euo pipefail

SESSION="${HORIZONTE_SYNC_BR_SCREEN:-horizonte-sync-br}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NOHUP_LOG="$ROOT/storage/logs/horizonte-sync-br-nohup.log"
SCREEN_LOG="$ROOT/storage/logs/horizonte-sync-br-screen.log"
WANTED_FILE="$ROOT/storage/logs/horizonte-sync-br.wanted"
SCREENDIR="${SCREENDIR:-$ROOT/storage/screen}"
RUNNER="$ROOT/scripts/horizonte-sync-br-screen-run.sh"
CONTINUE="$ROOT/scripts/horizonte-sync-br-continue.sh"

export SCREENDIR

usage() {
  cat <<EOF
Uso: $(basename "$0") {start|attach|status|ensure|stop}

  start   — nova sessão screen detached + flag wanted (continua após fechar SSH)
  attach  — ligar à sessão em curso (Ctrl+A D para desanexar)
  status  — estado da sessão screen + PID do sync (se existir)
  ensure  — se wanted activo mas screen morto, reinicia (útil em cron)
  stop    — remove wanted e encerra sessão screen

Logs:
  $NOHUP_LOG
  $SCREEN_LOG
  storage/logs/horizonte-sync-br-YYYYMMDD.log

Produção (user serventec ou deploy):
  sudo loginctl enable-linger \$(whoami)   # uma vez — evita TERM ao logout SSH

Cron exemplo (reinício automático se cair):
  */5 * * * * cd ${ROOT} && ./scripts/horizonte-sync-br-screen.sh ensure >> storage/logs/horizonte-sync-br-ensure.log 2>&1
EOF
}

require_screen() {
  if ! command -v screen >/dev/null 2>&1; then
    echo "GNU screen não encontrado. Instale: sudo apt install screen" >&2
    exit 1
  fi
}

prepare_screen_dir() {
  mkdir -p "$SCREENDIR" "$ROOT/storage/logs"
  chmod 700 "$SCREENDIR" 2>/dev/null || true
}

session_running() {
  screen -list 2>/dev/null | grep -qE "[0-9]+\.${SESSION}[[:space:]]"
}

sync_process_running() {
  pgrep -u "$(id -u)" -f "horizonte-sync-br-screen-run.sh" >/dev/null 2>&1 \
    || pgrep -u "$(id -u)" -f "horizonte-sync-br-continue.sh" >/dev/null 2>&1 \
    || pgrep -u "$(id -u)" -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1
}

mark_wanted() {
  echo "[$(date -Iseconds)] user=$(whoami) pid=$$" >"$WANTED_FILE"
}

clear_wanted() {
  rm -f "$WANTED_FILE"
}

warn_user_context() {
  if [[ "$(id -u)" -eq 0 ]]; then
    echo "AVISO: a correr como root — screen ficará em /run/screen/S-root/." >&2
    echo "        Ao verificar status noutro user, parecerá «não activo»." >&2
    echo "        Prefira: sudo -u serventec $0 start" >&2
    echo "" >&2
  fi
}

check_linger_hint() {
  if ! command -v loginctl >/dev/null 2>&1; then
    return 0
  fi
  local user linger
  user="$(whoami)"
  linger="$(loginctl show-user "$user" -p Linger --value 2>/dev/null || echo "")"
  if [[ "$linger" == "no" ]]; then
    echo "╔══════════════════════════════════════════════════════════════════╗" >&2
    echo "║  AVISO: Linger=no — ao fechar SSH o systemd envia TERM e mata   ║" >&2
    echo "║  o screen (~20s após logout). Execute UMA VEZ com sudo:         ║" >&2
    echo "║    loginctl enable-linger ${user}                                ║" >&2
    echo "╚══════════════════════════════════════════════════════════════════╝" >&2
    echo "" >&2
  fi
}

last_log_lines() {
  local n="${1:-5}"
  if [[ -f "$NOHUP_LOG" ]]; then
    echo "=== últimas ${n} linhas (nohup) ==="
    tail -n "$n" "$NOHUP_LOG" 2>/dev/null || true
  fi
}

launch_screen() {
  prepare_screen_dir
  cd "$ROOT"
  mark_wanted

  if command -v setsid >/dev/null 2>&1; then
    setsid screen -dmS "$SESSION" -L -Logfile "$SCREEN_LOG" "$RUNNER" </dev/null
  else
    screen -dmS "$SESSION" -L -Logfile "$SCREEN_LOG" "$RUNNER" </dev/null
  fi

  sleep 2
  if ! session_running; then
    clear_wanted
    return 1
  fi
  return 0
}

cmd="${1:-start}"

case "$cmd" in
  start)
    require_screen
    warn_user_context
    check_linger_hint
    if session_running; then
      mark_wanted
      echo "Sessão screen '${SESSION}' já activa (wanted actualizado)."
      echo "  Ver:  $0 attach"
      echo "  Parar: $0 stop"
      exit 0
    fi
    if [[ ! -x "$RUNNER" ]]; then
      echo "Script não executável: $RUNNER" >&2
      exit 1
    fi
    if [[ ! -x "$CONTINUE" ]]; then
      echo "Script não executável: $CONTINUE" >&2
      exit 1
    fi

    if launch_screen; then
      echo "Horizonte sync BR iniciado em screen '${SESSION}'."
      echo "  User:        $(whoami)"
      echo "  SCREENDIR:   $SCREENDIR"
      echo "  Wanted:      $WANTED_FILE"
      echo "  Ver sessão:  $0 attach"
      echo "  Ver log:     tail -f storage/logs/horizonte-sync-br-nohup.log"
      echo "  Estado:      $0 status"
      check_linger_hint
    else
      echo "ERRO: screen não arrancou ou morreu de imediato." >&2
      screen -list 2>&1 || true
      last_log_lines 15 >&2
      if [[ -f "$SCREEN_LOG" ]]; then
        tail -20 "$SCREEN_LOG" >&2 || true
      fi
      exit 1
    fi
    ;;
  ensure)
    require_screen
    if [[ ! -f "$WANTED_FILE" ]]; then
      echo "ensure: wanted ausente — nada a fazer (use start primeiro)."
      exit 0
    fi
    if session_running || sync_process_running; then
      echo "ensure: sync activo (screen ou processo) — ok."
      exit 0
    fi
    echo "ensure: wanted activo mas screen morto — a reiniciar…"
    check_linger_hint
    if launch_screen; then
      echo "ensure: screen reiniciado."
    else
      echo "ensure: falha ao reiniciar screen." >&2
      exit 1
    fi
    ;;
  attach)
    require_screen
    prepare_screen_dir
    if ! session_running; then
      echo "Nenhuma sessão '${SESSION}' activa para $(whoami)." >&2
      if [[ -f "$WANTED_FILE" ]]; then
        echo "Wanted activo — tente: $0 ensure" >&2
      fi
      screen -list 2>&1 || true
      exit 1
    fi
    echo "Desanexar sem parar o sync: Ctrl+A, depois D (não feche o terminal directamente)."
    exec screen -r "$SESSION"
    ;;
  status)
    require_screen
    prepare_screen_dir
    echo "=== contexto ==="
    echo "User:      $(whoami)"
    echo "SCREENDIR: $SCREENDIR"
    if [[ -f "$WANTED_FILE" ]]; then
      echo "Wanted:    activo ($(head -1 "$WANTED_FILE" 2>/dev/null || echo '?'))"
    else
      echo "Wanted:    (ausente — sync não pedido ou concluído)"
    fi
    if command -v loginctl >/dev/null 2>&1; then
      linger="$(loginctl show-user "$(whoami)" -p Linger --value 2>/dev/null || echo '?')"
      echo "Linger:    ${linger}"
      if [[ "$linger" == "no" ]]; then
        echo "  ⚠ Sem linger — logout SSH mata o sync. Corra: sudo loginctl enable-linger $(whoami)"
      fi
    fi
    echo ""
    echo "=== screen ==="
    if session_running; then
      screen -list 2>/dev/null | grep -E "${SESSION}" || true
    else
      echo "Sessão '${SESSION}' não activa para $(whoami)."
      screen -list 2>&1 || true
      if [[ -f "$WANTED_FILE" ]]; then
        echo ""
        echo "Wanted activo mas screen morto — reinicie com: $0 ensure"
      fi
    fi
    echo ""
    echo "=== sync BR (pid/lock) ==="
    pid_file="$ROOT/storage/logs/horizonte-sync-br.pid"
    lock_file="$ROOT/storage/logs/horizonte-sync-br.lock"
    if [[ -f "$pid_file" ]]; then
      pid="$(cat "$pid_file" 2>/dev/null || true)"
      if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
        echo "PID file: ${pid} (activo)"
      else
        echo "PID file: ${pid:-?} (morto — ficheiro órfão)"
      fi
    else
      echo "PID file: (ausente)"
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
    if pgrep -u "$(id -u)" -f "horizonte-sync-br-continue.sh" >/dev/null 2>&1; then
      echo "Processo: horizonte-sync-br-continue.sh activo"
    fi
    if pgrep -u "$(id -u)" -f "horizonte-sync-br-screen-run.sh" >/dev/null 2>&1; then
      echo "Processo: horizonte-sync-br-screen-run.sh activo"
    fi
    if pgrep -u "$(id -u)" -f "artisan horizonte:fortnightly-feed" >/dev/null 2>&1; then
      echo "Processo: horizonte:fortnightly-feed activo"
    fi
    echo ""
    last_log_lines 8
    if ! session_running && [[ -f "$WANTED_FILE" ]]; then
      echo ""
      echo "Diagnóstico: TERM/SIGHUP provável (logout SSH sem linger ou stop manual)."
      echo "  1) sudo loginctl enable-linger $(whoami)"
      echo "  2) $0 ensure   # reinicia agora"
      echo "  3) cron */5 * * * * …/horizonte-sync-br-screen.sh ensure"
    fi
    ;;
  stop)
    require_screen
    prepare_screen_dir
    clear_wanted
    if session_running; then
      screen -S "$SESSION" -X quit
      sleep 1
    fi
    if pgrep -u "$(id -u)" -f "horizonte-sync-br-continue.sh" >/dev/null 2>&1; then
      echo "A terminar continue.sh órfão…"
      pkill -u "$(id -u)" -TERM -f "horizonte-sync-br-continue.sh" 2>/dev/null || true
      sleep 2
    fi
    rm -f "$ROOT/storage/logs/horizonte-sync-br.pid"
    if session_running; then
      echo "AVISO: sessão ainda activa — tente: screen -S ${SESSION} -X quit" >&2
      exit 1
    fi
    echo "Sync BR parado (wanted removido)."
    ;;
  -h|--help|help)
    usage
    ;;
  *)
    usage >&2
    exit 1
    ;;
esac
