# Architecture — How Kali + OpenClaw Connect to the Team 6 System

**Author:** James
**Audience:** Jason (planning an OPNsense API app) — also useful as general reference
**Last updated:** 2026-04-19 (pre-pentest-week)

This doc explains how the Kali box and the OpenClaw agent framework are wired into the Team 6 enterprise network, and where the integration points are if you want to build something that talks to the infrastructure (e.g. an OPNsense API app).

Nothing sensitive is printed here — all real credentials live in `~/.openclaw/credentials/` on Kali or in `Team6_Tracker.xlsx`. This doc describes **how things connect**, not **what the secrets are**.

---

## 1. Big picture in one diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│  Kali (SecurityDesktop)  172.31.0.100 (WAN zone)                         │
│  ─ OS: Kali GNU/Linux 2025.4, kernel 6.18.9                              │
│  ─ Admin reach: SSH keys to all 8 other VMs (ed25519 + RSA for legacy)   │
│  ─ iptables INPUT DROP, fail2ban sshd jail                               │
│  ─ Also: Tailscale interface for external James-laptop → Kali access     │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │  OpenClaw (npm-installed agent framework, runs as kali user)       │  │
│  │  ─ Gateway daemon on :18789 (loopback), systemd user service       │  │
│  │  ─ 5 agents: main + james-pro + jason-pro + oscar-pro +            │  │
│  │              allegra-pro + ir-watcher                              │  │
│  │  ─ Providers: OpenRouter (Claude Opus 4.6) + OpenAI Codex          │  │
│  │  ─ Channels: Telegram (group + per-member topics)                  │  │
│  │  ─ Workspace: ~/.openclaw/workspace/ (MEMORY.md, HEARTBEAT.md,     │  │
│  │                persona files, logs, scripts)                       │  │
│  └────────────────────────────────────────────────────────────────────┘  │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │  Cron — suricata-digest.py every 15 min                            │  │
│  │  ─ SSH to OPNsense → pulls /var/log/suricata/eve.json window       │  │
│  │  ─ Parses alerts, dedups, writes markdown digest + JSON state      │  │
│  │  ─ Pattern-matches for critical signatures                         │  │
│  │  ─ On match → calls telegram-alert.sh → OpenClaw CLI send          │  │
│  │                → "IR Watcher" topic in Team 6 Telegram group       │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
          │ SSH (key auth where possible)
          ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  8 other lab VMs                                                         │
│   · Router-WAN, Router-LAN, OPNsense (WAN/DMZ/LAN)                       │
│   · WebServer, DatabaseServer (DMZ)                                      │
│   · AD/DNS, ClientDesktop1, ClientDesktop2 (LAN)                         │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Kali — the single admin source

### 2.1 Identity

| Attribute | Value |
|---|---|
| Hostname | `kali-01-test` |
| Internal IP | `172.31.0.100` (WAN zone, cyberlab) |
| Remote-access IP | Tailscale (separate interface — not committed to docs) |
| OS | Kali GNU/Linux 2025.4 (kernel 6.18.9) |
| Shell user | `kali` |

### 2.2 Why Kali has admin reach and no one else does

All hardening across the 9 VMs assumes **Kali is the only box that can SSH to any other VM**. This is enforced in multiple layers:

- **VyOS routers** (Router-WAN / Router-LAN): `firewall name SSH-ACL` — rule 10 accepts SSH from `172.31.0.100`, rule 20 drops + logs everything else. Non-Kali sources see `tcp/22` as filtered.
- **firewalld on DB + CD2** (RHEL hosts): rich rules source-restrict SSH to `172.31.0.100` only.
- **Windows hosts (WebServer, AD, CD1)**: OpenSSH server configured to only accept Kali's pubkey (per-user `administrators_authorized_keys`).
- **OPNsense**: `/root/.ssh/authorized_keys` holds only Kali's pubkeys.

If your app needs to reach a target VM over SSH, the cleanest path is **from Kali**. From anywhere else on the cyberlab network SSH is filtered.

### 2.3 SSH key material

Kali has two key pairs in `~/.ssh/`:

| File | Algorithm | Used for |
|---|---|---|
| `~/.ssh/id_ed25519` | Ed25519 | WebServer, DB, AD, CD1, CD2 (modern OpenSSH) |
| `~/.ssh/id_rsa` | RSA 4096 | VyOS routers, OPNsense (legacy OpenSSH — require `PubkeyAcceptedAlgorithms +ssh-rsa`) |

`~/.ssh/config` already has the `Host 172.31.0.1/172.31.0.2/192.168.1.1` blocks with the algorithm-compat flags, so plain `ssh vyos@172.31.0.1` or `ssh root@172.31.0.2` just works.

### 2.4 Perimeter hardening on Kali itself

- `iptables` INPUT policy = DROP. Allowed: loopback, ESTABLISHED/RELATED, `tailscale0`, ICMP echo-reply. `netfilter-persistent` enabled (survives reboot — tested Session 17).
- `fail2ban` sshd jail — maxretry 3, bantime 3600s, findtime 600s.
- Credentials: `kali` user password was rotated from the default `kali/kali` in Session 19 (current value in tracker).

---

## 3. OpenClaw — the agent framework on Kali

### 3.1 What it is

OpenClaw is an npm-installed agent framework (NodeJS). It provides a **gateway daemon** that hosts **LLM-backed agents** and exposes them via:

- A terminal UI (`openclaw-tui`) — interactive chat with any agent
- A REST/WebSocket gateway on `127.0.0.1:18789` (loopback only, token-auth)
- **Channel bridges** — Telegram, Discord, Slack, Matrix, etc. (we use Telegram)
- A CLI (`openclaw ...`) — scriptable operations

Think of it as: "Claude Code for messaging platforms, plus scriptable automation."

### 3.2 Install layout

| Path | Purpose |
|---|---|
| `/home/kali/.npm-global/lib/node_modules/openclaw/` | Install root |
| `/home/kali/.npm-global/bin/openclaw` | CLI symlink |
| `~/.config/systemd/user/openclaw-gateway.service` | systemd user unit → gateway daemon |
| `~/.openclaw/` | Per-user state root (agents, workspace, credentials) |

The gateway is **running right now** as a user systemd service:

```
systemctl --user status openclaw-gateway.service
```

### 3.3 Workspace layout — where the config + behavior lives

```
~/.openclaw/
├── openclaw.json                    # master config (plugins enabled, gateway, auth)
├── credentials/
│   ├── telegram-bot-token           # 0600 — the Telegram bot API token
│   ├── opnsense-root-pw             # 0600 — OPNsense root pw (SSH fallback)
│   └── *-allowFrom.json             # Telegram/Discord ACLs
├── agents/
│   ├── main/                        # the default "chat with you" agent
│   │   └── agent/models.json        # which providers/models this agent uses
│   ├── james-pro/ jason-pro/ oscar-pro/ allegra-pro/
│   └── (all 4 orphaned currently — exist but not in active routing)
└── workspace/                       # <--- MAIN CONFIG & CONTEXT FILES
    ├── SOUL.md                      # personality, tone, boundaries (loaded each session)
    ├── AGENTS.md                    # framework instructions (heartbeats, memory, etc.)
    ├── USER.md                      # who the human is
    ├── IDENTITY.md                  # what THIS agent is ("botnet", SOC assistant)
    ├── TOOLS.md                     # environment-specific notes (SSH hosts, etc.)
    ├── MEMORY.md                    # curated persistent memory (rewritten 2026-04-19)
    ├── HEARTBEAT.md                 # what to do on each heartbeat poll (30 min default)
    ├── memory/YYYY-MM-DD.md         # daily logs written by agents
    ├── agents/team6/
    │   ├── registry.json            # 5 per-member personas + ir-watcher
    │   ├── james-pro.md             # specialty + response format for each
    │   ├── jason-pro.md
    │   ├── oscar-pro.md
    │   ├── allegra-pro.md
    │   └── ir-watcher.md            # <-- pentest-week SOC analyst persona
    ├── scripts/
    │   ├── suricata-digest.py       # Tier A cron job (no LLM)
    │   ├── telegram-alert.sh        # Wrapper → OpenClaw CLI → Telegram send
    │   ├── critical-patterns.txt    # Regex list → critical hit → page team
    │   └── self-sources.txt         # IPs to de-prioritize (e.g. Kali itself)
    ├── logs/
    │   ├── suricata-digest-*.md     # Every 15-min digest
    │   ├── suricata-state.json      # Rolling counter (unread_alerts, totals)
    │   └── cron.log                 # stdout/stderr from the cron job
    └── ir-watcher.config.json       # Group + thread + DM IDs for alerting
```

