#!/usr/bin/env python3
"""
OpenClaw Auto-Block — Team 6 v4 (external-alias / file-push)

Architecture:
  - Source of truth lives in OPNsense's external alias backing file
    (/var/db/aliastables/autoblock_ip.txt). The autoblock_ip alias is
    type=external in config.xml, so update_tables.py preserves our entries
    instead of wiping them every minute.
  - autoblock writes the desired block list to a local file on Kali,
    SSH-pushes it to OPNsense, and runs `pfctl -T replace -f` to load
    into the runtime pf table. update_tables.py cron leaves it alone.
  - blocked.json on Kali is the authoritative state (TTL, audit).
  - reconcile() runs each cycle but should now be silent except after
    rare events (filter reload, OPNsense reboot) — wipes-per-minute
    are gone with the external-alias model.

Detection unchanged from v3:
  A) Suricata eve.json signatures
  B) OPNsense filter log volume / port-sweep heuristics

Telegram: glanceable single-line headlines + 2-3 key facts.
Local time stamps (PDT). Heartbeat disabled.
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
try:
    from zoneinfo import ZoneInfo
    LOCAL_TZ = ZoneInfo("America/Los_Angeles")
except Exception:
    LOCAL_TZ = None

# ── Config ────────────────────────────────────────────────────────────────────
OPN_SSH_USER   = "root"
OPN_SSH_HOST   = "172.31.0.2"
SSH_KEY        = os.path.expanduser("~/.ssh/id_rsa")
EVE_LOG_PATH   = "/var/log/suricata/eve.json"
EVE_TAIL_LINES = 200

PF_TABLE_IP            = "autoblock_ip"
OPN_ALIAS_FILE         = f"/var/db/aliastables/{PF_TABLE_IP}.txt"
KALI_ALIAS_FILE_LOCAL  = Path(os.path.expanduser("~/.openclaw/blocked_ips/blocked_ips.txt"))

CONFIG_DIR    = Path(os.path.expanduser("~/.config/openclaw-autoblock"))
BLOCK_DB      = CONFIG_DIR / "blocked.json"
LOG_FILE      = CONFIG_DIR / "autoblock.log"
DISABLE_FILE  = CONFIG_DIR / "DISABLE"
RECONCILE_LOG = CONFIG_DIR / "reconcile_events.json"

SELF_SOURCES_FILE = Path("/home/kali/.openclaw/workspace/scripts/self-sources.txt")
TELEGRAM_ALERT    = Path("/home/kali/.openclaw/workspace/scripts/telegram-alert.sh")
TELEGRAM_TIMEOUT  = 30

POLL_INTERVAL      = 30
DEFAULT_TTL_HOURS  = 24

FILTER_LOG_TAIL_LINES       = 5000
SCAN_THRESHOLD_BLOCKS       = 15
SCAN_THRESHOLD_UNIQUE_PORTS = 5

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
    ap = argparse.ArgumentParser(description="Team 6 Auto-Block v4 (external alias / file push)")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--ttl-hours", type=float, default=DEFAULT_TTL_HOURS)
    ap.add_argument("--once", action="store_true")
    return ap.parse_args()


# ── Logging ───────────────────────────────────────────────────────────────────

def _ts():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S")


def _ts_local():
    now = datetime.now(timezone.utc)
    if LOCAL_TZ is not None:
        return now.astimezone(LOCAL_TZ).strftime("%Y-%m-%d %H:%M:%S %Z")
    return now.strftime("%Y-%m-%d %H:%M:%S UTC")


def _ts_short_local():
    """Just HH:MM PDT/PST for headlines."""
    now = datetime.now(timezone.utc)
    if LOCAL_TZ is not None:
        return now.astimezone(LOCAL_TZ).strftime("%H:%M %Z")
    return now.strftime("%H:%M UTC")


def _log(line):
    print(line, flush=True)


def log_block(ip, rule, dry=False):
    tag = "DRYRUN_WOULD_BLOCK" if dry else "BLOCKED"
    _log(f"{_ts()} | {tag} | IP={ip} | RULE={rule}")


def log_unblock(ip, reason):
    _log(f"{_ts()} | UNBLOCKED | IP={ip} | REASON={reason}")


def log_info(msg):  _log(f"{_ts()} | INFO  | {msg}")
def log_warn(msg):  _log(f"{_ts()} | WARN  | {msg}")
def log_error(msg): _log(f"{_ts()} | ERROR | {msg}")
def log_debug(msg): _log(f"{_ts()} | DEBUG | {msg}")


# ── State ─────────────────────────────────────────────────────────────────────

def load_whitelist() -> set:
    wl = set(LAB_WHITELIST)
    if SELF_SOURCES_FILE.exists():
        try:
            for line in SELF_SOURCES_FILE.read_text().splitlines():
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                wl.add(line.split(None, 1)[0])
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


def load_reconcile_log() -> dict:
    if RECONCILE_LOG.exists():
        try:
            return json.loads(RECONCILE_LOG.read_text())
        except Exception:
            pass
    return {"total": 0, "session": 0, "last": None, "session_started": _ts()}


def save_reconcile_log(d: dict):
    RECONCILE_LOG.write_text(json.dumps(d, indent=2, sort_keys=True))


# ── Telegram ──────────────────────────────────────────────────────────────────

def telegram_notify(msg: str) -> bool:
    if not TELEGRAM_ALERT.exists():
        log_error(f"telegram-alert script missing at {TELEGRAM_ALERT}")
        return False
    try:
        r = subprocess.run([str(TELEGRAM_ALERT), msg],
                           timeout=TELEGRAM_TIMEOUT,
                           capture_output=True, text=True, check=False)
        if r.returncode == 0:
            return True
        log_error(f"telegram-alert exit rc={r.returncode}: {r.stderr.strip()[:200]}")
        return False
    except subprocess.TimeoutExpired:
        log_error(f"telegram-alert timed out after {TELEGRAM_TIMEOUT}s "
                  f"(message preview: {msg[:80]!r})")
        return False
    except Exception as exc:
        log_error(f"telegram-alert exception: {type(exc).__name__}: {exc}")
        return False


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


def scp_to_opn(local_path: str, remote_path: str, timeout: int = 15) -> bool:
    try:
        r = subprocess.run(
            ["scp", *SSH_OPTS, local_path, f"{OPN_SSH_USER}@{OPN_SSH_HOST}:{remote_path}"],
            capture_output=True, timeout=timeout, text=True
        )
        if r.returncode == 0:
            return True
        log_error(f"scp failed rc={r.returncode}: {r.stderr.strip()[:200]}")
        return False
    except subprocess.TimeoutExpired:
        log_error(f"scp timed out after {timeout}s")
        return False


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
    rc, out, err = ssh_run(
        f"tail -n {FILTER_LOG_TAIL_LINES} /var/log/filter/latest.log",
        timeout=15,
    )
    if rc != 0:
        log_error(f"filter-log fetch failed (rc={rc}): {err.strip()[:200]}")
        return []
    return out.splitlines()


def parse_filter_log_scanners(lines) -> dict:
    stats = collections.defaultdict(lambda: {"count": 0, "unique_ports": set()})
    for raw in lines:
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
    out = {}
    for ip, s in stats.items():
        out[ip] = {"count": s["count"], "unique_ports": sorted(s["unique_ports"])}
    return out


def identify_scanners_from_filter(filter_stats: dict) -> list:
    scanners = []
    for ip, s in filter_stats.items():
        count = s["count"]
        unique = len(s["unique_ports"])
        if count >= SCAN_THRESHOLD_BLOCKS:
            scanners.append((ip, f"{count} blocked probes, {unique} unique ports",
                             {"blocks": count, "unique_ports": unique, "ports": s["unique_ports"][:10]}))
        elif unique >= SCAN_THRESHOLD_UNIQUE_PORTS and count >= 5:
            scanners.append((ip, f"{unique} unique ports probed in {count} attempts",
                             {"blocks": count, "unique_ports": unique, "ports": s["unique_ports"][:10]}))
    return scanners


# ── pf table sync (the v4 magic) ──────────────────────────────────────────────

def write_local_alias_file(blocked: dict):
    """Write the IP set to local file (one per line, sorted)."""
    KALI_ALIAS_FILE_LOCAL.parent.mkdir(parents=True, exist_ok=True)
    content = "\n".join(sorted(blocked.keys()))
    if content:
        content += "\n"
    KALI_ALIAS_FILE_LOCAL.write_text(content)


def push_alias_file_to_opn(dry: bool) -> bool:
    """SCP the local file to OPNsense's external alias backing file."""
    if dry:
        return True
    return scp_to_opn(str(KALI_ALIAS_FILE_LOCAL), OPN_ALIAS_FILE, timeout=15)


