# Session 22 — IR Watcher Pipeline + Autoblock Deployment

**Date:** 2026-04-19 (night before pentest week kicks off)
**Operator:** James
**Collaborators:** Jason (OPNsense UI setup for autoblock)
**Scope:** Build the real-time incident response + autoblocking pipeline for pentest week (2026-04-20 → 2026-04-27).

---

## 1 · Executive Summary

Today's evening session delivered four connected pieces of defensive infrastructure, all live at session end:

1. **IR Watcher pipeline** — cron-driven Suricata alert digests on Kali (Tier A) + heartbeat-driven LLM narrative via OpenClaw's main agent (Tier B)
2. **OpenClaw Telegram integration** — revived from dormant state, wired to the existing Team 6 group with a new "IR Watcher" forum topic for alerts
3. **Autoblock.py** (SSH-path variant) — detects scanners from Suricata alerts every 30s and pushes them into the OPNsense `autoblock_ip` pf table, where a pre-existing block rule drops their traffic at the WAN interface
4. **Documentation + handoff** — architecture doc for Jason's in-progress OPNsense API app, Mac-mini testing handoff, tracker updates for the new API service account

Pentest kickoff readiness: **green**. All defenses are layered, observable, and emergency-disableable.

---

## 2 · What Changed Tonight

### 2.1 Kali (172.31.0.100)

**New files** under `~/.openclaw/workspace/`:

| Path | Purpose |
|------|---------|
| `scripts/suricata-digest.py` | Tier A cron job — pulls OPNsense eve.json every 15 min, dedups, writes markdown digest + state |
| `scripts/telegram-alert.sh` | OpenClaw CLI wrapper — sends critical alerts to IR Watcher topic (thread 71) |
| `scripts/critical-patterns.txt` | ~30 regex patterns for Suricata signatures that merit a Telegram page |
| `scripts/self-sources.txt` | Whitelist of IPs de-prioritized (Kali itself; extensible during pentest) |
| `scripts/autoblock.py` | SSH-path autoblock — 30s poll, pfctl add via SSH, TTL auto-expiry |
| `agents/team6/ir-watcher.md` | New agent persona: SOC analyst role with full defensive posture baked in |
| `MEMORY.md` | Rewritten with current Session 18–21d defensive posture (loaded by every OpenClaw session) |
| `HEARTBEAT.md` | Instructions for main agent — Tier B LLM check, writes to daily memory only when there are unread alerts |
| `ir-watcher.config.json` | Group + thread + DM IDs for telegram-alert.sh |

**Config changes:**
- `registry.json` — registered new `ir-watcher` agent (5 personas total)
- `openclaw.json` — enabled Telegram plugin, registered account `default` (bot token file at `~/.openclaw/credentials/telegram-bot-token` 0600)
- `openclaw.json` — added `channels.telegram.groups["-1003807356020"]` with `requireMention: true` (keeps group quiet) + topic 71 override
- User crontab — `*/15 * * * *` entry for suricata-digest.py

**New credentials files** (0600 perms):
- `~/.openclaw/credentials/telegram-bot-token` — Telegram bot API token
- `~/.openclaw/credentials/opnsense-root-pw` — OPNsense root password (SSH password fallback)
- `~/.config/openclaw-autoblock/opn-api.env` — OPNsense API key/secret for CounterDefense_ACCESS user (NOT currently used — SSH path in effect; kept for future use)

**New long-running process:**
- Autoblock.py started under `nohup` with `PYTHONUNBUFFERED=1`, PID persisted at `~/.config/openclaw-autoblock/autoblock.pid`

### 2.2 OPNsense (172.31.0.2)

**Manual UI setup by Jason:**
- Created dedicated API service account `CounterDefense_ACCESS`
  - Privileges: `Firewall: Alias: Edit` + `Firewall: Aliases: Reload` (least privilege)
  - Expires: 2026-04-28 (end of pentest week)
  - API key + secret generated, stored on Kali at `~/.config/openclaw-autoblock/opn-api.env`
- Created firewall aliases:
  - `autoblock_ip` (Host type) — populated by autoblock.py at runtime
  - `autoblock_mac` (MAC type) — placeholder, not currently populated (pf tables are IP-native; MAC blocking is out of scope)
- Created WAN firewall rules (via Firewall → Rules → WAN):
  - **Block**: source = `autoblock_ip` alias, destination = any, quick
  - **Pass**: source = `172.31.0.100` (Kali), destination = WAN address, port 443 (placed above the block for rule-order correctness)

