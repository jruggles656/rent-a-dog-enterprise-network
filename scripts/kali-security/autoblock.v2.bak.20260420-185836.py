#!/usr/bin/env python3
"""
OpenClaw Auto-Block — Team 6 v2 (filter-log augmented)
Detects scanners via TWO paths:
  A) Suricata eve.json — signature-based (ET SCAN, NMAP, etc.)  [original]
  B) OPNsense filter log — counts blocked-inbound events per source IP  [NEW]
     If a source IP has >= SCAN_THRESHOLD_BLOCKS blocked-inbound events in the recent
     window of log entries, flag as scanner. Catches attackers that Suricata rules miss.

Everything else unchanged: SSH-path pfctl block, self-sources whitelist, lab whitelist,
TTL auto-expiry, Telegram alerts, reboot-recovery, DISABLE kill-switch.
"""

import argparse
import json
import os
import subprocess
import time
import collections
import datetime as _dt
from datetime import datetime, timezone, timedelta
from pathlib import Path

# ── Config ────────────────────────────────────────────────────────────────────
OPN_SSH_USER   = "root"
OPN_SSH_HOST   = "172.31.0.2"
SSH_KEY        = os.path.expanduser("~/.ssh/id_rsa")
EVE_LOG_PATH   = "/var/log/suricata/eve.json"
EVE_TAIL_LINES = 200

PF_TABLE_IP = "autoblock_ip"

CONFIG_DIR    = Path(os.path.expanduser("~/.config/openclaw-autoblock"))
BLOCK_DB      = CONFIG_DIR / "blocked.json"
LOG_FILE      = CONFIG_DIR / "autoblock.log"
DISABLE_FILE  = CONFIG_DIR / "DISABLE"

SELF_SOURCES_FILE = Path("/home/kali/.openclaw/workspace/scripts/self-sources.txt")
TELEGRAM_ALERT    = Path("/home/kali/.openclaw/workspace/scripts/telegram-alert.sh")

POLL_INTERVAL = 30
DEFAULT_TTL_HOURS = 24

# Filter-log scan detection thresholds
FILTER_LOG_TAIL_LINES      = 5000   # how many lines to pull each cycle
SCAN_THRESHOLD_BLOCKS      = 15     # a source IP with >= this many blocks = scanner
SCAN_THRESHOLD_UNIQUE_PORTS = 5     # OR >= this many unique dest ports from same src

# Lab VM inventory — never blockable
LAB_WHITELIST = {
    "172.31.0.100", "172.31.0.2", "172.31.0.1",
    "10.0.1.1", "10.0.1.100", "10.0.1.200",
    "192.168.2.1", "192.168.2.2", "192.168.1.1",
    "192.168.1.5", "192.168.1.51", "192.168.1.100",
    "127.0.0.1",
}

SCAN_SIGNATURES = [
    "ET SCAN", "NMAP", "Nmap", "nmap",
    "PORT SCAN", "SYN SCAN", "NULL SCAN",
    "XMAS", "FIN SCAN", "OS DETECTION",
]

SSH_OPTS = [
    "-i", SSH_KEY,
    "-o", "StrictHostKeyChecking=no",
    "-o", "BatchMode=yes",
    "-o", "ConnectTimeout=8",
    "-o", "PubkeyAcceptedAlgorithms=+ssh-rsa",
    "-o", "HostKeyAlgorithms=+ssh-rsa",
]


# ── CLI ───────────────────────────────────────────────────────────────────────

def parse_args():
    ap = argparse.ArgumentParser(description="Team 6 Auto-Block (SSH + filter-log)")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--ttl-hours", type=float, default=DEFAULT_TTL_HOURS)
    ap.add_argument("--once", action="store_true")
    return ap.parse_args()


# ── Logging ───────────────────────────────────────────────────────────────────

def _ts():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S")


def _log(line):
    print(line, flush=True)
    with LOG_FILE.open("a") as f:
        f.write(line + "\n")


def log_block(ip, rule, dry=False):
    tag = "DRYRUN_WOULD_BLOCK" if dry else "BLOCKED"
    _log(f"{_ts()} | {tag} | IP={ip} | RULE={rule}")


def log_unblock(ip, reason):
    _log(f"{_ts()} | UNBLOCKED | IP={ip} | REASON={reason}")


