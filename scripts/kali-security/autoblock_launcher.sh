#!/bin/bash
# v4 launcher — preserves reconcile_events.json across restarts
set -e
RECON="$HOME/.config/openclaw-autoblock/reconcile_events.json"
if [ ! -f "$RECON" ]; then
  echo "{\"total\": 0, \"session\": 0, \"last\": null, \"session_started\": null}" > "$RECON"
fi
printf "\n----- AUTOBLOCK v4 START %s -----\n" "$(date -u +%FT%TZ)" >> "$HOME/.config/openclaw-autoblock/autoblock.log"
cd "$HOME"
PYTHONUNBUFFERED=1 setsid nohup python3 "$HOME/.openclaw/workspace/scripts/autoblock.py" >> "$HOME/.config/openclaw-autoblock/autoblock.log" 2>&1 < /dev/null &
NEWPID=$!
echo "$NEWPID" > "$HOME/.config/openclaw-autoblock/autoblock.pid"
disown || true
sleep 1
echo "LAUNCHED PID=$NEWPID"
ps -p "$NEWPID" -o pid,cmd --no-headers || echo "PROCESS_DIED"
