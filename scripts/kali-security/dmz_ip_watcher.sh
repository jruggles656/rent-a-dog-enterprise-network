#!/bin/bash
# dmz_ip_watcher.sh — ANY new IP seen in OPNsense DMZ ARP that isn't us = auto-block.
# Replaces MAC-based watcher (Team 8 changed MAC). IP-based catches MAC spoofs too.
# Run as cron every 60s.

set -uo pipefail

LOG="/home/kali/.config/openclaw-autoblock/dmz_ip_watcher.log"
BLOCKED_JSON="/home/kali/.config/openclaw-autoblock/blocked.json"
BLOCKED_TXT="/home/kali/.openclaw/blocked_ips/blocked_ips.txt"
TELEGRAM="/home/kali/.openclaw/workspace/scripts/telegram-alert.sh"
AUTOBLOCK_PY="/home/kali/.openclaw/workspace/scripts/autoblock.py"
STDOUT_LOG="/home/kali/.config/openclaw-autoblock/stdout.log"

OPN_HOST="root@172.31.0.2"
SSH_OPTS="-i /home/kali/.ssh/id_rsa -o PubkeyAcceptedAlgorithms=+ssh-rsa -o HostKeyAlgorithms=+ssh-rsa -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o LogLevel=QUIET -o BatchMode=yes"

# Our legit DMZ hosts — everything else is suspect
LEGIT_DMZ_IPS=("10.0.1.1" "10.0.1.100" "10.0.1.200")

TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

log_line() {
    echo "$TS | $1" >> "$LOG"
}

# Dump current DMZ ARP
ARP=$(ssh $SSH_OPTS $OPN_HOST 'arp -an | grep vtnet1' 2>/dev/null)
if [ -z "$ARP" ]; then
    log_line "WARN | arp fetch failed"
    exit 1
fi

# Extract all DMZ IPs currently in ARP
CURRENT_IPS=()
while IFS= read -r line; do
    ip=$(echo "$line" | awk '{print $2}' | tr -d '()')
    [ -z "$ip" ] && continue
    CURRENT_IPS+=("$ip")
done <<< "$ARP"

# Find intruders: in ARP but not in our legit list
INTRUDERS=()
for ip in "${CURRENT_IPS[@]}"; do
    is_legit=0
    for legit in "${LEGIT_DMZ_IPS[@]}"; do
        if [ "$ip" = "$legit" ]; then is_legit=1; break; fi
    done
    [ "$is_legit" = "1" ] && continue

    # Already blocked?
    if grep -qE "\"$ip\"" "$BLOCKED_JSON" 2>/dev/null; then
        continue
    fi

    INTRUDERS+=("$ip")
done

if [ "${#INTRUDERS[@]}" -eq 0 ]; then
    log_line "ok | no unknown DMZ hosts"
    exit 0
fi

log_line "DETECT | unknown DMZ host(s): ${INTRUDERS[*]}"

# Grab MAC addresses for the alert
MAC_INFO=""
for ip in "${INTRUDERS[@]}"; do
    mac=$(echo "$ARP" | grep -E "\\($ip\\)" | awk '{print $4}' | head -1)
    MAC_INFO="${MAC_INFO}${ip}=${mac} "
done

# Update blocked.json + blocked_ips.txt
python3 - <<PYEOF
import json
p = "$BLOCKED_JSON"
try:
    d = json.load(open(p))
except Exception:
    d = {}
ts = "$TS"
for ip in "${INTRUDERS[@]}".split():
    d[ip] = ts
open(p, "w").write(json.dumps(d, indent=2))
PYEOF

for ip in "${INTRUDERS[@]}"; do
    echo "$ip" >> "$BLOCKED_TXT"
done
sort -uV "$BLOCKED_TXT" -o "$BLOCKED_TXT"

# Immediate pf add (belt + suspenders; autoblock will reconcile on restart)
for ip in "${INTRUDERS[@]}"; do
    ssh $SSH_OPTS $OPN_HOST "pfctl -t autoblock_ip -T add $ip" >> "$LOG" 2>&1
done

# Restart autoblock to reload state from blocked.json
OLD_PID=$(pgrep -f "python3 $AUTOBLOCK_PY$" | head -1)
if [ -n "$OLD_PID" ]; then
    kill "$OLD_PID" 2>/dev/null
    sleep 2
fi
nohup python3 "$AUTOBLOCK_PY" >> "$STDOUT_LOG" 2>&1 &
disown

log_line "BLOCKED | ${INTRUDERS[*]} (killed old pid=$OLD_PID restarted autoblock)"

MSG="🎯 Unknown DMZ host detected + auto-blocked
Intruder IP(s): $(IFS=,; echo "${INTRUDERS[*]}")
MAC: $MAC_INFO
Action: added to pf autoblock_ip + blocked.json + autoblock restarted
This watcher replaces MAC-based tracking — catches any renumber regardless of MAC."
"$TELEGRAM" "$MSG" >> "$LOG" 2>&1 || log_line "WARN | telegram send failed"

exit 0
