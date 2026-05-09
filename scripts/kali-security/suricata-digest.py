#!/usr/bin/env python3
"""suricata-digest.py — Tier A: Pull Suricata alerts from OPNsense, digest, trigger critical Telegram alerts.

Runs via cron on Kali every 15 min. No LLM involvement — pure data pipeline.
Self/admin source IPs (listed in scripts/self-sources.txt) are de-prioritized:
  * kept in the digest for evidence
  * listed in a separate section
  * never trigger Telegram pages
  * excluded from critical pattern matching

Output:
- Digest at ~/.openclaw/workspace/logs/suricata-digest-YYYYMMDD-HHMM.md
- State at ~/.openclaw/workspace/logs/suricata-state.json
- Telegram page via telegram-alert.sh when critical patterns match an external source
"""
import os, sys, json, re, subprocess, datetime, collections, pathlib

HOME = pathlib.Path.home()
CFG_DIR = HOME / ".openclaw" / "workspace"
LOG_DIR = CFG_DIR / "logs"
STATE_FILE = LOG_DIR / "suricata-state.json"
PATTERN_FILE = CFG_DIR / "scripts" / "critical-patterns.txt"
SELF_FILE = CFG_DIR / "scripts" / "self-sources.txt"
TELEGRAM_ALERT = CFG_DIR / "scripts" / "telegram-alert.sh"
OPN_PW_FILE = HOME / ".openclaw" / "credentials" / "opnsense-root-pw"

LOG_DIR.mkdir(parents=True, exist_ok=True)

now = datetime.datetime.now().astimezone()
ts = now.strftime("%Y%m%d-%H%M")
ts_iso = now.isoformat(timespec="seconds")
since = (now - datetime.timedelta(minutes=16)).isoformat(timespec="seconds")
digest_path = LOG_DIR / f"suricata-digest-{ts}.md"

SSH_OPTS = [
    "-o", "StrictHostKeyChecking=no",
    "-o", "ConnectTimeout=8",
    "-o", "PubkeyAcceptedAlgorithms=+ssh-rsa",
    "-o", "HostKeyAlgorithms=+ssh-rsa",
]
REMOTE_CMD = "tail -n 20000 /var/log/suricata/eve.json"
OPN_TARGET = "root@172.31.0.2"


def fetch_alerts():
    try:
        r = subprocess.run(
            ["ssh", *SSH_OPTS, "-o", "BatchMode=yes", OPN_TARGET, REMOTE_CMD],
            capture_output=True, timeout=25, text=True
        )
        if r.returncode == 0:
            return r.stdout
    except Exception:
        pass
    if OPN_PW_FILE.exists():
        env = os.environ.copy()
        env["SSHPASS"] = OPN_PW_FILE.read_text().strip()
        r = subprocess.run(
            ["sshpass", "-e", "ssh", *SSH_OPTS, OPN_TARGET, REMOTE_CMD],
            capture_output=True, timeout=25, text=True, env=env
        )
        if r.returncode == 0:
            return r.stdout
    return ""


def load_self_sources():
    """Parse self-sources.txt → {ip: label}."""
    out = {}
    if not SELF_FILE.exists():
        return out
    for line in SELF_FILE.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split(None, 1)
        ip = parts[0]
        label = parts[1] if len(parts) > 1 else ""
        out[ip] = label
    return out


SELF = load_self_sources()
alerts_raw = fetch_alerts()

alerts = []
for line in alerts_raw.splitlines():
    try:
        e = json.loads(line)
    except Exception:
        continue
    if e.get("event_type") != "alert":
        continue
    if e.get("timestamp", "") < since:
        continue
    alerts.append(e)

# Dedup by (signature, src_ip, dst_ip)
dedup = collections.defaultdict(lambda: {"count": 0})
for e in alerts:
    a = e.get("alert", {})
    src = e.get("src_ip", "?")
    k = (a.get("signature", "?"), src, e.get("dest_ip", "?"))
    d = dedup[k]
    d["count"] += 1
    d.update({
        "sig": a.get("signature", "?"),
        "sid": a.get("signature_id", 0),
        "sev": a.get("severity", 3),
        "cat": a.get("category", ""),
        "src": src,
        "dst": e.get("dest_ip", "?"),
        "dport": e.get("dest_port", 0),
        "proto": e.get("proto", ""),
        "self": src in SELF,
        "self_label": SELF.get(src, ""),
    })
    if "first_ts" not in d:
        d["first_ts"] = e.get("timestamp", "")[:19]

# Separate external vs self
external = [d for d in dedup.values() if not d["self"]]
self_traf = [d for d in dedup.values() if d["self"]]