def pf_replace_from_file(dry: bool) -> tuple:
    """pfctl -T replace -f. Returns (ok, count_added_or_updated, stderr)."""
    if dry:
        return True, 0, ""
    rc, out, err = ssh_run(
        f"pfctl -t {PF_TABLE_IP} -T replace -f {OPN_ALIAS_FILE}",
        timeout=15,
    )
    if rc != 0:
        log_error(f"pfctl replace failed rc={rc}: {err.strip()[:200]}")
        return False, 0, err.strip()
    # output looks like "1 addresses added." / "no changes." etc.
    return True, 0, out.strip()


def pfctl_show() -> tuple:
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T show", timeout=10)
    if rc != 0:
        return set(), False
    return {ln.strip() for ln in out.splitlines() if ln.strip()}, True


def sync_to_opn(blocked: dict, dry: bool, reason: str = "") -> bool:
    """Full sync: write local file → scp to OPNsense → pfctl replace.
    Returns True if all steps succeeded."""
    write_local_alias_file(blocked)
    if dry:
        log_info(f"DRY-RUN: would sync {len(blocked)} IPs to OPNsense ({reason})")
        return True
    if not push_alias_file_to_opn(dry=False):
        return False
    ok, _, msg = pf_replace_from_file(dry=False)
    if ok:
        log_info(f"sync OK: {len(blocked)} IPs in alias_file + pf — {reason} — pfctl: {msg}")
    return ok