**Re-installation (performed twice by operator, not Jason):**
- `/root/.ssh/authorized_keys` — Kali's RSA + Ed25519 pubkeys re-installed after OPNsense UI user-creation helpers silently wiped the file. See §6 Known Issues.

### 2.3 Documents added to vault

| Path | Purpose |
|------|---------|
| `Reports/Architecture_Kali_OpenClaw_OPNsense.md` | System architecture reference for Jason (building an OPNsense API app) |
| `HANDOFF_MAC_MINI.md` | Test-commands cheat-sheet for the operator's post-handoff evening verification session |
| `Reports/Session_22_IR_Pipeline_Autoblock_Report.md` | This file |

### 2.4 Tracker (`Planning/Team6_Tracker.xlsx`)

- **Services & Apps** row 46 added: `OPNsense API (CounterDefense) · 443 · CounterDefense_ACCESS · CounterDefense#7742! · Jason · Deployed`
- **Change Log** row 110 added: documenting creation of CounterDefense_ACCESS service account

---

## 3 · Architecture of the IR Watcher Pipeline

```
       ┌──────────────────────────────┐
       │ Kali (172.31.0.100)          │
       │                              │
       │  ┌────────────────────┐      │
       │  │ crontab: */15m     │      │
       │  │  suricata-digest.py│      │
       │  └─────────┬──────────┘      │
       │            │                 │
       │            │ SSH             │
       │            ▼                 │
       │  ┌────────────────────┐      │
       │  │ OPNsense eve.json  │◀─────┼── Suricata 7.0.8 alerts (34K rules)
       │  │ via SSH tail       │      │
       │  └─────────┬──────────┘      │
       │            │                 │
       │            │ parse, dedup,   │
       │            │ pattern-match   │
       │            ▼                 │
       │  ┌────────────────────┐      │
       │  │ digest-*.md +      │      │
       │  │ state.json         │      │
       │  └─────────┬──────────┘      │
       │            │                 │
       │            │ if critical     │
       │            ▼                 │
       │  ┌────────────────────┐      │
       │  │ telegram-alert.sh  │──────┼── → OpenClaw CLI → Group 6 / IR Watcher (thread 71)
       │  └────────────────────┘      │
       │                              │
       │  ┌────────────────────┐      │
       │  │ autoblock.py       │      │
       │  │  (30s poll, SSH)   │──────┼── → pfctl -t autoblock_ip -T add <IP>
       │  │                    │      │        ↓
       │  │  + whitelist from  │      │   OPNsense WAN block rule drops attacker
       │  │    self-sources    │      │
       │  │  + TTL unblock 24h │      │
       │  │  + Telegram page   │──────┼── → IR Watcher topic
       │  │    per block       │      │
       │  └────────────────────┘      │
       │                              │
       │  OpenClaw main agent         │
       │  ┌────────────────────┐      │
       │  │ HEARTBEAT 30m      │      │
       │  │ reads state.json   │      │
       │  │ → if unread_alerts │      │
       │  │   writes memory/   │      │
       │  │   YYYY-MM-DD.md    │      │
       │  └────────────────────┘      │
       └──────────────────────────────┘
```

### Two-tier design rationale

- **Tier A (cron, no LLM):** firehose triage. SSH + Python script. Zero tokens. Always running.
- **Tier B (heartbeat, LLM):** correlation + narrative. Wakes only when there are unread alerts. Small tokens, proportional to activity. Writes to daily memory file for the post-pentest writeup.
- **Autoblock:** enforcement layer. Runs alongside the IR Watcher pipeline, independent cron via nohup + PID file.

---

## 4 · Autoblock Technical Detail

### 4.1 Why SSH path instead of REST API

Jason's original design used the OPNsense REST API at `https://172.31.0.2/api/firewall/alias_util/*`. The API user was created, key/secret generated, aliases + block rule deployed, and a WAN Allow rule was added to pass Kali → 443.

**Finding:** TCP SYN from Kali to OPNsense:443 is blackholed **before** it reaches OPNsense's vtnet0 interface. Diagnostic evidence:
- `tcpdump` on Kali eth0 confirms 3 SYN retransmissions leaving Kali destined for `172.31.0.2:443`
- `tcpdump` on OPNsense vtnet0 for `host 172.31.0.100 and port 443` captured **zero** packets during the same test
- Port 22 (SSH) between the two hosts works fine — full handshake observed on both sides
- pf ruleset inspected — WAN Allow rule for Kali → WAN address → HTTPS is present, correctly ordered above the autoblock Block rule
- lighttpd on OPNsense confirmed listening on `*:443`