# Critical detection — only on EXTERNAL sources
patterns = []
if PATTERN_FILE.exists():
    patterns = [
        l.strip() for l in PATTERN_FILE.read_text().splitlines()
        if l.strip() and not l.startswith("#")
    ]

criticals = []
for d in external:
    text = f"{d['sig']} {d['cat']}"
    if d["sev"] == 1 or any(re.search(p, text, re.I) for p in patterns):
        criticals.append(d)

# Write digest
sev_icon = {1: "🔴", 2: "🟡", 3: "🟢"}
total_ext_alerts = sum(d["count"] for d in external)
total_self_alerts = sum(d["count"] for d in self_traf)

lines = [
    f"# Suricata Digest — {ts_iso}",
    "",
    f"- Window: last 15 min (since `{since}`)",
    f"- External alerts: **{total_ext_alerts}** ({len(external)} unique sigs)  ·  "
    f"Self/admin: **{total_self_alerts}** ({len(self_traf)} unique)  ·  "
    f"Critical: **{len(criticals)}**",
    "",
    "## External alerts (by severity)",
]
if external:
    for d in sorted(external, key=lambda x: x["sev"]):
        icon = sev_icon.get(d["sev"], "⚪")
        lines.append(f"- {icon} **sev {d['sev']}** · sid `{d['sid']}` · {d['cat']}")
        lines.append(f"  `{d['sig'][:140]}`")
        lines.append(f"  {d['src']} → {d['dst']}:{d['dport']}/{d['proto']} · ×{d['count']} since {d.get('first_ts','?')}")
else:
    lines.append("_(no external alerts in window)_")

if self_traf:
    lines.append("")
    lines.append("## Self / admin traffic (not attackers — de-prioritized)")
    for d in sorted(self_traf, key=lambda x: x["sev"]):
        lines.append(
            f"- 🛠️ sev {d['sev']} · {d['cat']} · `{d['sig'][:100]}`  "
            f"· {d['src']} ({d['self_label']}) → {d['dst']}:{d['dport']}/{d['proto']} · ×{d['count']}"
        )

if criticals:
    lines.append("")
    lines.append("## Critical hits (external)")
    for d in criticals:
        lines.append(f"- 🚨 sev{d['sev']} {d['cat']}: `{d['sig'][:100]}` — {d['src']} → {d['dst']}:{d['dport']} ×{d['count']}")

digest_path.write_text("\n".join(lines) + "\n")

# State
prev = {}
if STATE_FILE.exists():
    try:
        prev = json.loads(STATE_FILE.read_text())
    except Exception:
        pass

new_state = {
    "last_run_at": ts_iso,
    "latest_digest": str(digest_path),
    "last_batch_alerts": total_ext_alerts + total_self_alerts,
    "last_batch_external": total_ext_alerts,
    "last_batch_self": total_self_alerts,
    "last_batch_criticals": len(criticals),
    "total_alerts": prev.get("total_alerts", 0) + total_ext_alerts + total_self_alerts,
    "total_external": prev.get("total_external", 0) + total_ext_alerts,
    "total_self": prev.get("total_self", 0) + total_self_alerts,
    "total_criticals": prev.get("total_criticals", 0) + len(criticals),
    # unread_alerts = external only; the heartbeat LLM shouldn't narrate self traffic
    "unread_alerts": prev.get("unread_alerts", 0) + total_ext_alerts,
}
tmp = STATE_FILE.with_suffix(".json.tmp")
tmp.write_text(json.dumps(new_state, indent=2))
tmp.replace(STATE_FILE)

# Telegram page for CRITICAL external only
if criticals and TELEGRAM_ALERT.exists():
    msg_lines = [
        "🚨 Suricata CRITICAL — last 15 min",
        f"Criticals: {len(criticals)} sigs · external alerts: {total_ext_alerts}",
        "",
    ]
    for d in criticals[:5]:
        msg_lines.append(f"• sev{d['sev']} [{d['cat']}] {d['sig'][:80]}")
        msg_lines.append(f"  {d['src']} → {d['dst']}:{d['dport']}/{d['proto']} ×{d['count']}")
    if len(criticals) > 5:
        msg_lines.append(f"_(+{len(criticals) - 5} more not shown)_")
    msg_lines.append(f"\nDigest: {digest_path.name}")
    msg = "\n".join(msg_lines)
    try:
        subprocess.run([str(TELEGRAM_ALERT), msg], timeout=30, check=False)
    except Exception as e:
        print(f"[{ts_iso}] telegram-alert failed: {e}", file=sys.stderr)

print(f"[{ts_iso}] digest={digest_path.name} ext={total_ext_alerts} self={total_self_alerts} crits={len(criticals)}")