### 3.4 Agents

Registered in `workspace/agents/team6/registry.json`:

| Agent | Owner | Specialty | Telegram topic |
|---|---|---|---|
| `main` | shared | General chat with James | Topic 1 (General) |
| `james-pro` | James | Web + AI infrastructure | Topic 6 |
| `jason-pro` | Jason | Network + OPNsense + Routing | Topic 7 |
| `oscar-pro` | Oscar | PostgreSQL + Data + Python automation | Topic 8 |
| `allegra-pro` | Allegra | Security + Monitoring + Compliance docs | Topic 9 |
| `ir-watcher` | shared | Pentest IR + Suricata analysis | Topic 71 (IR Watcher) |

Each persona has its own `.md` prompt file under `agents/team6/` that gets loaded when that agent is invoked. The `ir-watcher.md` file is the longest — it bakes in the full defensive posture and response format.

### 3.5 Telegram channel

- Bot: `@Nestler_Botnet_bot` (ID `8797910326`)
- Group: "Group 6" (chat ID `-1003807356020`, `is_forum: true`)
- Bot is administrator in the group with `can_manage_topics: true`
- Token lives in `~/.openclaw/credentials/telegram-bot-token` (0600)
- OpenClaw polls for updates; can send via:
  - CLI: `openclaw message send --channel telegram --account default --target <chat_id> --thread-id <topic_id> --message "..."`
  - Direct Bot API: `curl https://api.telegram.org/bot<TOKEN>/sendMessage?chat_id=<ID>&text=...`

---

## 4. The IR Watcher pipeline (how Tier A + Tier B hang together)

### 4.1 Flow

```
 OPNsense                                 Kali                           Telegram
 /var/log/suricata/                cron every 15 min                     Group 6
 eve.json  ─────SSH──────▶  suricata-digest.py  ──if critical──▶         IR Watcher
                             │                                            topic 71
                             ├─► digest-YYYYMMDD-HHMM.md (logs/)
                             ├─► suricata-state.json (unread_alerts++)
                             └─► telegram-alert.sh ─► openclaw message send
```

### 4.2 Heartbeat (Tier B)

Every 30 min the `main` OpenClaw agent fires a heartbeat. It reads `HEARTBEAT.md`, which instructs it to:

1. Read `suricata-state.json`
2. If `unread_alerts == 0` → reply `HEARTBEAT_OK` (cheap, ~100 tokens)
3. If `> 0` → load `ir-watcher.md` persona, read latest digest, append 3-5 bullet correlation to `memory/YYYY-MM-DD.md`, decrement unread counter

