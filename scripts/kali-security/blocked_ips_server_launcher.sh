#!/bin/bash
# Launcher for blocked_ips_server. Idempotent: kills any existing instance first.
set -e
LOG="$HOME/.config/openclaw-autoblock/http_server.log"
PID="$HOME/.config/openclaw-autoblock/http_server.pid"
SCRIPT="$HOME/.openclaw/workspace/scripts/blocked_ips_server.py"

# Kill existing
if [ -f "$PID" ]; then
  OLDPID=$(cat "$PID" 2>/dev/null || true)
  if [ -n "$OLDPID" ] && kill -0 "$OLDPID" 2>/dev/null; then
    kill "$OLDPID" 2>/dev/null || true
    sleep 1
  fi
fi
# Belt-and-suspenders: kill anything else on the port
for p in $(pgrep -f blocked_ips_server.py); do kill "$p" 2>/dev/null || true; done
sleep 1

printf "\n----- HTTP-SERVER START %s -----\n" "$(date -u +%FT%TZ)" >> "$LOG"
PYTHONUNBUFFERED=1 setsid nohup python3 "$SCRIPT" >> "$LOG" 2>&1 < /dev/null &
NEWPID=$!
echo "$NEWPID" > "$PID"
disown || true
sleep 1
echo "LAUNCHED PID=$NEWPID"
ps -p "$NEWPID" -o pid,cmd --no-headers || echo "PROCESS_DIED"