def log_info(msg):
    print(f"{_ts()} | INFO | {msg}", flush=True)


def log_error(msg):
    print(f"{_ts()} | ERROR | {msg}", flush=True)


# ── State ─────────────────────────────────────────────────────────────────────

def load_whitelist() -> set:
    wl = set(LAB_WHITELIST)
    if SELF_SOURCES_FILE.exists():
        try:
            for line in SELF_SOURCES_FILE.read_text().splitlines():
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                ip = line.split(None, 1)[0]
                wl.add(ip)
        except Exception as exc:
            log_error(f"failed to read {SELF_SOURCES_FILE}: {exc}")
    return wl


def load_blocked_db() -> dict:
    if BLOCK_DB.exists():
        try:
            raw = json.loads(BLOCK_DB.read_text())
            if isinstance(raw, list):
                ts = datetime.now(timezone.utc).isoformat(timespec="seconds")
                return {ip: ts for ip in raw}
            return dict(raw)
        except Exception:
            pass
    return {}


def save_blocked_db(blocked: dict):
    BLOCK_DB.write_text(json.dumps(blocked, indent=2, sort_keys=True))


# ── Telegram ──────────────────────────────────────────────────────────────────

def telegram_notify(msg: str):
    if not TELEGRAM_ALERT.exists():
        return
    try:
        subprocess.run([str(TELEGRAM_ALERT), msg], timeout=15, check=False)
    except Exception as exc:
        log_error(f"telegram-alert failed: {exc}")


# ── SSH helpers ───────────────────────────────────────────────────────────────

def ssh_run(remote_cmd: str, timeout: int = 10) -> tuple:
    try:
        r = subprocess.run(
            ["ssh", *SSH_OPTS, f"{OPN_SSH_USER}@{OPN_SSH_HOST}", remote_cmd],
            capture_output=True, timeout=timeout, text=True
        )
        return r.returncode, r.stdout, r.stderr
    except subprocess.TimeoutExpired:
        return 124, "", "ssh timeout"
    except FileNotFoundError:
        return 127, "", "ssh not found"


# ── Detection A: Suricata signatures ──────────────────────────────────────────

def fetch_eve_lines() -> list:
    rc, out, err = ssh_run(f"tail -n {EVE_TAIL_LINES} {EVE_LOG_PATH}", timeout=20)
    if rc != 0:
        log_error(f"SSH fetch eve.json failed (rc={rc}): {err.strip()[:200]}")
        return []
    return out.splitlines()


def parse_scan_alerts(lines) -> list:
    hits = []
    for line in lines:
        line = line.strip()
        if not line:
            continue
        try:
            obj = json.loads(line)
        except json.JSONDecodeError:
            continue
        if obj.get("event_type") != "alert":
            continue
        sig = obj.get("alert", {}).get("signature", "")
        if any(token in sig for token in SCAN_SIGNATURES):
            hits.append({"src_ip": obj.get("src_ip", ""), "signature": sig, "source": "suricata"})
    return hits


# ── Detection B: OPNsense filter log ──────────────────────────────────────────

def fetch_filter_log() -> list:
    """Pull last N lines of today's filter log from OPNsense.
    OPNsense 25.x writes plain text here: /var/log/filter/filter_YYYYMMDD.log
    The `latest.log` symlink also works.
    """
    rc, out, err = ssh_run(
        f"tail -n {FILTER_LOG_TAIL_LINES} /var/log/filter/latest.log",
        timeout=15,
    )
    if rc != 0:
        log_error(f"filter-log fetch failed (rc={rc}): {err.strip()[:200]}")
        return []
    return out.splitlines()


def parse_filter_log_scanners(lines) -> dict:
    """Return {src_ip: {"count": N, "unique_ports": set(), "sample_ports": [...]}}.
    Only counts 'match,block,in' events.
    """
    stats = collections.defaultdict(lambda: {"count": 0, "unique_ports": set()})
    for raw in lines:
        # Find the CSV portion after the `]` of the meta block
        idx = raw.find(']')
        if idx < 0:
            continue
        csv_part = raw[idx + 1:].strip()
        fields = csv_part.split(',')
        if len(fields) < 20:
            continue
        try:
            action    = fields[6]
            direction = fields[7]
            src_ip    = fields[18]
            dst_port  = fields[21] if len(fields) > 21 else ""
        except IndexError:
            continue
        if action != "block" or direction != "in":
            continue
        if not src_ip or "." not in src_ip:
            continue
        stats[src_ip]["count"] += 1
        if dst_port.isdigit():
            stats[src_ip]["unique_ports"].add(dst_port)
    # Convert sets to lists for JSON-friendliness
    out = {}
    for ip, s in stats.items():
        out[ip] = {
            "count": s["count"],
            "unique_ports": sorted(s["unique_ports"]),
        }
    return out