Tier A = data (free, always). Tier B = narrative (tokens only when there's signal).

### 4.3 Self-source filter

`suricata-digest.py` reads `scripts/self-sources.txt` and any alert with a `src_ip` in that list:

- Goes to a "Self / admin" section in the digest (still evidence)
- Does NOT trigger Telegram paging
- Does NOT count toward `unread_alerts` (heartbeat stays silent for our own admin work)

Currently the file has `172.31.0.100  Kali (sole authorized admin source)`. Add more during pentest week if legitimate traffic trips rules.

---

## 5. OPNsense — for Jason's API app

### 5.1 OPNsense API overview

OPNsense exposes a **REST API** on the same HTTPS listener as the UI. Same cert, same port.

- **Base URL:** `https://<opn_ip>/api/` (e.g. `https://172.31.0.2/api/` from Kali or DMZ)
- **Auth:** HTTP Basic, username = API key, password = API secret (both per-user, generated in UI)
- **Content-Type:** `application/json`
- **Docs:** https://docs.opnsense.org/development/api.html
- **Format:** endpoints grouped as `/api/<module>/<controller>/<command>` — e.g. `/api/firewall/filter/searchRule`

### 5.2 Generating an API key (one-time)

UI path:
1. OPNsense UI → **System → Access → Users**
2. Edit an admin user (or create a dedicated API user — recommended for your app so you can revoke without touching root)
3. Scroll to "API keys" → **+ to add**
4. OPNsense generates a key + secret and returns a `.txt` file — **download it**, OPNsense will not show the secret again
5. The file contains:
   ```
   key=<KEYVALUE>
   secret=<SECRETVALUE>
   ```

Store those the same way we store the bot token (`~/.config/<yourapp>/opn-api.env`, 0600), not in your repo.

### 5.3 Minimal working example

From Kali (or wherever you develop):

```bash
API_KEY="..."
API_SECRET="..."
OPN_HOST="172.31.0.2"

# Read firewall filter rules
curl -sk -u "${API_KEY}:${API_SECRET}" \
  "https://${OPN_HOST}/api/firewall/filter/searchRule" | jq .
```

`-k` skips cert validation because OPNsense ships a self-signed cert. If you'd rather validate, export the OPNsense cert and use `--cacert`.

### 5.4 Endpoints likely useful to your app

Full endpoint tree: `curl -sk -u KEY:SECRET https://<opn>/api/core/menu/tree`

| Module | What you can do |
|---|---|
| `/api/firewall/filter/*` | Read/create/edit/delete filter rules, reload ruleset |
| `/api/firewall/alias/*` | Read/update aliases (IP groups, networks) |
| `/api/diagnostics/interface/*` | Interface stats, ARP table, routing table |
| `/api/ids/service/*` | Suricata status + rule management (**avoid `reconfigure`** — Session 21c caveat, will regenerate `installed_rules.yaml` and wipe hand-installed rulesets) |
| `/api/diagnostics/log/*` | System, firewall, interface logs |
| `/api/core/firmware/status` | Version / update status |
| `/api/diagnostics/traffic/top` | Traffic top talkers (good for dashboards) |
| `/api/unbound/*` | DNS (Unbound) config + cache |

### 5.5 State changes require an explicit "apply" call

OPNsense API is two-phase for most config changes:

1. **Modify** — `POST /api/firewall/filter/addRule` etc. — writes to config.xml but does NOT activate
2. **Apply** — `POST /api/firewall/filter/apply` — reloads pf with the new rules

Always follow a mutation with the matching apply call. If your app forgets, the UI will show "Undo changes / Apply changes" pending banners.

### 5.6 Other ways to talk to OPNsense (non-API)

If API doesn't cover what you need:

| Path | How | Good for |
|---|---|---|
| SSH to root@172.31.0.2 | Kali has key auth now | Arbitrary shell, pfctl, suricatasc, configctl |
| `configctl` | Local CLI on OPNsense | High-level service commands (`configctl suricata restart`) |
| `pfctl -sr` | pf CLI | Raw firewall state |
| `suricatasc` | Unix socket to Suricata | IDS stats, ruleset reload (**don't** unless you know what you're doing) |

### 5.7 Integration with OpenClaw / IR Watcher (optional)

If your app wants to **post alerts or reports** into the Team 6 Telegram group, the simplest path is to shell out to OpenClaw's CLI on Kali:

```bash
ssh kali@<kali-tailscale> \
  "/home/kali/.npm-global/bin/openclaw message send \
     --channel telegram --account default \
     --target <chat_id> --thread-id <topic_id> \
     --message 'your alert text'"
```

Use `thread-id 71` for the IR Watcher topic, or your own per-agent topic (`jason-pro` = 7).

Or skip OpenClaw and hit Telegram's Bot API directly — the bot token is in `~/.openclaw/credentials/telegram-bot-token` (chmod 600, kali user).

If your app wants to **read Suricata state** without touching OPNsense, just read `~/.openclaw/workspace/logs/suricata-state.json` on Kali — updated every 15 min. All the dedup / filter work is already done.

---

## 6. What you can build (ideas for Jason's app)

Scoped to things that play nicely with the existing setup:

1. **Firewall rule editor UI** — thin web UI that reads `firewall/filter/searchRule`, lets you toggle / edit / create rules, calls `firewall/filter/apply` after changes. Good for teammates who can't use the OPNsense UI directly (everyone but Jason, per Nestler's ACL).
2. **Zone traffic dashboard** — aggregate `diagnostics/interface/*` data across WAN/DMZ/LAN, visualize bps + packet counts. Pair with Zabbix on DB (10.0.1.200) if you want historical trends.
3. **Alert triage dashboard** — read `suricata-state.json` + recent digest files from Kali (over SSH or Tailscale), show a live view of attacker IPs + TTPs. Basically a lightweight SOC console for pentest week.
4. **Rule ACL automation** — during pentest week, programmatically suppress / unsuppress specific Suricata SIDs via `/api/ids/service/querySettings` + edits. Dangerous but useful.
5. **Health watchdog** — poll `/api/diagnostics/interface/getInterfaceStatistics`, `/api/core/firmware/status`, Suricata status, alert on degradation. Wire alerts into your own `jason-pro` topic (thread 7) so you don't spam the group.