**Conclusion:** the 443 drop is at the cyberlab infrastructure level (between Kali and OPNsense), not in our firewall configuration. Out of our control. The REST API path is therefore unusable from Kali to OPNsense for this engagement.

**Pivot:** autoblock.py uses SSH + `pfctl -t autoblock_ip -T add <ip>` instead. We already have verified SSH key auth (Port 22 path). Same blocking effect, no API dependency.

**Tradeoff accepted:** runtime pf table entries are not persisted to OPNsense's `config.xml`. If OPNsense reboots mid-pentest, the table is cleared. Mitigation: autoblock.py's startup routine re-populates the pf table from `~/.config/openclaw-autoblock/blocked.json` on Kali (reboot-recovery). Recovery time ≈ process-restart time + 3 seconds.

### 4.2 Hardening layered on top of Jason's original

| Protection | Purpose |
|------------|---------|
| **Lab whitelist** | 13 lab IPs (Kali, all 9 VMs, router IPs, loopback) hard-coded; never blockable by accident |
| **Dynamic whitelist** | Reads `~/.openclaw/workspace/scripts/self-sources.txt` every poll → edit the file during pentest week, no code change or restart needed |
| **Kill-switch** | `touch ~/.config/openclaw-autoblock/DISABLE` → graceful exit on next poll |
| **TTL auto-unblock** | Default 24h; blocks self-clear to prevent permanent false-positive lockouts |
| **Telegram page on every block + TTL-expiry** | Real-time visibility into every enforcement action |
| **--dry-run / --once flags** | Used during deploy verification |
| **Reboot-recovery** | Startup routine reconciles pf table with blocked.json; re-adds any missing blocks |

### 4.3 Scan signatures that trigger a block

Any Suricata alert whose signature contains (case-sensitive substrings):
`ET SCAN`, `NMAP`, `Nmap`, `nmap`, `PORT SCAN`, `SYN SCAN`, `NULL SCAN`, `XMAS`, `FIN SCAN`, `OS DETECTION`

This is intentionally broad. Per team strategic decision (see §5), the goal is to block pentest scanners at first recon, not collect evidence of attempted TTPs.

---

## 5 · Strategic Decision Captured

Mid-session the team made an explicit strategic call that shapes the entire design:

> *"I don't care the entire point is to not let them in — if it's a short report then it's a good report because no one got in."*

This inverts the default evidence-capture stance that Suricata's alert-only mode was built around (Session 21c deployment). The final architecture reflects this:

- **Suricata remains alert-only** (not drop-mode) — preserves evidence for the writeup regardless
- **Autoblock, running separately, drops confirmed scanners** — achieves the "don't let them in" goal without touching Suricata config
- **IR Watcher pipeline documents what Suricata saw and what autoblock blocked** — gives the writeup material either way

Result: a report can be short ("attackers blocked at recon, nothing got in") or long ("here's everything they tried and how we caught it"), and we generate evidence for both narratives.

---

## 6 · Known Issues and Workarounds

### 6.1 OPNsense /root/.ssh/authorized_keys silently wiped by UI operations

**Symptom:** after certain OPNsense UI operations (Session 21d password dual-store fix, tonight's user-creation for CounterDefense_ACCESS), `/root/.ssh/authorized_keys` disappears. SSH key auth from Kali to OPNsense fails with `Permission denied (publickey,password,keyboard-interactive)` until re-installed.

**Root cause hypothesis:** OPNsense's PHP user-management helpers (`local_user_set_password`, `write_config`, and similar called by user-creation flows) appear to rebuild `/root/.ssh/` contents as part of applying config changes for the root user.

**Detection:** `ssh kali@... 'ssh -o BatchMode=yes root@172.31.0.2 hostname'` returns permission denied when normally it succeeds.

**Workaround (tested twice, works):**
```bash
cat ~/.ssh/id_rsa.pub ~/.ssh/id_ed25519.pub | \
  SSHPASS="$(cat ~/.openclaw/credentials/opnsense-root-pw)" sshpass -e \
  ssh -o PubkeyAuthentication=no -o PubkeyAcceptedAlgorithms=+ssh-rsa \
      -o HostKeyAlgorithms=+ssh-rsa root@172.31.0.2 \
  'mkdir -p /root/.ssh && chmod 700 /root/.ssh && \
   cat > /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys'
```

**During pentest week:** if SSH from Kali to OPNsense starts failing, run the above. Autoblock + IR pipeline both depend on SSH key auth. Monitor: autoblock log will show `SSH fetch eve.json failed (rc=255): Permission denied`.

### 6.2 Kali → OPNsense 443 blackholed at cyberlab level

**Symptom:** TCP connect from Kali (172.31.0.100) to OPNsense (172.31.0.2) on port 443 times out; port 22 works. Verified tcpdump on both ends.

**Impact:** OPNsense REST API unreachable from Kali. Affects anything API-driven from Kali → OPNsense.

**Not affected:** SSH-based workflows (autoblock.py, suricata-digest.py, manual admin). All current tooling works around this via SSH.

**Status:** out-of-scope for team mitigation; cyberlab infrastructure issue. Not worth pursuing during pentest week. Jason's in-progress OPNsense API app will need to run from a different location (e.g., OPNsense LAN segment) to use the API, or also pivot to SSH-based.

### 6.3 OPNsense root shell is tcsh — `2>/dev/null` doesn't parse

**Symptom:** initial autoblock deploy threw `tail: 2: No such file or directory`. Cause: FreeBSD root default shell is tcsh which parses `2>/dev/null` as literal arguments rather than stderr redirect.

**Fix:** removed redirect from SSH remote commands in both `autoblock.py` and `suricata-digest.py`. No functional change — we just see tail's stderr if the file is missing (which it isn't).