# ── Reconcile (rare under v4) ─────────────────────────────────────────────────

def reconcile(blocked: dict, dry: bool, recon_log: dict) -> int:
    """Verify pf table matches blocked.json. Under v4 the pf state should
    persist (external alias, no minutely wipes). This catches rare events
    like filter reload or OPNsense reboot. Logs every drift; only Telegrams
    if drift is unexpected (>3 entries off, or first of session)."""
    if dry:
        return 0
    on_pf, ok = pfctl_show()
    if not ok:
        return 0
    intended = set(blocked.keys())
    missing  = intended - on_pf
    extra    = on_pf - intended
    if not missing and not extra:
        return 0

    # Drift detected — push truth to OPNsense
    success = sync_to_opn(blocked, dry=False, reason=f"reconcile drift (missing={len(missing)} extra={len(extra)})")
    if not success:
        log_error("reconcile sync failed")
        return 0

    recon_log["total"] += 1
    recon_log["session"] += 1
    recon_log["last"] = _ts()
    save_reconcile_log(recon_log)

    n = recon_log["session"]
    notify_first = (n == 1)
    notify_big = (len(missing) >= 3 or len(extra) >= 3)
    if notify_first or notify_big:
        msg = (
            f"⚠️ *RECONCILE* · `{_ts_short_local()}`\n"
            f"pf was out of sync — restored from blocked.json\n"
            f"Missing: `{len(missing)}` · Extra: `{len(extra)}`\n"
            f"Active blocks now: `{len(blocked)}`"
        )
        telegram_notify(msg)
    return len(missing) + len(extra)


