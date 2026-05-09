#!/bin/bash
# mac_watcher.sh — poll OPNsense ARP for known-bad MACs; push new IPs to autoblock state.
# Runs as a cron every 60s. When it finds a new IP for a known-bad MAC, it:
#   (1) adds the IP to blocked.json + blocked_ips.txt
#   (2) restarts autoblock so it picks up the new state and pushes to pf
#   (3) sends a Telegram alert
#
# This catches Team 8 IP renumbers that land OUTSIDE the pre-blocked range.
# Pre-blocked range (as of 2026-04-23): 10.0.1.64/28 (.64-.79).

set -uo pipefail

# ──────────────────────────────────────────────────────────────────────
# Config
# ──────────────────────────────────────────────────────────────────────
BAD_MACS=(
    "bc:24:11:06:59:1c"    # Team 8 DMZ Kali (seen on .67 + .69 already)
)

BLOCKED_JSON="/home/kali/.config/openclaw-autoblock/blocked.json"
BLOCKED_TXT="/home/kali/.openclaw/blocked_ips/blocked_ips.txt"
LOG="/home/kali/.config/openclaw-autoblock/mac_watcher.log"
TELEGRAM="/home/kali/.openclaw/workspace/scripts/telegram-alert.sh"
AUTOBLOCK_PY="/home/kali/.openclaw/workspace/scripts/autoblock.py"
STDOUT_LOG="/home/kali/.config/openclaw-autoblock/stdout.log"

OPN_HOST="root@172.31.0.2"
SSH_OPTS="-i /home/kali/.ssh/id_rsa -o PubkeyAcceptedAlgorithms=+ssh-rsa -o HostKeyAlgorithms=+ssh-rsa -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes"

WHITELIST_IPS=("10.0.1.1" "10.0.1.100" "10.0.1.200" "172.31.0.1" "172.31.0.2" "172.31.0.100" "192.168.1.1" "192.168.1.5" "192.168.1.51" "192.168.1.100" "192.168.2.1" "192.168.2.2")

TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

log_line() {
    echo "$TS | $1" >> "$LOG"
}

# ──────────────────────────────────────────────────────────────────────
# Fetch IPs from OPNsense ARP that match any known-bad MAC
# ──────────────────────────────────────────────────────────────────────
ARP_OUTPUT="$(ssh $SSH_OPTS $OPN_HOST 'arp -an' 2>/dev/null)"
if [ -z "$ARP_OUTPUT" ]; then
    log_line "WARN | arp fetch failed or empty"
    exit 1
fi

BAD_IPS=()
for mac in "${BAD_MACS[@]}"; do
    # arp -an format: "? (10.0.1.69) at bc:24:11:06:59:1c on vtnet1 ..."
    while IFS= read -r line; do
        ip="$(echo "$line" | awk '{print $2}' | tr -d '()')"
        [ -z "$ip" ] && continue
        BAD_IPS+=("$ip")
    done < <(echo "$ARP_OUTPUT" | grep -i "$mac")
done

if [ "${#BAD_IPS[@]}" -eq 0 ]; then
    log_line "ok | no bad-MAC IPs in ARP"
    exit 0
fi

# ──────────────────────────────────────────────────────────────────────
# Determine which ones are NEW (not already in blocked.json) and not whitelisted
# ──────────────────────────────────────────────────────────────────────
NEW_IPS=()
for ip in "${BAD_IPS[@]}"; do
    # whitelist guard
    skip=0
    for w in "${WHITELIST_IPS[@]}"; do
        if [ "$ip" = "$w" ]; then skip=1; break; fi
    done
    [ "$skip" = "1" ] && continue
    # already blocked?
    if grep -qE "\"$ip\"" "$BLOCKED_JSON" 2>/dev/null; then
        continue
    fi
    NEW_IPS+=("$ip")
done

if [ "${#NEW_IPS[@]}" -eq 0 ]; then
    log_line "ok | bad-MAC IPs already blocked (${BAD_IPS[*]})"
    exit 0
fi

# ──────────────────────────────────────────────────────────────────────
# New IP(s) found — update state + restart autoblock
# ──────────────────────────────────────────────────────────────────────
log_line "DETECT | new IP(s) for known MAC: ${NEW_IPS[*]}"

# Update blocked.json (python for reliable JSON)
python3 - <<PYEOF
import json, datetime, sys
p = "$BLOCKED_JSON"
try:
    d = json.load(open(p))
except Exception:
    d = {}
ts = "$TS"
for ip in """${NEW_IPS[*]}""".split():
    d[ip] = ts
open(p, "w").write(json.dumps(d, indent=2))
PYEOF

# Update blocked_ips.txt (dedupe + sort)
for ip in "${NEW_IPS[@]}"; do
    echo "$ip" >> "$BLOCKED_TXT"
done
sort -uV "$BLOCKED_TXT" -o "$BLOCKED_TXT"

# Restart autoblock so it reloads blocked.json and re-pushes to pf
OLD_PID="$(pgrep -f "python3 $AUTOBLOCK_PY$" | head -1)"
if [ -n "$OLD_PID" ]; then
    kill "$OLD_PID" 2>/dev/null
    sleep 2
fi
nohup python3 "$AUTOBLOCK_PY" >> "$STDOUT_LOG" 2>&1 &
disown

log_line "RESTART | killed pid=$OLD_PID new autoblock starting"

# Telegram alert
MSG="🎯 MAC-watcher auto-block — Team 8 pivoted to new IP(s): $(IFS=,; echo "${NEW_IPS[*]}") — all resolving to same MAC ${BAD_MACS[*]}. Added to autoblock state + autoblock restarted."
"$TELEGRAM" "$MSG" >> "$LOG" 2>&1 || log_line "WARN | telegram send failed"

exit 0
