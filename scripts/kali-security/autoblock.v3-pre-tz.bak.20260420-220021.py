#!/usr/bin/env python3
"""
OpenClaw Auto-Block — Team 6 v3 (wipe-aware)

Detects scanners via TWO paths:
  A) Suricata eve.json — signature-based (ET SCAN, NMAP, etc.)
  B) OPNsense filter log — counts blocked-inbound events per source IP

Day-1 root-cause fix (2026-04-20):
  OPNsense cron `update_tables.py` runs every minute and wipes runtime pf entries
  not backed by an alias in config.xml. Our `autoblock_ip` alias has empty
  `<content/>`, so its pf table gets cleared every ~60s. Reconcile re-adds.
  This is now treated as expected behavior (logged, but no Telegram spam).

Key v3 changes vs v2:
  • Telegram subprocess timeout 15s → 30s (real send ~17s; was false-erroring)
  • telegram_notify() returns success bool; failures logged with detail
  • pfctl_show() logs raw rc/stdout/stderr length for any unexpected condition
  • reconcile_pf_with_blocked() persists wipe stats to wipe_stats.json
  • Reconcile Telegram alerts: first wipe of session, every 100th wipe (heartbeat),
    or "unexpected" wipes (gap > 5 min suggests OPNsense isn't doing its 1/min cron)
  • Rich Telegram messages on real blocks: includes context, counts, attribution
  • Startup health check: tests OPNsense SSH + Telegram before entering loop
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
WIPE_STATS    = CONFIG_DIR / "wipe_stats.json"

SELF_SOURCES_FILE = Path("/home/kali/.openclaw/workspace/scripts/self-sources.txt")
TELEGRAM_ALERT    = Path("/home/kali/.openclaw/workspace/scripts/telegram-alert.sh")
TELEGRAM_TIMEOUT  = 30  # bumped from 15 — real send takes ~17s

POLL_INTERVAL = 30
DEFAULT_TTL_HOURS = 24

FILTER_LOG_TAIL_LINES      = 5000
SCAN_THRESHOLD_BLOCKS      = 15
SCAN_THRESHOLD_UNIQUE_PORTS = 5

# Wipe-alert tuning
WIPE_HEARTBEAT_EVERY = 100   # send a status Telegram every Nth wipe
WIPE_GAP_ALERT_MIN   = 5     # if no wipes for >5min, that's unusual — alert

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
    ap = argparse.ArgumentParser(description="Team 6 Auto-Block v3 (wipe-aware)")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--ttl-hours", type=float, default=DEFAULT_TTL_HOURS)
    ap.add_argument("--once", action="store_true")
    return ap.parse_args()


# ── Logging ───────────────────────────────────────────────────────────────────

def _ts():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S")


def _log(line):
    # stdout is nohup-redirected to the log file; print() reaches it.
    # Don't double-write by also opening the file directly.
    print(line, flush=True)


def log_block(ip, rule, dry=False):
    tag = "DRYRUN_WOULD_BLOCK" if dry else "BLOCKED"
    _log(f"{_ts()} | {tag} | IP={ip} | RULE={rule}")


def log_unblock(ip, reason):
    _log(f"{_ts()} | UNBLOCKED | IP={ip} | REASON={reason}")


def log_info(msg):
    _log(f"{_ts()} | INFO | {msg}")


def log_warn(msg):
    _log(f"{_ts()} | WARN | {msg}")


def log_error(msg):
    _log(f"{_ts()} | ERROR | {msg}")


def log_debug(msg):
    _log(f"{_ts()} | DEBUG | {msg}")


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


def load_wipe_stats() -> dict:
    if WIPE_STATS.exists():
        try:
            return json.loads(WIPE_STATS.read_text())
        except Exception:
            pass
    return {"total_wipes": 0, "session_wipes": 0, "first_wipe_ts": None,
            "last_wipe_ts": None, "session_started": _ts()}


def save_wipe_stats(stats: dict):
    WIPE_STATS.write_text(json.dumps(stats, indent=2, sort_keys=True))


# ── Telegram ──────────────────────────────────────────────────────────────────

def telegram_notify(msg: str) -> bool:
    """Send Telegram notification. Returns True on success, False on failure.
    Failures are logged with full detail; never raises."""
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
            scanners.append((ip, f"filter-log scan: {count} blocks, {unique} unique dst ports",
                             {"blocks": count, "unique_ports": unique, "ports": s["unique_ports"][:10]}))
        elif unique >= SCAN_THRESHOLD_UNIQUE_PORTS and count >= 5:
            scanners.append((ip, f"filter-log port-sweep: {unique} unique dst ports, {count} events",
                             {"blocks": count, "unique_ports": unique, "ports": s["unique_ports"][:10]}))
    return scanners


# ── pf table ops via SSH ──────────────────────────────────────────────────────

def pfctl_add(ip: str, dry: bool) -> bool:
    if dry:
        return True
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T add {ip}", timeout=10)
    if rc == 0:
        return True
    log_error(f"pfctl add {ip} failed (rc={rc}, stderr={err.strip()[:200]!r})")
    return False


def pfctl_delete(ip: str, dry: bool) -> bool:
    if dry:
        return True
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T delete {ip}", timeout=10)
    if rc == 0:
        return True
    log_error(f"pfctl delete {ip} failed (rc={rc}, stderr={err.strip()[:200]!r})")
    return False


def pfctl_show() -> tuple:
    """Returns (set_of_ips, ssh_ok). ssh_ok=False if call failed."""
    rc, out, err = ssh_run(f"pfctl -t {PF_TABLE_IP} -T show", timeout=10)
    if rc != 0:
        log_error(f"pfctl show failed (rc={rc}, stderr={err.strip()[:200]!r})")
        return set(), False
    return {ln.strip() for ln in out.splitlines() if ln.strip()}, True


# ── Wipe-aware reconcile ──────────────────────────────────────────────────────

def reconcile_pf_with_blocked(blocked: dict, dry: bool, wipe_stats: dict) -> int:
    """Compare pf table with blocked.json. Re-add any missing IPs.
    Tracks wipe events. Telegram-quiet for expected wipes (root cause: OPNsense
    update_tables.py cron rebuilds runtime pf tables every minute from config.xml
    aliases; our autoblock_ip alias has empty <content/>, so the runtime adds
    get cleared each cycle). Telegrams only for first-of-session, periodic
    heartbeat, or unexpected gaps."""
    if not blocked or dry:
        return 0
    on_pf, ok = pfctl_show()
    if not ok:
        return 0  # SSH failed; don't make assumptions about table state
    missing = [ip for ip in blocked if ip not in on_pf]
    if not missing:
        return 0
    restored = 0
    for ip in missing:
        if pfctl_add(ip, dry=False):
            restored += 1
    if not restored:
        log_warn(f"reconcile: {len(missing)} block(s) missing from pf, but pfctl add failed for all")
        return 0

    # Update stats
    now_iso = _ts()
    now_dt  = datetime.now(timezone.utc)
    wipe_stats["total_wipes"] += 1
    wipe_stats["session_wipes"] += 1
    if not wipe_stats.get("first_wipe_ts"):
        wipe_stats["first_wipe_ts"] = now_iso
    last_iso = wipe_stats.get("last_wipe_ts")
    gap_sec = None
    if last_iso:
        try:
            last_dt = datetime.fromisoformat(last_iso.replace("Z", "+00:00"))
            if last_dt.tzinfo is None:
                last_dt = last_dt.replace(tzinfo=timezone.utc)
            gap_sec = (now_dt - last_dt).total_seconds()
        except Exception:
            pass
    wipe_stats["last_wipe_ts"] = now_iso
    save_wipe_stats(wipe_stats)

    # Always log to file with timestamp + count + missing IPs + gap
    gap_str = f"gap={gap_sec:.0f}s" if gap_sec is not None else "gap=first"
    log_info(f"reconcile: pf wiped (likely OPNsense update_tables.py cron) — "
             f"re-added {restored}/{len(missing)} block(s) "
             f"[session_wipe #{wipe_stats['session_wipes']}, total #{wipe_stats['total_wipes']}, {gap_str}] "
             f"IPs: {','.join(missing[:5])}")

    # Decide whether to Telegram
    n = wipe_stats["session_wipes"]
    notify = False
    notify_reason = ""
    if n == 1:
        notify, notify_reason = True, "first wipe of session"
    elif n % WIPE_HEARTBEAT_EVERY == 0:
        notify, notify_reason = True, f"heartbeat (every {WIPE_HEARTBEAT_EVERY})"
    elif gap_sec is not None and gap_sec > WIPE_GAP_ALERT_MIN * 60:
        notify, notify_reason = True, f"unusual gap ({gap_sec:.0f}s since last wipe — OPNsense cron may have stopped)"

    if notify:
        gap_line = f"`{gap_sec:.0f}s` (expected: ~60s)" if gap_sec is not None else "`first wipe of session`"
        msg = (
            f"🔄 *Autoblock reconcile fired* — `{notify_reason}`\n"
            f"━━━━━━━━━━━━━━━━━━━\n"
            f"⏱  *When:* `{now_iso} UTC`\n"
            f"🛡  *Re-added:* `{restored}` IP(s) → pf `{PF_TABLE_IP}`\n"
            f"🎯  *IPs restored:* `{', '.join(missing[:5])}`{'...' if len(missing) > 5 else ''}\n"
            f"📊  *Session wipes:* `{n}` (lifetime: `{wipe_stats['total_wipes']}`)\n"
            f"⌛  *Gap since last:* {gap_line}\n"
            f"━━━━━━━━━━━━━━━━━━━\n"
            f"ℹ️  *Root cause:* OPNsense `update_tables.py` cron rebuilds pf tables\n"
            f"    every 60s from config.xml aliases. Our `autoblock_ip` alias has\n"
            f"    empty content, so runtime entries get wiped each cycle.\n"
            f"    Reconcile re-adds them within one poll. Defense remains intact."
        )
        telegram_notify(msg)
    return restored


def restore_blocks_to_pf(blocked: dict, dry: bool):
    if not blocked:
        log_info("no prior blocks to restore")
        return
    if dry:
        log_info(f"DRY-RUN: would restore {len(blocked)} prior blocks")
        return
    on_pf, ok = pfctl_show()
    if not ok:
        log_warn("startup pfctl_show failed; will rely on next reconcile cycle")
        return
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
            telegram_notify(f"🔁 *Autoblock reboot-recovery*\nRe-added `{r}` prior block(s) to pf at startup.\nIPs: `{', '.join(to_restore[:5])}`")


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
            telegram_notify(f"🔓 *Autoblock TTL expired*\n`{ip}` unblocked after {ttl_hours}h.")
    if expired:
        save_blocked_db(blocked)


# ── Block helper ──────────────────────────────────────────────────────────────

def do_block(ip: str, reason: str, source: str, blocked: dict, dry: bool, extra: dict = None) -> bool:
    """Perform the block + update state + notify."""
    if not pfctl_add(ip, dry=dry):
        return False
    blocked[ip] = datetime.now(timezone.utc).isoformat(timespec="seconds")
    save_blocked_db(blocked)
    log_block(ip, reason, dry=dry)
    prefix = "🧪 *DRY-RUN would block*" if dry else "🚨 *AUTOBLOCK BLOCKED*"
    extra = extra or {}
    extra_lines = ""
    if "blocks" in extra:
        extra_lines += f"📊  *Blocked events:* `{extra['blocks']}`\n"
    if "unique_ports" in extra:
        extra_lines += f"🔌  *Unique dst ports:* `{extra['unique_ports']}`\n"
    if extra.get("ports"):
        extra_lines += f"🎯  *Sample ports:* `{', '.join(map(str, extra['ports']))}`\n"
    msg = (
        f"{prefix} `{ip}`\n"
        f"━━━━━━━━━━━━━━━━━━━\n"
        f"⏱  *When:* `{_ts()} UTC`\n"
        f"🔍  *Detected via:* `{source}`\n"
        f"📝  *Reason:* {reason}\n"
        f"{extra_lines}"
        f"📦  *Total blocks now:* `{len(blocked)}`\n"
        f"━━━━━━━━━━━━━━━━━━━\n"
        f"Action: added to pf `{PF_TABLE_IP}` table on OPNsense (`{OPN_SSH_HOST}`).\n"
        f"TTL: 24h auto-expiry. Survives wipes via reconcile loop."
    )
    telegram_notify(msg)
    return True


# ── Health check at startup ───────────────────────────────────────────────────

def startup_health_check() -> dict:
    """Return dict of {check_name: (ok_bool, detail_str)}."""
    health = {}
    # SSH to OPNsense
    rc, out, err = ssh_run("echo PONG && uname -srm", timeout=8)
    health["opnsense_ssh"] = (rc == 0, out.strip()[:80] if rc == 0 else err.strip()[:80])
    # pf table read
    on_pf, ok = pfctl_show()
    health["opnsense_pf_read"] = (ok, f"{len(on_pf)} entries")
    # Telegram script present
    health["telegram_script"] = (TELEGRAM_ALERT.exists(), str(TELEGRAM_ALERT))
    # SSH key
    health["ssh_key"] = (Path(SSH_KEY).exists(), SSH_KEY)
    return health


# ── Main loop ─────────────────────────────────────────────────────────────────

def main():
    args = parse_args()
    dry = bool(args.dry_run)
    ttl_hours = float(args.ttl_hours)

    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    blocked = load_blocked_db()
    wipe_stats = load_wipe_stats()
    # Reset session counter (lifetime persists)
    wipe_stats["session_wipes"] = 0
    wipe_stats["session_started"] = _ts()
    save_wipe_stats(wipe_stats)

    mode = "DRY-RUN" if dry else "LIVE"
    log_info(f"=" * 70)
    log_info(f"OpenClaw Auto-Block v3 (wipe-aware) starting — pid={os.getpid()}")
    log_info(f"  mode={mode} · TTL={ttl_hours}h · poll={POLL_INTERVAL}s · telegram_timeout={TELEGRAM_TIMEOUT}s")
    log_info(f"  whitelist: {len(LAB_WHITELIST)} lab IPs (+ self-sources.txt)")
    log_info(f"  prior blocks: {len(blocked)} → {list(blocked.keys())}")
    log_info(f"  scan thresholds: {SCAN_THRESHOLD_BLOCKS} blocks OR {SCAN_THRESHOLD_UNIQUE_PORTS} ports (last {FILTER_LOG_TAIL_LINES} lines)")
    log_info(f"  lifetime wipes seen: {wipe_stats.get('total_wipes', 0)}")

    health = startup_health_check()
    health_lines = []
    for name, (ok, detail) in health.items():
        glyph = "✅" if ok else "❌"
        log_info(f"  health {glyph} {name}: {detail}")
        health_lines.append(f"{glyph} `{name}`: {detail}")
    all_healthy = all(ok for ok, _ in health.values())

    startup_msg = (
        f"🛡️ *Autoblock v3 starting* ({mode})\n"
        f"━━━━━━━━━━━━━━━━━━━\n"
        f"⏱  *Started:* `{_ts()} UTC`\n"
        f"🆔  *PID:* `{os.getpid()}`\n"
        f"📦  *Prior blocks:* `{len(blocked)}` → `{', '.join(blocked.keys()) if blocked else '(none)'}`\n"
        f"🔄  *Lifetime wipes:* `{wipe_stats.get('total_wipes', 0)}`\n"
        f"⚙️  *Poll:* `{POLL_INTERVAL}s` · *TTL:* `{ttl_hours}h`\n"
        f"━━━━━━━━━━━━━━━━━━━\n"
        f"*Health checks:*\n" + "\n".join(health_lines) + "\n"
        f"━━━━━━━━━━━━━━━━━━━\n"
        f"Detection paths: Suricata sigs + OPNsense filter log\n"
        f"Disable: `touch {DISABLE_FILE}`"
    )
    telegram_notify(startup_msg)

    if not all_healthy:
        log_warn("startup health check has failures — proceeding anyway")

    restore_blocks_to_pf(blocked, dry=dry)

    cycles = 0
    try:
        while True:
            if DISABLE_FILE.exists():
                log_info(f"DISABLE file present — exiting")
                telegram_notify("🛑 *Autoblock stopped* (DISABLE file present).")
                return

            cycles += 1
            whitelist = load_whitelist()
            expire_stale_blocks(blocked, ttl_hours, dry)
            reconcile_pf_with_blocked(blocked, dry, wipe_stats)

            new_blocks = 0

            # Detection A: Suricata
            eve_lines = fetch_eve_lines()
            sur_hits  = parse_scan_alerts(eve_lines)
            for alert in sur_hits:
                ip  = alert["src_ip"]
                sig = alert["signature"]
                if not ip or ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, f"Suricata sig: {sig[:100]}", "suricata", blocked, dry):
                    new_blocks += 1

            # Detection B: filter log
            flog_lines = fetch_filter_log()
            flog_stats = parse_filter_log_scanners(flog_lines)
            flog_scanners = identify_scanners_from_filter(flog_stats)
            for ip, reason, extra in flog_scanners:
                if ip in whitelist or ip in blocked:
                    continue
                if do_block(ip, reason, "filter-log", blocked, dry, extra=extra):
                    new_blocks += 1

            if new_blocks:
                log_info(f"cycle {cycles}: {new_blocks} new block(s) · suricata_alerts={len(sur_hits)} flog_candidates={len(flog_scanners)}")

            if args.once:
                log_info(f"--once exit. cycle={cycles} new_blocks={new_blocks}")
                return

            time.sleep(POLL_INTERVAL)
    except KeyboardInterrupt:
        log_info("Stopped (Ctrl-C)")
        telegram_notify("🛑 *Autoblock stopped* (Ctrl-C).")


if __name__ == "__main__":
    main()