# ── TTL expiry ────────────────────────────────────────────────────────────────

def expire_stale_blocks(blocked: dict, ttl_hours: float, dry: bool) -> int:
    if not ttl_hours or ttl_hours <= 0:
        return 0
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
        log_unblock(ip, "TTL_EXPIRED")
        blocked.pop(ip, None)
    if expired:
        save_blocked_db(blocked)
        sync_to_opn(blocked, dry=dry, reason=f"TTL expired {len(expired)} block(s)")
        for ip in expired:
            telegram_notify(
                f"🔓 *UNBLOCKED* `{ip}` · `{_ts_short_local()}`\n"
                f"Reason: 24h TTL expired\n"
                f"Active blocks: `{len(blocked)}`"
            )
    return len(expired)


# ── Block helper ──────────────────────────────────────────────────────────────

def do_block(ip: str, reason: str, source: str, blocked: dict, dry: bool, extra: dict = None) -> bool:
    blocked[ip] = datetime.now(timezone.utc).isoformat(timespec="seconds")
    save_blocked_db(blocked)
    if not sync_to_opn(blocked, dry=dry, reason=f"new block {ip}"):
        # Roll back blocked.json on sync failure
        blocked.pop(ip, None)
        save_blocked_db(blocked)
        log_error(f"block {ip} failed: sync to OPNsense failed; rolled back state")
        return False
    log_block(ip, reason, dry=dry)
    extra = extra or {}
    src_short = {"suricata": "Suricata sig", "filter-log": "filter log"}.get(source, source)

    extra_line = ""
    if "blocks" in extra and "unique_ports" in extra:
        extra_line = f"`{extra['blocks']}` events across `{extra['unique_ports']}` ports"
        if extra.get("ports"):
            extra_line += f" → `{', '.join(map(str, extra['ports'][:6]))}`"
            if len(extra['ports']) > 6:
                extra_line += "…"

    head = "🧪 *DRYRUN BLOCK*" if dry else "🚨 *BLOCKED*"
    msg = (
        f"{head} `{ip}` · `{_ts_short_local()}`\n"
        f"Why: {reason}\n"
        + (f"Detail: {extra_line}\n" if extra_line else "")
        + f"Detected by: {src_short}\n"
        f"Active blocks: `{len(blocked)}`"
    )
    telegram_notify(msg)
    return True


# ── Health check at startup ───────────────────────────────────────────────────

def startup_health_check() -> dict:
    health = {}
    rc, out, err = ssh_run("echo PONG && uname -srm", timeout=8)
    health["opnsense_ssh"] = (rc == 0, out.strip()[:80] if rc == 0 else err.strip()[:80])
    on_pf, ok = pfctl_show()
    health["opnsense_pf_read"] = (ok, f"{len(on_pf)} entries")
    rc, out, err = ssh_run(f"test -f {OPN_ALIAS_FILE} && head -3 {OPN_ALIAS_FILE} && echo ===END===", timeout=8)
    health["opn_alias_file"] = (rc == 0, out.split('===END===', 1)[0].strip()[:80] or "(empty)")
    health["telegram_script"] = (TELEGRAM_ALERT.exists(), str(TELEGRAM_ALERT))
    health["ssh_key"] = (Path(SSH_KEY).exists(), SSH_KEY)
    return health


# ── Main loop ─────────────────────────────────────────────────────────────────