def identify_scanners_from_filter(filter_stats: dict) -> list:
    """Return list of (ip, reason) that meet scanner thresholds."""
    scanners = []
    for ip, s in filter_stats.items():
        count = s["count"]
        unique = len(s["unique_ports"])
        if count >= SCAN_THRESHOLD_BLOCKS:
            scanners.append((ip, f"filter-log scan: {count} blocks, {unique} unique dst ports"))
        elif unique >= SCAN_THRESHOLD_UNIQUE_PORTS and count >= 5:
            scanners.append((ip, f"filter-log port-sweep: {unique} unique dst ports, {count} events"))
    return scanners


# ── pf table ops via SSH ──────────────────────────────────────────────────────

def pfctl_add(ip: str, dry: bool) -> bool:
    if dry:
        return True
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T add {ip}", timeout=10)
    if rc == 0:
        return True
    log_error(f"pfctl add {ip} failed (rc={rc}): {err.strip()[:200]}")
    return False


def pfctl_delete(ip: str, dry: bool) -> bool:
    if dry:
        return True
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T delete {ip}", timeout=10)
    if rc == 0:
        return True
    log_error(f"pfctl delete {ip} failed (rc={rc}): {err.strip()[:200]}")
    return False


def pfctl_show() -> set:
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T show", timeout=10)
    if rc != 0:
        log_error(f"pfctl show failed: {err.strip()[:200]}")
        return set()
    return {ln.strip() for ln in out.splitlines() if ln.strip()}


# ── Reboot recovery ───────────────────────────────────────────────────────────

def reconcile_pf_with_blocked(blocked: dict, dry: bool) -> int:
    """Compare pf table with blocked.json. Re-add any missing IPs. Return count restored.
    Called every poll cycle to self-heal if pf table gets cleared mid-run (e.g. by
    OPNsense config reload). Logs only when it actually does something."""
    if not blocked or dry:
        return 0
    on_pf = pfctl_show()
    missing = [ip for ip in blocked if ip not in on_pf]
    if not missing:
        return 0
    restored = 0
    for ip in missing:
        if pfctl_add(ip, dry=False):
            restored += 1
    if restored:
        log_info(f"reconcile: pf was missing {len(missing)} block(s) from blocked.json — re-added {restored}")
        telegram_notify(
            f"⚠️ Autoblock reconcile: pf table was missing {restored} block(s) from blocked.json. "
            f"Re-added. Likely an OPNsense pf reload cleared the runtime table. "
            f"IPs: {', '.join(missing[:5])}{'...' if len(missing)>5 else ''}"
        )
    return restored


def restore_blocks_to_pf(blocked: dict, dry: bool):
    if not blocked:
        log_info("no prior blocks to restore")
        return
    if dry:
        log_info(f"DRY-RUN: would restore {len(blocked)} prior blocks")
        return
    on_pf = pfctl_show()
    to_restore = [ip for ip in blocked if ip not in on_pf]
    already    = [ip for ip in blocked if ip in on_pf]
    log_info(f"reboot-recovery: pf={len(on_pf)} entries, json={len(blocked)}")
    if already:
        log_info(f"  {len(already)} already in pf")
    if to_restore:
        log_info(f"  restoring {len(to_restore)} IPs")
        r = 0
        for ip in to_restore:
            if pfctl_add(ip, dry=False):
                r += 1
        if r:
            telegram_notify(f"🔁 Autoblock reboot-recovery: re-added {r} prior block(s) to pf.")


# ── TTL expiry ────────────────────────────────────────────────────────────────