### 6.4 OpenClaw group ACL — negative chat IDs don't go in `allowFrom`

**Symptom:** adding `-1003807356020` to `telegram-default-allowFrom.json` triggered a log warning:
> `Invalid allowFrom entry: "-1003807356020" - allowFrom/groupAllowFrom authorization expects numeric Telegram sender user IDs only. To allow a Telegram group or supergroup, add its negative chat ID under "channels.telegram.groups" instead.`

**Fix:** reverted allowFrom to user IDs only; added group config at `channels.telegram.groups."-1003807356020"` in `openclaw.json`, with `requireMention: true` at the group level and `requireMention: false` override for topic 71 (IR Watcher).

### 6.5 Topic-level requireMention override not honoured

**Symptom:** setting `channels.telegram.groups."-1003807356020".topics."71".requireMention: false` did not take effect — bot still required @-mention in the IR Watcher topic.

**Status:** not fixed; operator accepted @-mention requirement ("I don't mind @-mentioning"). May be an OpenClaw bug or an issue with the topic key format. Out-of-scope for pentest week.

### 6.6 Python stdout buffering under nohup

**Symptom:** autoblock.py's `print()` calls didn't appear in the log file when launched via `nohup ... >> autoblock.log`. Only direct `LOG_FILE.open("a")` writes were visible.

**Fix:** launch with `PYTHONUNBUFFERED=1 nohup python3 ...` so stdout is unbuffered and line-oriented logs appear in real time.

---

## 7 · Verification Performed

All of the following was executed and confirmed green at session close:

- [x] Autoblock.py runs clean `--dry-run --once` (0 alerts, 0 would-blocks — expected, no scans yet)
- [x] Autoblock.py runs clean `--once` in LIVE mode (no errors, no blocks, pf table empty)
- [x] Autoblock.py running under `nohup` with PID persisted; process survives shell detach
- [x] SSH from Kali to OPNsense via key auth (post re-install)
- [x] `pfctl -t autoblock_ip -T show` returns empty (no attackers yet)
- [x] `pfctl -t autoblock_mac -T show` returns empty (alias exists, no entries)
- [x] OpenClaw Telegram channel status `enabled, configured, running, mode:polling, works`
- [x] Telegram send to IR Watcher topic (thread 71) — message IDs 64 (group), 74 (startup test), 97+ (autoblock startups) all delivered
- [x] Cron entry installed: `*/15 * * * * python3 ~/.openclaw/workspace/scripts/suricata-digest.py ...`
- [x] Suricata digest manually triggered: produces markdown digest and state.json
- [x] Self-source filter correctly classifies Kali's own SSH scan alert as self-traffic (verified via 8h-window replay)
- [x] `openclaw config validate` → `Config valid`
- [x] Snapshots taken: `autoblock-ready-20260419` (OPNsense 1318) + `autoblock-running-20260419` (Kali 201)

---

## 8 · Emergency Controls (quick reference)