def main():
    args = parse_args()
    dry = bool(args.dry_run)
    ttl_hours = float(args.ttl_hours)

    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    KALI_ALIAS_FILE_LOCAL.parent.mkdir(parents=True, exist_ok=True)
    blocked = load_blocked_db()
    recon_log = load_reconcile_log()
    recon_log["session"] = 0
    recon_log["session_started"] = _ts()
    save_reconcile_log(recon_log)

    mode = "DRY-RUN" if dry else "LIVE"
    log_info(f"=" * 70)
    log_info(f"OpenClaw Auto-Block v4 (external-alias) starting — pid={os.getpid()}")
    log_info(f"  mode={mode} · TTL={ttl_hours}h · poll={POLL_INTERVAL}s · telegram_timeout={TELEGRAM_TIMEOUT}s")
    log_info(f"  whitelist: {len(LAB_WHITELIST)} lab IPs (+ self-sources.txt)")
    log_info(f"  prior blocks: {len(blocked)} → {list(blocked.keys())}")
    log_info(f"  scan thresholds: {SCAN_THRESHOLD_BLOCKS} blocks OR {SCAN_THRESHOLD_UNIQUE_PORTS} unique ports")
    log_info(f"  alias file (kali): {KALI_ALIAS_FILE_LOCAL}")
    log_info(f"  alias file (opn):  {OPN_ALIAS_FILE}")
    log_info(f"  lifetime reconciles: {recon_log.get('total', 0)}")

    health = startup_health_check()
    health_lines = []
    for name, (ok, detail) in health.items():
        glyph = "✅" if ok else "❌"
        log_info(f"  health {glyph} {name}: {detail}")
        health_lines.append(f"{glyph} {name}")
    all_healthy = all(ok for ok, _ in health.values())

    # Initial sync: ensure OPNsense state matches blocked.json
    if blocked and not dry:
        log_info("startup sync: pushing blocked.json → OPNsense alias_file + pf")
        sync_to_opn(blocked, dry=False, reason="startup")

    blocks_short = ", ".join(blocked.keys()) if blocked else "(none)"
    startup_msg = (
        f"🛡 *AUTOBLOCK v4 ARMED* · `{_ts_short_local()}`\n"
        f"Active blocks: `{len(blocked)}` → `{blocks_short}`\n"
        f"Path: SSH push → `{OPN_ALIAS_FILE}` (external alias)\n"
        f"Health: {'  '.join(health_lines)}\n"
        f"PID `{os.getpid()}` · Poll `{POLL_INTERVAL}s` · TTL `{ttl_hours}h`"
    )
    telegram_notify(startup_msg)

    if not all_healthy:
        log_warn("startup health check has failures — proceeding anyway")

    cycles = 0
    try:
        while True:
            if DISABLE_FILE.exists():
                log_info(f"DISABLE file present — exiting")
                telegram_notify(f"🛑 *AUTOBLOCK STOPPED* · `{_ts_short_local()}`\nReason: DISABLE file present")
                return

            cycles += 1
            whitelist = load_whitelist()
            expire_stale_blocks(blocked, ttl_hours, dry)
            reconcile(blocked, dry, recon_log)

            new_blocks = 0

            eve_lines = fetch_eve_lines()
            sur_hits  = parse_scan_alerts(eve_lines)
            for alert in sur_hits:
                ip  = alert["src_ip"]
                sig = alert["signature"]
                if not ip or ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, sig[:120], "suricata", blocked, dry):
                    new_blocks += 1

            flog_lines = fetch_filter_log()
            flog_stats = parse_filter_log_scanners(flog_lines)
            flog_scanners = identify_scanners_from_filter(flog_stats)
            for ip, reason, extra in flog_scanners:
                if ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, reason, "filter-log", blocked, dry, extra=extra):
                    new_blocks += 1

            if new_blocks:
                log_info(f"cycle {cycles}: {new_blocks} new block(s) · suricata={len(sur_hits)} flog_candidates={len(flog_scanners)}")

            if args.once:
                log_info(f"--once exit. cycle={cycles} new_blocks={new_blocks}")
                return

            time.sleep(POLL_INTERVAL)
    except KeyboardInterrupt:
        log_info("Stopped (Ctrl-C)")
        telegram_notify(f"🛑 *AUTOBLOCK STOPPED* · `{_ts_short_local()}`\nReason: Ctrl-C")


if __name__ == "__main__":
    main()
