#!/usr/bin/env bash
# telegram-alert.sh — Post a message to the IR Watcher topic via OpenClaw CLI.
# Usage: telegram-alert.sh "message body"
#
# Reads target IDs from ~/.openclaw/workspace/ir-watcher.config.json
# Called by suricata-digest.py when critical patterns are matched.

set -euo pipefail

MSG="${1:-}"
if [[ -z "$MSG" ]]; then
  echo "usage: $0 <message-text>" >&2
  exit 2
fi

CFG="$HOME/.openclaw/workspace/ir-watcher.config.json"
OC="$HOME/.npm-global/bin/openclaw"

if [[ ! -f "$CFG" ]]; then
  echo "ERR: missing $CFG" >&2; exit 3
fi
if [[ ! -x "$OC" ]]; then
  echo "ERR: openclaw CLI not found at $OC" >&2; exit 4
fi

GROUP_ID=$(python3 -c "import json;print(json.load(open('$CFG'))['group_id'])")
THREAD_ID=$(python3 -c "import json;print(json.load(open('$CFG'))['thread_id'])")

"$OC" message send \
  --channel telegram \
  --account default \
  --target "$GROUP_ID" \
  --thread-id "$THREAD_ID" \
  --message "$MSG"