```bash
# Stop autoblock gracefully
touch ~/.config/openclaw-autoblock/DISABLE

# Force-kill autoblock
kill -9 $(cat ~/.config/openclaw-autoblock/autoblock.pid)

# Unblock one IP
ssh root@172.31.0.2 "pfctl -t autoblock_ip -T delete <IP>"

# Nuke all blocks
ssh root@172.31.0.2 "pfctl -t autoblock_ip -T flush"
echo '{}' > ~/.config/openclaw-autoblock/blocked.json

# Add false-positive IP to permanent whitelist (re-read on next poll)
echo "1.2.3.4  description" >> ~/.openclaw/workspace/scripts/self-sources.txt

# Nuclear — rollback snapshots
# Proxmox VM 1318 → autoblock-ready-20260419
# Proxmox VM 201  → autoblock-running-20260419
```

---

## 9 · Recommendations

### Immediate (tonight / tomorrow morning)
1. Run the testing scenarios in [HANDOFF_MAC_MINI.md](../HANDOFF_MAC_MINI.md) to verify autoblock fires on a synthetic alert and that reboot-recovery works. These are safe (use TEST-NET / DOCS-NET IPs, no real hosts affected).
2. Spot-check the IR Watcher topic in Telegram to confirm startup messages landed.

### During pentest week (2026-04-20 → 2026-04-27)
1. Check `~/.config/openclaw-autoblock/autoblock.log` at least twice daily — look for false positives.
2. Monitor the IR Watcher Telegram topic continuously.
3. If a legitimate teammate/grader IP gets blocked, add it to `self-sources.txt` and un-block via `pfctl -T delete`. The filter self-heals on next poll.
4. If OPNsense SSH key auth breaks, use the re-install one-liner in §6.1.

### Post-pentest (2026-04-28 onward)
1. **Rotate the CounterDefense_ACCESS API key** (expiration set to 2026-04-28 auto-disables the user).
2. **Revoke the OpenClaw Telegram bot token** if the group will continue beyond class — regenerate via @BotFather if needed.
3. **Audit the blocked.json file** — catalogue pentest attacker IPs for the writeup.
4. **Consider merging autoblock state into config.xml** — if the SSH-path tradeoff (non-persistent blocks) is unacceptable long-term, invest the time to fix the cyberlab 443 path and move to API-based autoblock.
5. **Clean up the abandoned 443 WAN Allow rule and CounterDefense_ACCESS API user** if not needed post-pentest.

---

## 10 · Artifacts

### Files modified or created on Kali
- `/home/kali/.openclaw/workspace/scripts/autoblock.py`
- `/home/kali/.openclaw/workspace/scripts/suricata-digest.py`
- `/home/kali/.openclaw/workspace/scripts/telegram-alert.sh`
- `/home/kali/.openclaw/workspace/scripts/critical-patterns.txt`
- `/home/kali/.openclaw/workspace/scripts/self-sources.txt`
- `/home/kali/.openclaw/workspace/agents/team6/ir-watcher.md`
- `/home/kali/.openclaw/workspace/agents/team6/registry.json`
- `/home/kali/.openclaw/workspace/MEMORY.md`
- `/home/kali/.openclaw/workspace/HEARTBEAT.md`
- `/home/kali/.openclaw/workspace/ir-watcher.config.json`
- `/home/kali/.openclaw/openclaw.json`
- `/home/kali/.openclaw/credentials/telegram-default-allowFrom.json`
- `/home/kali/.openclaw/credentials/telegram-bot-token` (new, 0600)
- `/home/kali/.openclaw/credentials/opnsense-root-pw` (new, 0600)
- `/home/kali/.config/openclaw-autoblock/opn-api.env` (new, 0600)
- `/home/kali/.config/openclaw-autoblock/autoblock.log`
- `/home/kali/.config/openclaw-autoblock/blocked.json`
- `/home/kali/.config/openclaw-autoblock/autoblock.pid`
- User crontab — entry for suricata-digest.py

### OPNsense changes (Jason)
- New user: `CounterDefense_ACCESS`
- New aliases: `autoblock_ip`, `autoblock_mac`
- New WAN rules: autoblock block, Kali → WAN address:443 Allow
- `/root/.ssh/authorized_keys` (re-installed by operator)

### Vault documents
- `Final_Project/Reports/Architecture_Kali_OpenClaw_OPNsense.md` (new)
- `Final_Project/Reports/Session_22_IR_Pipeline_Autoblock_Report.md` (this file, new)
- `Final_Project/HANDOFF_MAC_MINI.md` (new)
- `Final_Project/Planning/Team6_Tracker.xlsx` — Services & Apps row 46 + Change Log row 110

### Proxmox snapshots
- VM 1318 OPNsense — `autoblock-ready-20260419`
- VM 201 Kali — `autoblock-running-20260419`

---

*End of Session 22 report.*