Whatever you build, please keep the API credentials out of any git repo. If you want, I can help add a `~/.openclaw/credentials/opnsense-api-key` pattern so your app reads from the same 0600 config dir we're already using.

---

## 7. Appendix — file / config quick reference

### On Kali

| Path | Contents |
|---|---|
| `~/.ssh/config` | VyOS + OPNsense algorithm-compat entries |
| `~/.ssh/id_rsa` / `id_ed25519` | Kali's admin keys (keep these safe; losing them = no admin reach) |
| `~/.openclaw/credentials/telegram-bot-token` | Bot API token (0600) |
| `~/.openclaw/credentials/opnsense-root-pw` | OPNsense root pw (0600, SSH fallback) |
| `~/.openclaw/workspace/MEMORY.md` | Master context loaded by every OpenClaw session |
| `~/.openclaw/workspace/logs/suricata-state.json` | Rolling IR state counter |
| `~/.openclaw/workspace/scripts/suricata-digest.py` | Tier A cron job |
| `/etc/cron.d/`... or `crontab -l` | Cron entry: `*/15 * * * * suricata-digest.py` |

### On OPNsense

| Path | Contents |
|---|---|
| `/root/.ssh/authorized_keys` | Kali's RSA + Ed25519 pubkeys |
| `/var/log/suricata/eve.json` | Suricata alert firehose (JSON lines) |
| `/var/log/suricata/eve.json.0..3` | Weekly rotated alert logs |
| `/usr/local/etc/suricata/installed_rules.yaml` | Session 21c hand-installed rulesets (**do NOT let UI regenerate**) |
| `/usr/local/etc/suricata/opnsense.rules/` | 15 ruleset `.rules` files |
| `/conf/config.xml` | Master OPNsense config (users, firewall rules, etc.) |

### Tracker / master docs

| Path (in Obsidian vault) | Purpose |
|---|---|
| `Planning/Team6_Tracker.xlsx` | Master tracker — VMs, passwords, firewall, DNS, backups, change log, AD users |
| `Team6_System_Overview.docx` | Team-facing system explainer |
| `Reports/Tier2_Firewalld_Report.docx` | Session 21 firewalld rich-rules report |
| `Reports/Tier3_VyOS_SSH_ACL_Report.docx` | Session 19 VyOS SSH-ACL report |
| `Reports/Suricata_Deployment_Report.docx` | Session 21c Suricata manual-install report + caveats |
| `PROJECT_CONTEXT_SUMMARY.md` | Rolling session log through Session 21d |

---

## Questions / gotchas for Jason

- **Cert trust**: OPNsense ships a self-signed cert. Either `-k` in curl / `verify=False` in requests, or add the cert to a local trust store. Don't mix the two across environments.
- **Rate limiting**: OPNsense's API is not rate-limited per se, but aggressive polling will eat CPU on the firewall. For dashboards, 5-10 sec intervals are polite.
- **Apply-before-you-leave**: if your app makes a config change and dies before the apply call, the UI shows a pending "revert / apply" banner. Idempotent design helps.
- **Don't touch Suricata rules via the UI** (see Session 21c caveat). If you want to manage rules programmatically, hit the API's `ids/service/*` endpoints directly, or SSH + edit files + `configctl suricata restart`.
- **Ask me for credentials when you're ready** — I'll provision a dedicated API key for your app (separate from root) and drop it in a 0600 file. Don't hardcode.

If you want to pair on a design session before you start coding, ping me — happy to walk through the pieces.
