# 🐕 Rent a Dog — Enterprise Network Project

> **IST 4910 · Enterprise System Administration · Spring 2026**
> California State University, San Bernardino · Dr. Nestler
>
> A fully built, hardened, monitored, and defended 9-VM enterprise network — with AI-powered incident response at its core.

[![Built with AI](https://img.shields.io/badge/AI--Powered-Claude%20%2B%20OpenClaw-blueviolet?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0id2hpdGUiPjxwYXRoIGQ9Ik0xMiAyQzYuNDggMiAyIDYuNDggMiAxMnM0LjQ4IDEwIDEwIDEwIDEwLTQuNDggMTAtMTBTMTcuNTIgMiAxMiAyem0wIDE4Yy00LjQyIDAtOC0zLjU4LTgtOHMzLjU4LTggOC04IDggMy41OCA4IDgtMy41OCA4LTggOHoiLz48L3N2Zz4=)](https://github.com/jruggles656)
[![VMs](https://img.shields.io/badge/VMs-9%20Nodes-green?style=for-the-badge)]()
[![Pentest](https://img.shields.io/badge/Pentest%20Week-Survived-success?style=for-the-badge)]()

---

## 👥 Built by Team 6

<table align="center">
  <tr>
    <td align="center" width="170">
      <a href="https://github.com/jruggles656">
        <img src="https://github.com/jruggles656.png" width="110" alt="James Ruggles" />
        <br />
        <b>James Ruggles</b>
      </a>
      <br />
      <sub>🔧 Web + AI Infra<br/>Security Lead</sub>
    </td>
    <td align="center" width="170">
      <a href="https://github.com/jcortt">
        <img src="https://github.com/jcortt.png" width="110" alt="Jason Cortez-Robles" />
        <br />
        <b>Jason Cortez-Robles</b>
      </a>
      <br />
      <sub>🌐 Network +<br/>Firewall Admin</sub>
    </td>
    <td align="center" width="170">
      <!-- TODO: replace src with https://github.com/USERNAME.png and wrap in <a href="https://github.com/USERNAME"> -->
      <img src="https://ui-avatars.com/api/?name=Oscar+Ponce&size=220&background=30363d&color=e6edf3&bold=true&font-size=0.4" width="110" alt="Oscar Ponce" />
      <br />
      <b>Oscar Ponce</b>
      <br />
      <sub>🗄️ Database +<br/>Linux Admin</sub>
    </td>
    <td align="center" width="170">
      <!-- TODO: replace src with https://github.com/USERNAME.png and wrap in <a href="https://github.com/USERNAME"> -->
      <img src="https://ui-avatars.com/api/?name=Allegra+Ramirez&size=220&background=30363d&color=e6edf3&bold=true&font-size=0.4" width="110" alt="Allegra Ramirez" />
      <br />
      <b>Allegra Ramirez</b>
      <br />
      <sub>🏛️ Active Directory<br/>+ DNS Admin</sub>
    </td>
  </tr>
</table>

---

## 📋 Table of Contents

- [🚀 Quick Start](#-quick-start)
- [🔭 Project Overview](#-project-overview)
- [🌐 Network Architecture](#-network-architecture)
- [🖥️ VM Inventory](#️-vm-inventory)
- [🛡️ Security Posture](#️-security-posture)
- [⚔️ Pentest Week](#️-pentest-week)
- [🤖 AI Integration — The Main Event](#-ai-integration--the-main-event)
- [📂 Repository Structure](#-repository-structure)
- [📊 Project Timeline](#-project-timeline)
- [🏆 Key Achievements](#-key-achievements)

---

## 🚀 Quick Start

This repo is the **archive** of a finished class project — the network it documents has been torn down. You can't `docker-compose up` it. What you *can* do:

**Read the story** (recommended path)
1. Skim [Project Overview](#-project-overview) for the what and why
2. See [Network Architecture](#-network-architecture) for the topology
3. Jump to [AI Integration — The Main Event](#-ai-integration--the-main-event) for the interesting bit (autoblock, OpenClaw, agent personas)
4. Read [Pentest Week](#-pentest-week) and the [pentest findings](pentest/findings/) for the defense-under-fire writeups

**Browse the code**
- [`website/`](website/) — the PHP e-commerce app (vanilla PHP + PostgreSQL)
- [`scripts/`](scripts/) — automation: hardening, monitoring, DB backups, security
- [`ai-integration/`](ai-integration/) — OpenClaw config, agent personas, autoblock pipeline
- [`pentest/findings/`](pentest/findings/) — 21 vulnerability writeups from pentest week

**Run the website locally** (if you really want to)
```bash
cd website
# requires PHP 8+, PostgreSQL, and the schema in ../database/rentadog_setup.sql
php -S localhost:8000
```

**A note on credentials:** This was a teaching lab. Passwords visible in `database/`, `network/`, and `scripts/` were the actual lab credentials — left intentionally so the repo accurately documents what was running. The lab is torn down; nothing here is reachable.

---

## 🔭 Project Overview

This project simulates a real-world enterprise environment for **Rent a Dog**, a fictional e-commerce business offering dog breed rentals and experience packages. Over the course of Spring 2026, our team of four built, configured, hardened, monitored, and ultimately defended a complete enterprise network from live adversarial penetration testing.

**What makes this project different:** We leaned heavily into AI tooling — not just for code generation, but as **operational infrastructure**. An AI agent framework ([OpenClaw](https://github.com/anthropics/openclaw)) ran on our security workstation during pentest week, providing real-time Suricata alert analysis, automated IP blocking, and team-wide Telegram notifications. Every team member had a dedicated AI agent persona. This wasn't AI as a novelty — it was AI as a force multiplier for a 4-person team defending a 9-node network.

### 🏢 The Business

**Rent a Dog** is a fictional e-commerce platform where customers can:
- Browse available dog breeds organized by rental tier (Standard, Premium, Luxury)
- Book experience packages (Dog Park Day, Beach Adventure, Hiking Buddy)
- Complete purchases through a shopping cart and checkout flow
- Contact support via an AI-powered chat widget ("Bark Bot")

### 🎓 Course Context

IST 4910 is a capstone-level Enterprise System Administration course. Each team receives a Proxmox cluster allocation on CSUSB's Cyberlab and must build a complete enterprise network from scratch — then defend it during a week-long adversarial pentest where other teams attack your infrastructure.

---

## 🌐 Network Architecture

```
                        ☁️ Internet
                           |
                    [External Switch]
                      /          \
              Router-WAN      🔒 Kali (SecurityDesktop)
            172.31.0.1        172.31.0.100
                  |             (+ Tailscale for remote access)
             [WAN Switch]
                  |
             [OPNsense Firewall]  ─────── [DMZ Switch]
            WAN: 172.31.0.2                     |
            DMZ: 10.0.1.1              +--------+--------+
            LAN: 192.168.2.1          |                  |
                  |               🌍 WebServer      🗄️ DatabaseServer
           [Firewall Switch]     10.0.1.100          10.0.1.200
                  |
             Router-LAN
           192.168.2.2 / 192.168.1.1
                  |
            [Internal Switch]
                  |
       +----------+----------+-----------+
       |          |          |           |
     💻 CD1    💻 CD2     🏛️ AD/DNS   📊 Tracker
  192.168.1.100  .51      192.168.1.5   .10 (NO VM)
```

### 🔀 Network Zones

| Zone | Subnet | Purpose |
|------|--------|---------|
| **WAN** | `172.31.0.0/24` | External-facing — Router-WAN, Kali, OPNsense WAN interface |
| **DMZ** | `10.0.1.0/24` | Isolated services — WebServer (Apache/XAMPP), DatabaseServer (PostgreSQL + Zabbix) |
| **LAN** | `192.168.1.0/24` | Internal clients — AD/DNS, ClientDesktop1 (Win11), ClientDesktop2 (RHEL) |
| **Firewall Mgmt** | `192.168.2.0/24` | OPNsense LAN ↔ Router-LAN management segment |

---

## 🖥️ VM Inventory

| VM | OS | IP(s) | Key Services | Owner |
|----|-----|-------|-------------|-------|
| **Kali** | Kali 2025.4 | `172.31.0.100` | SSH gateway, OpenClaw, Autoblock, Suricata digests, fail2ban | James |
| **Router-WAN** | VyOS 1.0.2 | `172.31.0.1` | Routing, SSH-ACL firewall | Jason |
| **Router-LAN** | VyOS 1.0.2 | `192.168.2.2` / `192.168.1.1` | Inter-zone routing, SSH-ACL firewall | Jason |
| **OPNsense** | OPNsense 25.1 | `172.31.0.2` / `10.0.1.1` / `192.168.2.1` | Firewall, NAT, Suricata IDS/IPS (34,353 rules) | Jason |
| **WebServer** | Win Server 2025 | `10.0.1.100` | Apache/XAMPP, PHP 8.2, MailEnable, Bark Bot, Rent a Dog site | James |
| **DatabaseServer** | RHEL 10 | `10.0.1.200` | PostgreSQL 17, Zabbix Server 7.0, Python automation | Oscar |
| **AD/DNS** | Win Server 2025 | `192.168.1.5` | Active Directory (`nestlerteam6.local`), DNS | Allegra |
| **ClientDesktop1** | Windows 11 | `192.168.1.100` | Domain-joined workstation, Zabbix agent | James |
| **ClientDesktop2** | RHEL 10 | `192.168.1.51` | Domain-joined (adcli+SSSD), health monitoring | Oscar |

---

## 🛡️ Security Posture

We implemented a **tiered hardening strategy** across 4 sessions, systematically closing attack surfaces based on vulnerability assessment findings.

### 🔍 Vulnerability Assessment (Session 18)

Full-scope scanning using **Nmap 7.98** (all 9 hosts, 4 zones) + **Nikto 2.5.0** (WebServer via SSH tunnel).

**Critical findings that drove hardening:**
- 🔴 VyOS routers running OpenSSH 5.5p1 (CVSS 9.8+ — EOL, unpatchable)
- 🟠 WebServer: TRACE enabled, phpMyAdmin exposed, missing security headers, directory indexing
- 🟢 OPNsense firewall effectiveness confirmed — only SSH visible from WAN zone

### 🏗️ Hardening Tiers

#### Tier 1 — Host-Level Protocol Hardening
- ❌ LLMNR disabled on AD/DNS, WebServer, CD1 (blocks Responder poisoning)
- ❌ NetBIOS over TCP/IP disabled on all Windows hosts
- ✅ SMB signing **required** on all Windows hosts (blocks NTLM relay)
- ✅ LDAP signing **enforced** on Domain Controller (blocks LDAP relay)
- 🔒 `RestrictAnonymous=2` on AD (blocks null session enumeration)

#### Tier 2 — firewalld Source Restriction (RHEL Hosts)
- 🔒 DatabaseServer: SSH only from Kali, PostgreSQL only from WebServer, Zabbix restricted
- 🔒 ClientDesktop2: SSH only from Kali, Zabbix agent only from DatabaseServer
- 🗑️ Removed broad service/port allows — rich rules with source IPs are sole enforcement

#### Tier 3 — VyOS SSH ACL (Mitigating Unpatchable CVEs)
- 🛡️ `SSH-ACL` ruleset on both routers: accept from Kali (`172.31.0.100`), drop + log everything else
- ✅ Eliminates network path to CVSS 9.8+ OpenSSH vulns from any compromised internal host

#### Tier 4 — Application & Service Hardening
- 🌐 Apache: `TraceEnable Off`, `ServerTokens Prod`, security headers (X-Frame-Options, CSP, etc.)
- 🔒 phpMyAdmin restricted to localhost (403 from non-local)
- 🐛 XSS in `experience.php` patched (`intval` wrapper)
- 🔑 Session cookies hardened (HttpOnly, Secure, SameSite)
- 🐘 PostgreSQL `listen_addresses` restricted to `localhost,10.0.1.200`
- 🚫 fail2ban on Kali SSH (maxretry 3, bantime 1hr)

### 👤 Account Security (Session 20 Cleanup)

- 🔑 All 9 VMs on **30-character strong passwords** (no defaults remain)
- 🧹 **6 unauthorized local accounts purged** from CD2 + 1 from DB
- 🗑️ Stale AD accounts (`botnet`, `jason`, `oscar`) **deleted**
- 📏 AD MinPasswordLength raised from 8 → **14** (NIST/CIS alignment)
- 🔐 Only `Administrator` in Domain Admins / Enterprise Admins / Schema Admins

### 📡 Monitoring

- 📊 **Zabbix 7.0.25** — 8/8 hosts GREEN (agents on Windows/Linux, SNMP on routers + OPNsense)
- 🔍 **Suricata IDS** — IPS mode on WAN/DMZ/LAN with **34,353 alert signatures** (12 ET Open + 3 abuse.ch rulesets)

---

## ⚔️ Pentest Week

**Duration:** April 20–27, 2026 (7 days)
**Format:** Red team (other class teams) vs. Blue team (us defending)

During pentest week, our defensive infrastructure was put to the test against live adversarial attacks from multiple teams. We documented **21 discrete findings** ranging from initial reconnaissance detection to active exploitation attempts.

### 📋 Select Findings

| # | Finding | Severity | Outcome |
|---|---------|----------|---------|
| 001 | Team 8 SYN scan detected + blocked | 🟡 Info | Autoblock triggered, traffic dropped |
| 002 | Autoblock v2 deployed mid-engagement | 🟢 Enhancement | Improved detection logic |
| 006 | Autoblock v4 — external alias cutover | 🟢 Enhancement | Moved from SSH-path to OPNsense API |
| 007 | Team 8 pivot to `.69` — auto-blocked | 🔴 Critical | Lateral movement attempt contained |
| 008 | Admin auth hardening (A1) | 🟠 High | Unauthorized access path closed |
| 013 | Bark Bot prompt injection guard (A4) | 🟠 High | AI chat widget attack vector patched |
| 014 | SSH ACL local chain gap (A5) | 🔴 Critical | Firewall rule ordering defect found + fixed |
| 019 | Day 4 DMZ pivot contained | 🔴 Critical | Attacker lateral movement stopped |
| 020 | Day 6 ARP spoof attempt — third pivot | 🔴 Critical | Network-layer attack detected |
| 021 | Day 7 LAN/DMZ scan — watcher chain reaction | 🟡 Info | Full defensive pipeline exercised |

### 🏆 Pentest Week Results

- ✅ **Zero successful compromises** of production services
- ✅ All attack attempts detected within minutes via Suricata + AI pipeline
- ✅ Automated blocking contained lateral movement attempts
- ✅ 21 findings documented with evidence and remediation steps
- ✅ System evolved through 4 autoblock versions during live engagement

---

## 🤖 AI Integration — The Main Event

> **This is what sets our project apart.** We didn't just use AI to write code — we deployed AI as live operational infrastructure that defended our network during a real adversarial engagement.

### 🧠 Philosophy

Most student projects use ChatGPT to generate boilerplate and move on. We went further:

1. **AI as a development accelerator** — Claude (Anthropic) was used extensively throughout the semester for code generation, system administration, vulnerability analysis, and documentation
2. **AI as operational infrastructure** — During pentest week, an AI agent framework ran 24/7 on our security workstation, providing automated incident response
3. **AI as a team multiplier** — Each team member had a dedicated AI agent persona that understood their role and responsibilities
4. **Full transparency** — We're documenting exactly how and where AI was used, not hiding it

### 🔧 AI Tools Used

| Tool | What It Did | Where/When |
|------|-------------|------------|
| **Claude Code** (Anthropic) | Primary AI assistant for all development, sysadmin, security analysis, documentation, and architecture decisions | Entire semester — every session |
| **Claude Opus 4.6** (via OpenRouter) | LLM backbone for OpenClaw agents — powered IR Watcher analysis, team agent personas | Pentest week (24/7) |
| **OpenClaw** | Agent framework running on Kali — managed 5+ agent personas, Telegram integration, cron-driven pipelines | Pentest week + pre-deployment |
| **Bark Bot** (custom) | AI-powered customer service chatbot on the Rent a Dog website, backed by Cyberlab GPU API (Dolphin 3) | Website feature — entire semester |

### 🏗️ Claude Code — Semester-Long AI Partnership

Claude Code (Anthropic's CLI tool) was the backbone of this entire project. Here's a non-exhaustive list of what it was used for:

#### 💻 Development
- 🌐 Built the entire Rent a Dog PHP website (pages, shopping cart, checkout flow, contact forms)
- 🐘 Designed and implemented the PostgreSQL database schema (14 tables, 57 constraints, 37 indexes)
- 🔄 Migrated database connections from `pg_connect` to PDO (prepared statements)
- 🤖 Built the Bark Bot AI chat widget (`chat.php` → Cyberlab GPU API integration)
- 📧 Configured MailEnable email server (5 accounts, SMTP/IMAP/POP3)
- 🎫 Built the internal helpdesk ticketing system

#### 🔒 Security & Hardening
- 🔍 Ran and analyzed Nmap + Nikto vulnerability scans across all 9 hosts
- 📝 Generated hardening scripts (`harden_apache.ps1`, `fix_ad_anon.ps1`, firewalld rich rules)
- 🛡️ Designed the tiered hardening strategy (Tiers 1–3) based on scan findings
- 🔐 Performed account baseline audit — found and removed 7 unauthorized local accounts
- 🔑 Rotated credentials, enforced password policies, cleaned up AD
- 🧱 Configured iptables on Kali (INPUT DROP policy + Tailscale + fail2ban)

#### 🏗️ Infrastructure & Administration
- 🖥️ Troubleshot and fixed WebServer duplicate SID issue (sysprep + domain rejoin)
- 📡 Configured Zabbix monitoring for all 8 target hosts (agents + SNMP)
- 🌐 Set up DNS zones and records for `nestlerteam6.local` and `rentadog.local`
- 🔧 Diagnosed and worked around Suricata rule download failures (upstream TLS block)
- 📦 Manually deployed 15 Suricata rulesets via SCP when OPNsense UI couldn't reach external sources
- 🔌 Configured SSH key auth across all 9 VMs (ed25519 + RSA for legacy VyOS/OPNsense)

#### 📄 Documentation
- 📋 Generated 25+ technical reports (vulnerability assessments, hardening reports, deployment guides)
- 📊 Maintained the master project tracker (9-sheet Excel workbook)
- 🗺️ Created network topology diagrams and architecture documentation
- 📖 Wrote the System Security Plan (SSP)
- 🎤 Drafted presentation scripts and QA cheat sheets

### 🤖 OpenClaw — AI Agent Framework

[OpenClaw](https://github.com/anthropics/openclaw) is an open-source AI agent framework that we deployed on our Kali security workstation. It ran as a persistent service during pentest week, providing:

#### 🎭 Agent Personas (5 Specialized AI Agents)

Each team member had a dedicated AI agent persona that understood their role:

| Agent | Role | Purpose |
|-------|------|---------|
| **james-pro** | 🔧 Web + AI Infrastructure | Full-stack development, AI integration, security hardening |
| **jason-pro** | 🌐 Network + Firewall | OPNsense configuration, routing, firewall rules |
| **oscar-pro** | 🗄️ Database + Linux | PostgreSQL, RHEL administration, Python automation |
| **allegra-pro** | 🏛️ Active Directory | AD/DNS management, domain policies, user management |
| **ir-watcher** | 🔍 SOC Analyst | Real-time Suricata alert analysis, incident correlation |

Each persona had:
- A `SOUL.md` defining personality, expertise, and communication style
- A `USER.md` mapping to the team member they supported
- Access to shared workspace memory (`MEMORY.md`) for continuity across sessions
- Telegram channel integration for team communication

#### 🚨 IR Watcher Pipeline — Real-Time Threat Detection

```
┌─────────────────────────────────────────────────────────────────┐
│  Every 15 minutes (cron)                                         │
│                                                                  │
│  suricata-digest.py                                              │
│    ├── SSH → OPNsense → pull /var/log/suricata/eve.json         │
│    ├── Parse + dedup alerts by (signature, src_ip, dst_ip)      │
│    ├── Write markdown digest → logs/suricata-digest-*.md        │
│    ├── Update state → logs/suricata-state.json                  │
│    └── Pattern match against critical-patterns.txt              │
│         └── On match → telegram-alert.sh                        │
│              └── OpenClaw CLI → Telegram "IR Watcher" topic     │
│                   └── Team 6 group chat (thread 71)             │
└─────────────────────────────────────────────────────────────────┘
```

- 📊 **1,900+ Suricata digest reports** generated over pentest week
- 🚨 Critical pattern matching against ~30 signature regexes
- 📱 Instant Telegram alerts to the entire team on critical detections
- 🧠 LLM-powered narrative analysis via OpenClaw's IR Watcher agent

#### 🚫 Autoblock — Automated Threat Response

Autoblock was our crown jewel — an AI-assisted automated IP blocking system that evolved through **4 versions** during live engagement:

```
┌─────────────────────────────────────────────────────────────────┐
│  Every 30 seconds (continuous loop)                              │
│                                                                  │
│  autoblock.py                                                    │
│    ├── SSH → OPNsense → tail eve.json for new alerts            │
│    ├── Filter: match "ET SCAN" + similar attack signatures      │
│    ├── Exclude: self-sources.txt (Kali, teammates, prof)        │
│    ├── SSH → OPNsense → pfctl -t autoblock_ip -T add <IP>      │
│    ├── Send Telegram alert → IR Watcher topic                   │
│    ├── Log → autoblock.log                                      │
│    ├── Persist → blocked.json (survives reboots)                │
│    └── TTL auto-expiry (configurable)                           │
└─────────────────────────────────────────────────────────────────┘
```

**Evolution during pentest week:**

| Version | What Changed | Why |
|---------|-------------|-----|
| **v1** | Initial SSH-path implementation | Pre-pentest deployment |
| **v2** | Improved detection logic, better signature matching | Day 1 — first real scans detected |
| **v3** | Timezone fixes, reconciliation for pf table drift | Day 2 — edge cases in production |
| **v4** | Cutover to OPNsense external alias API | Day 3 — more reliable than SSH+pfctl |

**Results:**
- 🚫 Multiple attacker IPs automatically blocked within seconds of detection
- 🔄 Lateral movement attempts (Team 8 pivoting to new IPs) caught and blocked automatically
- 📱 Every block event sent instant Telegram notification to the team
- 🔄 Reboot recovery — `blocked.json` state restored automatically on restart

#### 🐕 Bark Bot — AI Customer Service Widget

A custom-built AI chatbot embedded in the Rent a Dog website:

- 💬 Frontend: JavaScript chat widget with typing indicators, message history
- ⚙️ Backend: `chat.php` → Cyberlab GPU API (Dolphin 3 model)
- 🛡️ Security: Prompt injection guards added during pentest week (Finding A4)
- 👨‍💼 Admin dashboard at `/pages/admin_contacts.php` for reviewing conversations

#### 🔍 Additional AI-Powered Watchers

During pentest week, we deployed additional monitoring scripts:

| Watcher | Frequency | Purpose |
|---------|-----------|---------|
| `honeypot_watcher.sh` | Every 1 min | Monitor WebServer honeypot capture log |
| `dmz_ip_watcher.sh` | Every 1 min | Detect new/unauthorized IPs appearing in DMZ |
| `mac_watcher.sh` | On-demand | ARP table monitoring for MAC spoofing detection |

### 📊 AI Usage Statistics

| Metric | Value |
|--------|-------|
| Claude Code sessions across semester | 20+ major sessions |
| Technical reports generated with AI | 25+ documents |
| Lines of code written/reviewed with AI | Thousands (PHP, Python, Bash, PowerShell) |
| OpenClaw agents deployed | 5 personas + IR Watcher |
| Suricata digests generated by AI pipeline | 1,900+ |
| Autoblock versions iterated during live engagement | 4 |
| Pentest findings documented with AI assistance | 21 |
| Telegram alerts sent via AI pipeline | Hundreds |

### 💡 Lessons Learned About AI in Security Operations

1. **AI is a force multiplier, not a replacement** — A 4-person team defended a 9-node network against multiple attack teams. Without AI-powered automation, we couldn't have maintained 24/7 awareness.

2. **Iterate in production** — Autoblock went through 4 versions during a live engagement. The AI helped us rapidly prototype, test, and deploy fixes while under active attack.

3. **Transparency matters** — We're documenting every use of AI because hiding it would be dishonest. AI made us more effective, and that's worth showcasing.

4. **AI needs guardrails** — Bark Bot got prompt-injected during pentest week (Finding A4). We caught it, patched it, and documented it. AI tools need the same hardening as any other attack surface.

5. **Agent personas are surprisingly useful** — Having role-specific AI agents meant each team member could get contextually-aware help without re-explaining the entire project every time.

---

## 📂 Repository Structure

```
rent-a-dog-enterprise-network/
├── 📄 README.md                          ← You are here
│
├── 🌐 website/                           ← Rent a Dog PHP application
│   ├── index.php                         ← Homepage
│   ├── pages/                            ← All site pages (breeds, checkout, contact, etc.)
│   ├── agents/                           ← Bark Bot customer service AI
│   ├── api/                              ← Backend API endpoints
│   ├── css/                              ← Stylesheets
│   ├── js/                               ← Frontend JavaScript
│   ├── images/                           ← Site assets
│   └── includes/                         ← Shared PHP includes
│
├── 🗄️ database/                          ← Database schema and data
│   ├── rentadog_setup.sql                ← Full PostgreSQL schema (14 tables)
│   └── *.xlsx                            ← Data templates
│
├── 🔧 scripts/                           ← All automation scripts
│   ├── kali-security/                    ← Autoblock, Suricata digest, Telegram alerts
│   ├── db-automation/                    ← Oscar's DB backup, login monitor, AI workflow
│   ├── cd2-monitoring/                   ← Network health check script
│   ├── hardening/                        ← Apache hardening, AD anonymous enum fix
│   └── admin-diagnostics/               ← Network probes, status checks, troubleshooting
│
├── 🌐 network/                           ← Network topology diagrams
│   ├── layout.png                        ← Network diagram
│   └── SSH_Quick_Reference.*             ← SSH connection guide
│
├── 🤖 ai-integration/                    ← 🌟 AI/ML infrastructure
│   ├── openclaw-config/                  ← OpenClaw framework configuration
│   ├── agent-personas/                   ← All 5 agent persona definitions
│   ├── autoblock/                        ← Autoblock state, logs, config
│   ├── ir-pipeline/                      ← IR Watcher pipeline config + patterns
│   └── bark-bot/                         ← Bark Bot chat widget documentation
│
├── ⚔️ pentest/                           ← Pentest week artifacts
│   ├── scans/                            ← Nmap XMLs/TXTs + Nikto results (all 9 hosts)
│   ├── findings/                         ← 21 documented findings (markdown)
│   └── evidence/                         ← Supporting evidence
│
├── 📊 logs/                              ← Operational logs
│   ├── suricata-digests/                 ← 1,900+ Suricata alert digest reports
│   └── autoblock-logs/                   ← Autoblock operational logs
│
└── 📄 docs/                              ← Documentation (curated portfolio set)
    ├── reports/                           ← Architecture, OpenClaw, Suricata, BarkBot, IR pipeline
    ├── architecture/                      ← System architecture + project context
    └── deliverables/                      ← Pentest Final Report, Findings Catalog, Network Diagram
```

---

## 📊 Project Timeline

| Session | Date | Major Milestones |
|---------|------|-----------------|
| 1–15 | Jan–Mar 2026 | Network build-out, VM deployment, website development, database setup |
| 16 | Early Apr | SSH key deployment, password hardening, Zabbix agent deployment |
| 17 | Apr 12 | WebServer SID fix, full backup restore test, domain configuration |
| 18 | Apr 13 | 🔍 Full vulnerability assessment (Nmap + Nikto) + initial hardening |
| 19 | Apr 17 | 🛡️ Tier 1 + Tier 3 hardening (LLMNR/SMB/LDAP + VyOS SSH ACL) |
| 20 | Apr 19 | 👤 Account baseline cleanup (7 unauthorized accounts removed) |
| 21 | Apr 19 | 🔒 Tier 2 firewalld + Suricata rulesets (34,353 signatures) |
| 22 | Apr 19 | 🤖 OpenClaw + IR Watcher + Autoblock deployment |
| 23–29 | Apr 20–27 | ⚔️ **PENTEST WEEK** — 21 findings, 4 autoblock versions, zero compromises |
| 30 | Apr 28+ | 📄 Final documentation, SSP, presentation prep |

---

## 🏆 Key Achievements

- ✅ **9-VM enterprise network** built from scratch on Proxmox
- ✅ **Zero successful compromises** during 7-day adversarial pentest
- ✅ **34,353 Suricata IDS signatures** deployed (manual workaround for upstream TLS block)
- ✅ **AI-powered automated incident response** running 24/7 during pentest week
- ✅ **4 iterations of autoblock** developed and deployed during live engagement
- ✅ **5 AI agent personas** supporting team operations
- ✅ **1,900+ automated security digests** generated
- ✅ **21 pentest findings** documented with evidence and remediation
- ✅ **25+ technical reports** produced
- ✅ **Full-stack e-commerce site** with AI chatbot, email server, and helpdesk

---

## ⚖️ License

This project was created for educational purposes as part of IST 4910 at CSUSB. All code and documentation are shared for portfolio and educational reference.

---

<p align="center">
  <b>🐕 Rent a Dog — Built by Team 6, Powered by AI, Defended Under Fire 🔥</b>
</p>
