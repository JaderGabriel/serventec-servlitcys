#!/usr/bin/env bash
# Inicia o loop Horizonte sync BR numa sessão GNU screen (sobrevive a fecho SSH).
#
# Uso (recomendado em produção):
#   ./scripts/horizonte-sync-br-screen.sh start
#   ./scripts/horizonte-sync-br-screen.sh attach    # rever output em tempo real
#   ./scripts/horizonte-sync-br-screen.sh status
#   ./scripts/horizonte-sync-br-screen.sh stop
#
# Desanexar da sessão: Ctrl+A, depois D  (NÃO feche o terminal SSH estando attached)
#
# Se ao fechar SSH o sync morrer: loginctl enable-linger $(whoami)
# Não use sudo — corra como o utilizador de deploy (ex.: www-data ou o seu user).
set -euo pipefail

SESSION="${HORIZONTE_SYNC_BR_SCREEN:-horizonte-sync-br}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NOHUP_LOG="$ROOT/storage/logs/horizonte-sync-br-nohup.log"
SCREEN_LOG="$ROOT/storage/logs/horizonte-sync-br-screen.log"
SCREENDIR="${SCREENDIR:-$ROOT/storage/screen}"
RUNNER="$ROOT/scripts/horizonte-sync-br-screen-run.sh"
CONTINUE="$ROOT/scripts/horizonte-sync-br-continue.sh"

export SCREENDIR

usage() {
  cat <<EOF
Uso: $(basename "$0") {start|attach|status|stop}

  start   — nova sessão screen detached (continua após fechar SSH)
  attach  — ligar à sessão em curso (Ctrl+A D para desanexar)
  status  — estado da sessão screen + PID do sync (se existir)
  stop    — encerrar sessão screen (interrompe o loop)

Logs:
  $NOHUP_LOG
  $SCREEN_LOG
  storage/logs/horizonte-sync-br-YYYYMMDD.log

Sobrevivência a SSH:
  • Use start e saia do SSH sem attach — ou Ctrl+A, D após attach.
  • Se a sessão morrer ao logout: loginctl enable-linger \$(whoami)
  • Não use sudo — corra como o mesmo user em start e status.

Alternativa manual:
  SCREENDIR=$SCREENDIR setsid screen -S ${SESSION} -dm -L \\
    bash -lc 'cd ${ROOT} && ./scripts/horizonte-sync-br-screen-run.sh'
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

warn_user_context() {
  if [[ "$(id -u)" -eq 0 ]]; then
    echo "AVISO: a correr como root — screen ficará em /run/screen/S-root/." >&2
    echo "        Ao verificar status noutro user, parecerá «não activo»." >&2
    echo "        Prefira: sudo -u SEU_USER $0 start" >&2
    echo "" >&2
  fi
}

check_linger_hint() {
  if command -v loginctl >/dev/null 2>&1; then
    local user linger
    user="$(whoami)"
    linger="$(loginctl show-user "$user" -p Linger --value 2>/dev/null || echo "")"
    if [[ "$linger" == "no" ]]; then
      echo "Dica: Linger=no — ao logout SSH o systemd pode matar processos do user." >&2
      echo "      Para sync longo: loginctl enable-linger $user  (requer sudo, uma vez)" >&2
      echo "" >&2
    fi
  fi
}

last_log_lines() {
  local n="${1:-5}"
  if [[ -f "$NOHUP_LOG" ]]; then
    echo "=== últimas ${n} linhas (nohup) ==="
    tail -n "$n" "$NOHUP_LOG" 2>/dev/null || true
  fi
}

cmd="${1:-start}"

case "$cmd" in
  start)
    require_screen
    warn_user_context
    check_linger_hint
    if session_running; then
      echo "Sessão screen '${SESSION}' já activa."
      echo "  Ver:  $0 attach"
      echo "  Parar: $0 stop"
      exit 1
    fi
    if [[ ! -x "$RUNNER" ]]; then
      echo "Script não executável: $RUNNER" >&2
      exit 1
    fi
    if [[ ! -x "$CONTINUE" ]]; then
      echo "Script não executável: $CONTINUE" >&2
      exit 1
    fi
    prepare_screen_dir
    cd "$ROOT"

    # setsid: desliga da sessão SSH / process group (sobrevive a fecho de terminal)
    # -L: log interno do screen para diagnóstico
    if command -v setsid >/dev/null 2>&1; then
      setsid screen -dmS "$SESSION" -L -Logfile "$SCREEN_LOG" "$RUNNER" </dev/null
    else
      screen -dmS "$SESSION" -L -Logfile "$SCREEN_LOG" "$RUNNER" </dev/null
    fi

    sleep 2
    if session_running; then
      echo "Horizonte sync BR iniciado em screen '${SESSION}'."
      echo "  User:        $(whoami)"
      echo "  SCREENDIR:   $SCREENDIR"
      echo "  Sobrevive a fecho SSH se usar start sem attach (ou Ctrl+A, D após attach)."
      echo "  Ver sessão:  $0 attach"
      echo "  Ver log:     tail -f storage/logs/horizonte-sync-br-nohup.log"
      echo "  Estado:      $0 status"
    else
      echo "ERRO: screen não arrancou ou morreu de imediato." >&2
      echo "  screen -list" >&2
      last_log_lines 15 >&2
      if [[ -f "$SCREEN_LOG" ]]; then
        echo "  tail -20 $SCREEN_LOG" >&2
        tail -20 "$SCREEN_LOG" >&2 || true
      fi
      exit 1
    fi
    ;;
  attach)
    require_screen
    prepare_screen_dir
    if ! session_running; then
      echo "Nenhuma sessão '${SESSION}' activa para $(whoami)." >&2
      echo "SCREENDIR=$SCREENDIR" >&2
      screen -list 2>&1 || true
      echo "Inicie com: $0 start" >&2
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
    if command -v loginctl >/dev/null 2>&1; then
      echo "Linger:    $(loginctl show-user "$(whoami)" -p Linger --value 2>/dev/null || echo '?')"
    fi
    echo ""
    echo "=== screen ==="
    if session_running; then
      screen -list 2>/dev/null | grep -E "${SESSION}" || true
    else
      echo "Sessão '${SESSION}' não activa para $(whoami)."
      screen -list 2>&1 || true
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
    if ! session_running && [[ -f "$pid_file" ]]; then
      pid="$(cat "$pid_file" 2>/dev/null || true)"
      if [[ -n "$pid" ]] && ! kill -0 "$pid" 2>/dev/null; then
        echo ""
        echo "Provável causa: sync terminou ou falhou — screen encerra quando o runner para."
        echo "  Reinicie: $0 start"
        echo "  Se morrer ao fechar SSH: loginctl enable-linger $(whoami)"
      fi
    fi
    ;;
  stop)
    require_screen
    prepare_screen_dir
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
        rm -f "$pid_file"
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