def expire_stale_blocks(blocked: dict, ttl_hours: float, dry: bool):
    if not ttl_hours or ttl_hours <= 0:
        return
    now = datetime.now(timezone.utc)
    cutoff = timedelta(hours=ttl_hours)
    expired = []
    for ip, ts_iso in list(blocked.items()):
        try:
            ts = datetime.fromisoformat(ts_iso.replace("Z", "+00:00"))
            if ts.tzinfo is None:
                ts = ts.replace(tzinfo=timezone.utc)
            if (now - ts) > cutoff:
                expired.append(ip)
        except Exception:
            continue
    for ip in expired:
        if pfctl_delete(ip, dry=dry):
            blocked.pop(ip, None)
            log_unblock(ip, "TTL_EXPIRED")
            telegram_notify(f"🔓 Autoblock TTL expired — `{ip}` unblocked.")
    if expired:
        save_blocked_db(blocked)


# ── Block helper ──────────────────────────────────────────────────────────────

def do_block(ip: str, reason: str, source: str, blocked: dict, dry: bool) -> bool:
    """Perform the block + update state + notify. Returns True if a new block was made."""
    if not pfctl_add(ip, dry=dry):
        return False
    blocked[ip] = datetime.now(timezone.utc).isoformat(timespec="seconds")
    save_blocked_db(blocked)
    log_block(ip, reason, dry=dry)
    prefix = "🧪 DRY-RUN would block" if dry else "🛡️ BLOCKED"
    telegram_notify(
        f"{prefix} `{ip}`  (via {source})\n"
        f"reason: {reason}"
    )
    return True


# ── Main loop ─────────────────────────────────────────────────────────────────

def main():
    args = parse_args()
    dry = bool(args.dry_run)
    ttl_hours = float(args.ttl_hours)

    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    blocked = load_blocked_db()

    mode = "DRY-RUN" if dry else "LIVE"
    log_info(f"OpenClaw Auto-Block v2 (SSH + filter-log) started — mode={mode} · TTL={ttl_hours}h · poll={POLL_INTERVAL}s")
    log_info(f"Lab whitelist: {len(LAB_WHITELIST)} IPs (+ self-sources.txt)")
    log_info(f"Prior blocks: {len(blocked)}")
    log_info(f"Filter-log scan thresholds: {SCAN_THRESHOLD_BLOCKS} blocks OR {SCAN_THRESHOLD_UNIQUE_PORTS} unique ports (last {FILTER_LOG_TAIL_LINES} lines)")

    telegram_notify(
        f"🛡️ Autoblock v2 starting ({mode})\n"
        f"Detection: Suricata eve.json + OPNsense filter log\n"
        f"TTL {ttl_hours}h · poll {POLL_INTERVAL}s\n"
        f"Prior blocks: {len(blocked)}"
    )

    restore_blocks_to_pf(blocked, dry=dry)

    try:
        while True:
            if DISABLE_FILE.exists():
                log_info(f"DISABLE file present — exiting")
                telegram_notify("🛑 Autoblock stopped (DISABLE file).")
                return

            whitelist = load_whitelist()
            expire_stale_blocks(blocked, ttl_hours, dry)
            reconcile_pf_with_blocked(blocked, dry)

            new_blocks = 0

            # --- Detection A: Suricata signatures ---
            eve_lines = fetch_eve_lines()
            sur_hits  = parse_scan_alerts(eve_lines)
            for alert in sur_hits:
                ip  = alert["src_ip"]
                sig = alert["signature"]
                if not ip or ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, f"Suricata sig: {sig[:100]}", "suricata", blocked, dry):
                    new_blocks += 1

            # --- Detection B: OPNsense filter log ---
            flog_lines = fetch_filter_log()
            flog_stats = parse_filter_log_scanners(flog_lines)
            flog_scanners = identify_scanners_from_filter(flog_stats)
            for ip, reason in flog_scanners:
                if ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, reason, "filter-log", blocked, dry):
                    new_blocks += 1

            if new_blocks:
                log_info(f"cycle: {new_blocks} new block(s) · suricata={len(sur_hits)} flog_candidates={len(flog_scanners)}")

            if args.once:
                log_info(f"--once exit. blocks={new_blocks}")
                return

            time.sleep(POLL_INTERVAL)
    except KeyboardInterrupt:
        log_info("Stopped (Ctrl-C)")
        telegram_notify("🛑 Autoblock stopped (Ctrl-C).")


if __name__ == "__main__":
    main()
