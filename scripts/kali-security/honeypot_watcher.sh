#!/bin/bash
# honeypot_watcher.sh v2 — watches BOTH Apache access.log AND dedicated honeypot_captures.log
# Telegram alerts on decoy path hits + credential submissions.

set -uo pipefail

LOG="/home/kali/.config/openclaw-autoblock/honeypot_watcher.log"
STATE="/home/kali/.config/openclaw-autoblock/honeypot_last_line.state"
TELEGRAM="/home/kali/.openclaw/workspace/scripts/telegram-alert.sh"

WEB_HOST="Administrator@10.0.1.100"
SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=QUIET -o ConnectTimeout=10"

# Apache access log honeypot path patterns (from DOGPARK Phase 2 Day 3 + our decoy sitemap)
HONEYPOT_PATTERNS='(GET /\.env|GET /\.git/config|GET /sitemap\.xml|GET /wp-config\.php|GET /admin_internal|GET /backup_2026|GET /db_exports|GET /old_site|GET /staging|GET /config/app_secrets|GET /api/v1/internal|POST /admin_internal)'

# Legit admin sources — don't alert on these
WHITELIST_IPS="172.31.0.100 192.168.1.100 192.168.1.51 192.168.1.5"

TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

log_line() {
    echo "$TS | $1" >> "$LOG"
}

# ──────────────────────────────────────────────────────────────────────
# Fetch access.log tail + dedicated honeypot_captures.log tail
# ──────────────────────────────────────────────────────────────────────
FETCH=$(ssh $SSH_OPTS $WEB_HOST 'powershell -Command "Get-Content C:\xampp\apache\logs\access.log -Tail 500; Write-Host ---CAPTURES---; Get-Content C:\xampp\apache\logs\honeypot_captures.log -Tail 200 -ErrorAction SilentlyContinue"' 2>/dev/null)

if [ -z "$FETCH" ]; then
    log_line "WARN | fetch failed or empty"
    exit 1
fi

ACCESS_TAIL=$(echo "$FETCH" | sed -n '1,/---CAPTURES---/p' | head -n -1)
CAPTURE_TAIL=$(echo "$FETCH" | sed -n '/---CAPTURES---/,$p' | tail -n +2)

# ──────────────────────────────────────────────────────────────────────
# Apache access-log honeypot path hits (GET /decoy)
# ──────────────────────────────────────────────────────────────────────
ACCESS_HITS=$(echo "$ACCESS_TAIL" | grep -E "$HONEYPOT_PATTERNS" 2>/dev/null || true)

# ──────────────────────────────────────────────────────────────────────
# Dedicated capture log entries (credential submits + dashboard hits)
# ──────────────────────────────────────────────────────────────────────
CAPTURE_HITS=$(echo "$CAPTURE_TAIL" | grep -E '\[HONEYPOT-(CRED|DASHBOARD)\]' 2>/dev/null || true)

if [ -z "$ACCESS_HITS" ] && [ -z "$CAPTURE_HITS" ]; then
    log_line "ok | no new honeypot activity"
    exit 0
fi

# ──────────────────────────────────────────────────────────────────────
# Dedupe everything via md5-hash state file
# ──────────────────────────────────────────────────────────────────────
touch "$STATE"

NEW_ACCESS=""
while IFS= read -r line; do
    [ -z "$line" ] && continue
    h=$(echo "$line" | md5sum | awk '{print $1}')
    if ! grep -qF "$h" "$STATE" 2>/dev/null; then
        NEW_ACCESS="${NEW_ACCESS}${line}"$'\n'
        echo "$h" >> "$STATE"
    fi
done <<< "$ACCESS_HITS"

NEW_CAPTURES=""
while IFS= read -r line; do
    [ -z "$line" ] && continue
    h=$(echo "$line" | md5sum | awk '{print $1}')
    if ! grep -qF "$h" "$STATE" 2>/dev/null; then
        NEW_CAPTURES="${NEW_CAPTURES}${line}"$'\n'
        echo "$h" >> "$STATE"
    fi
done <<< "$CAPTURE_HITS"

# Trim state to last 1000 hashes
tail -1000 "$STATE" > "$STATE.tmp" && mv "$STATE.tmp" "$STATE"

if [ -z "$NEW_ACCESS" ] && [ -z "$NEW_CAPTURES" ]; then
    log_line "ok | no new activity after dedup"
    exit 0
fi

# ──────────────────────────────────────────────────────────────────────
# Filter whitelisted sources from access hits (IP is first field)
# ──────────────────────────────────────────────────────────────────────
ALERT_ACCESS=""
while IFS= read -r hit; do
    [ -z "$hit" ] && continue
    src_ip=$(echo "$hit" | awk '{print $1}')
    skip=0
    for w in $WHITELIST_IPS; do
        if [ "$src_ip" = "$w" ]; then skip=1; break; fi
    done
    [ "$skip" = "1" ] && continue
    ALERT_ACCESS="${ALERT_ACCESS}${hit}"$'\n'
done <<< "$NEW_ACCESS"

# Capture hits ALWAYS alert (credential submissions are high-signal no matter the source)
ALERT_CAPTURES="$NEW_CAPTURES"

if [ -z "$ALERT_ACCESS" ] && [ -z "$ALERT_CAPTURES" ]; then
    log_line "ok | new hits from whitelisted sources only"
    exit 0
fi

# ──────────────────────────────────────────────────────────────────────
# Compose + send alert
# ──────────────────────────────────────────────────────────────────────
log_line "HIT | honeypot activity detected"

ALERT_BODY="Honeypot hit on WebServer"$'\n\n'

if [ -n "$ALERT_CAPTURES" ]; then
    ALERT_BODY="${ALERT_BODY}CREDENTIAL SUBMISSIONS / DASHBOARD VIEWS:"$'\n'
    ALERT_BODY="${ALERT_BODY}$(echo "$ALERT_CAPTURES" | head -5)"$'\n\n'
fi

if [ -n "$ALERT_ACCESS" ]; then
    ALERT_BODY="${ALERT_BODY}APACHE ACCESS LOG (decoy paths):"$'\n'
    ALERT_BODY="${ALERT_BODY}$(echo "$ALERT_ACCESS" | head -5)"$'\n'
fi

echo "$ALERT_BODY" | tee -a "$LOG" >/dev/null

"$TELEGRAM" "$ALERT_BODY" >> "$LOG" 2>&1 || log_line "WARN | telegram send failed"

exit 0
