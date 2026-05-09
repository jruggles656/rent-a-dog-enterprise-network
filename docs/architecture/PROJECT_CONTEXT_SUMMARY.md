# IST 4910 — Rent a Dog — Current System State
**Last Updated:** April 19, 2026 (Session 21c — Suricata rulesets deployed via manual workaround; tracker AD Users sheet added)
**Purpose:** Quick context loading when switching sessions/devices. For team-facing docs see `Team6_System_Overview.docx`.

---

## Quick Reference

**Course:** IST 4910 - Enterprise System Administration (Dr. Nestler)
**Team 6:** James Ruggles, Jason Cortez-Robles, Oscar Ponce, Allegra Ramirez
**Cyberlab:** `team6.pve.cyberlab.csusb.edu`
**Business:** Rent a Dog (e-commerce — breed tier rentals + experience packages)
**Domain:** `nestlerteam6.local` (DC at 192.168.1.5) / `rentadog.local` (web/email)
**Kali Tailscale:** `[REDACTED-TAILSCALE]` (kali/kali) — entry point for all remote work
**Zabbix:** `http://10.0.1.200/zabbix` (Admin / [REDACTED])

---

## Network Topology (Verified Live)

```
                     Internet
                        |
                 [External Switch]
                   /          \
           Router-WAN      Kali (SecurityDesktop)
         172.31.0.1        172.31.0.100
               |             (+ Tailscale [REDACTED-TAILSCALE])
          [WAN Switch]
               |
          [OPNsense Firewall]  -------- [DMZ Switch]
         WAN: 172.31.0.2                     |
         DMZ: 10.0.1.1              +--------+--------+
         LAN: 192.168.2.1          |                  |
               |               WebServer          DatabaseServer
        [Firewall Switch]     10.0.1.100          10.0.1.200
               |
          Router-LAN
        eth4: 192.168.2.2 (firewall-facing)
        eth5: 192.168.1.1 (LAN-facing)
               |
         [Internal Switch]
               |
    +----------+----------+-----------+
    |          |          |           |
  CD1        CD2       AD/DNS     Tracker
192.168.1.100  192.168.1.51  192.168.1.5  192.168.1.10 (NO VM)
```

### Zones

| Zone | Subnet | Devices |
|------|--------|---------|
| WAN | 172.31.0.0/24 | Router-WAN (.1), Kali (.100), OPNsense WAN (.2) |
| DMZ | 10.0.1.0/24 | OPNsense DMZ (.1), WebServer (.100), DatabaseServer (.200) |
| LAN | 192.168.1.0/24 | Router-LAN (.1), CD1 (.100), CD2 (.51), AD/DNS (.5) |
| Firewall Mgmt | 192.168.2.0/24 | OPNsense LAN (.1), Router-LAN eth4 (.2) |

---

## VM Inventory (All Verified April 12, 2026)

### Router-WAN — 172.31.0.1
- **OS:** VyOS 1.0.2 (hydrogen)
- **Interfaces:** eth4 (172.31.0.1/24 WAN), eth5 (10.10.x.x maindhcp)
- **SSH:** port 22, key auth (RSA), requires `PubkeyAcceptedAlgorithms +ssh-rsa` from Kali
- **SNMP:** community `team6lab2026` (Zabbix monitored, GREEN)
- **Credentials:** vyos / 30-char 1Password (in tracker)
- **Owner:** Jason

### Router-LAN — 192.168.2.2 / 192.168.1.1
- **OS:** VyOS 1.0.2 (hydrogen)
- **Interfaces:** eth4 (192.168.2.2/24 firewall-facing), eth5 (192.168.1.1/24 LAN-facing)
- **SSH:** port 22, key auth (RSA), requires `PubkeyAcceptedAlgorithms +ssh-rsa` from Kali
- **SNMP:** community `team6lab2026` (Zabbix monitored, GREEN)
- **Credentials:** vyos / 30-char 1Password (in tracker)
- **Owner:** Jason

### OPNsense Firewall — 172.31.0.2 / 10.0.1.1 / 192.168.2.1
- **OS:** OPNsense 25.1 (FreeBSD, amd64)
- **Interfaces:** vtnet0 (WAN 172.31.0.2), vtnet1 (DMZ 10.0.1.1), vtnet2 (LAN 192.168.2.1)
- **Listening:** SSH (:22), HTTPS (:443), HTTP (:80)
- **SSH:** port 22, key auth (RSA), `openssh_enable=YES` in rc.conf (boot-persistent)
- **Suricata IDS:** v7.0.8, PID 2501, IPS mode on WAN/DMZ/LAN, eve.json logging active. No external rulesets (ET Open etc.) downloaded — only empty OPNsense.rules stub.
- **SNMP:** bsnmpd, community `team6lab2026` (Zabbix monitored, GREEN)
- **Firewall:** 10+ rules across WAN/LAN/DMZ + 2 NAT port forwards (80/443 → WebServer) + 5 Zabbix rules
- **Web GUI:** https://192.168.2.1 (root login)
- **Credentials:** root / 30-char 1Password (in tracker)
- **Owner:** Jason

### Kali (SecurityDesktop) — 172.31.0.100
- **OS:** Kali GNU/Linux 2025.4 (kernel 6.18.9)
- **IPs:** eth0 (172.31.0.100/24), tailscale0 ([REDACTED-TAILSCALE]/32)
- **iptables:** INPUT DROP policy, allow loopback + ESTABLISHED/RELATED + tailscale0 + ICMP echo-reply. `netfilter-persistent` enabled (reboot-tested Session 17).
- **SSH config:** `~/.ssh/config` has PubkeyAcceptedAlgorithms entries for VyOS + OPNsense
- **Keys:** ed25519 (modern VMs) + RSA 4096 (legacy VyOS/OPNsense)
- **Credentials:** kali / [REDACTED] (rotated Session 19 — was kali/kali)
- **Owner:** James / shared

### WebServer — 10.0.1.100
- **OS:** Windows Server 2025 (build 26100)
- **Hostname:** WEBSERVER
- **Listening ports:** 22 (SSH), 25 (SMTP), 80 (HTTP), 110 (POP3), 143 (IMAP), 443 (HTTPS), 587 (SMTP submission)
- **Services:** Apache/XAMPP (PHP 8.2.12), MailEnable Standard 10.56, OpenSSH, Zabbix Agent
- **Website:** Rent a Dog site at http://10.0.1.100 — live PDO connection to PostgreSQL
- **Email:** MailEnable — 5 accounts (james@, jason@, oscar@, allegra@, admin@rentadog.local), all password `[REDACTED]` (admin: `[REDACTED]`)
- **AI:** Bark Bot chat widget (chat.php → Cyberlab GPU API), admin dashboard at /pages/admin_contacts.php (password: `[REDACTED]`)
- **Domain:** AD-joined (sysprep + rejoin Session 17 — SID fixed, unique SID confirmed)
- **SID:** S-1-5-21-755779914-2621603520-1372550196 (regenerated via sysprep Session 17)
- **SSH:** key auth (ed25519), `administrators_authorized_keys`
- **Credentials:** Administrator / 30-char 1Password (in tracker)
- **Owner:** James

### DatabaseServer — 10.0.1.200
- **OS:** RHEL 10.0 (Coughlan), kernel 6.12.0
- **Hostname:** database
- **Listening ports:** 22 (SSH), 80 (httpd/Zabbix UI), 5432 (PostgreSQL), 10050 (Zabbix agent), 10051 (Zabbix server)
- **Services running:** postgresql-17, zabbix-server, zabbix-agent, httpd, sshd
- **PostgreSQL 17.9:** databases `rentadog` (14 tables, app user `rentadog_app`) + `zabbix` (203 tables, user `zabbix`)
- **Zabbix Server 7.0.25:** Dashboard at http://10.0.1.200/zabbix, 8/8 hosts GREEN
- **Python scripts:** script1 (backup, daily 2AM), script3 (login monitor, hourly), script4 (AI workflow, every 30min) in `/home/playerone/scripts/`
- **Domain:** AD-joined (adcli+SSSD)
- **SSH:** key auth (ed25519), password auth disabled
- **Credentials:** playerone / 30-char 1Password (in tracker)
- **Owner:** Oscar

### AD/DNS — 192.168.1.5
- **OS:** Windows Server 2025 (build 26100)
- **Hostname:** WIN-LLE76FGA4UL
- **Listening ports:** 22 (SSH), 88 (Kerberos), 389 (LDAP), 445 (SMB), 464, 593, 636 (LDAPS), 3268 (GC)
- **Roles:** Active Directory Domain Services, DNS Server
- **Domain:** `nestlerteam6.local`
- **AD accounts (Session 20 cleanup):** Administrator (enabled, 30-char pw), allegra/[REDACTED] (enabled — rotated Session 20), james/[REDACTED] (enabled Session 20 for rubric row 14 "user account" demo), guest1+guest2/[REDACTED] (enabled). botnet/jason/oscar **deleted** Session 20. Guest (builtin) disabled. krbtgt = system, never touch.
- **AD policy (Session 20):** MinPasswordLength raised 8 → **14**; Complexity on; Lockout 5 attempts / 30 min; Password history 10; Max age 90d. Neither allegra nor james is in Domain Admins / Enterprise Admins / Schema Admins — Administrator only.
- **DNS zones:** `nestlerteam6.local` (16 A records), `rentadog.local` (www + mail + MX), reverse zones for LAN + DMZ
- **Forwarders:** 8.8.8.8, 1.1.1.1
- **SSH:** key auth (ed25519), `administrators_authorized_keys`
- **Credentials:** Administrator / 30-char 1Password (in tracker)
- **Owner:** Allegra

### ClientDesktop1 — 192.168.1.100
- **OS:** Windows 11 (build 26200)
- **Hostname:** DESKTOP-HJEAFGC
- **Listening ports:** 22 (SSH), 10050 (Zabbix agent), standard Windows
- **Services:** OpenSSH (Running/Automatic), Zabbix Agent
- **Domain:** AD-joined
- **SSH:** key auth (ed25519), `administrators_authorized_keys`. sshd reinstalled Session 16 (was zombie).
- **Credentials:** Administrator / 30-char 1Password (in tracker), playerone / same credential
- **Owner:** James
- **Note:** On bridge `nest05a4` — may need to confirm with Professor Nestler if should be `nest06a4`

### ClientDesktop2 — 192.168.1.51
- **OS:** RHEL 10.0 (Coughlan)
- **Hostname:** client-linux.nestlerteam6.local
- **IP:** 192.168.1.51 (NOT .101 as some old docs said)
- **Services:** sshd, Zabbix Agent
- **Domain:** AD-joined (adcli+SSSD, realm: NESTLERTEAM6.LOCAL)
- **Python scripts:** script2 (health check, every 15min) in `/home/playerone/scripts/`
- **SSH:** key auth (ed25519), password auth disabled
- **Credentials:** playerone / 30-char 1Password (in tracker)
- **Owner:** Oscar

---

## Security Posture

**Passwords:** All 9 VMs on 30-char strong credentials. No defaults remain (Kali rotated from kali/kali Session 19).
**SSH keys:** 8/8 targets verified from Kali (ed25519 for modern, RSA for legacy VyOS/OPNsense).
**Password auth:** Disabled on DatabaseServer + CD2. Still enabled on WebServer, CD1, AD/DNS, VyOS routers, OPNsense (not critical with 30-char passwords).
**iptables (Kali):** INPUT DROP, allow loopback + ESTABLISHED + tailscale0 + ICMP. Persistent (netfilter-persistent enabled, reboot-tested).
**Suricata IDS:** Running on OPNsense, IPS mode, all interfaces. Needs ET Open rulesets downloaded.
**Zabbix monitoring:** 8/8 hosts GREEN (agents on Windows/Linux VMs, SNMP on routers + OPNsense).
**SNMP:** Community string `team6lab2026` (changed from default `public`).

### Hardening Applied (Session 18)

**WebServer Apache (10.0.1.100):** TraceEnable Off, ServerTokens Prod, ServerSignature Off, security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, CSP), X-Powered-By removed, directory indexing disabled, server-status/server-info restricted to localhost, phpMyAdmin restricted to localhost (403 from non-local), XSS in experience.php fixed (intval wrapper), session cookies hardened (HttpOnly, Secure, SameSite). Script: `harden_apache.ps1`, backups timestamped 20260413_035802.
**AD/DNS (192.168.1.5):** RestrictAnonymous registry key set to 2 — blocks null session enumeration of accounts/shares/policies. Script: `fix_ad_anon.ps1`.
**DatabaseServer (10.0.1.200):** PostgreSQL `listen_addresses` changed from `'*'` to `'localhost,10.0.1.200'` — port 5432 no longer on all interfaces. SSH hardened via `/etc/ssh/sshd_config.d/hardening.conf` (MaxAuthTries 3, LoginGraceTime 30, PermitRootLogin no, ClientAliveInterval 300, ClientAliveCountMax 2). Script: `fix_pg.sh`.
**Kali (172.31.0.100):** fail2ban installed and active with sshd jail (maxretry 3, bantime 3600s, findtime 600s).

### Tier 1 Hardening Applied (Session 19, April 17, 2026)

**AD/DNS (192.168.1.5):** LLMNR disabled (EnableMulticast=0), NetBIOS over TCP/IP disabled on both NICs (NetbiosOptions=2), LDAP signing set to require (LDAPServerIntegrity=2, full enforcement at next DC reboot). Pre-check: zero unsigned LDAP binds in prior 7 days (events 2886–2889). Transcript at C:\Team6_Hardening_20260417_211723.log.
**WebServer (10.0.1.100):** LLMNR disabled, NetBIOS disabled on both NICs, SMB signing now both Required AND Enabled (previously both False). Transcript at C:\Team6_Hardening_20260417_211732.log.
**CD1 (192.168.1.100):** LLMNR disabled, NetBIOS disabled on both NICs, SMB EnableSecuritySignature set to True (Require was already True). Transcript at C:\Team6_Hardening_20260417_211727.log.
**Post-change verified:** Website HTTP 200, DNS www.rentadog.local → 10.0.1.100, CD2 realm/SSSD still working, zero LDAP signing error events. Full report at Reports/Tier1_Hardening_Report.docx.
**Mitigates:** LLMNR/NBT-NS poisoning (Responder), NTLM relay to SMB, NTLM relay to LDAP, SMB null enumeration (combined with Session 18 RestrictAnonymous=2).

### Tier 3 Hardening Applied (Session 19, April 17, 2026)

**Router-WAN (172.31.0.1) + Router-LAN (192.168.1.1 / 192.168.2.2):** firewall name `SSH-ACL` rule set applied to `eth4 local` + `eth5 local` on both routers. Rule 10 = accept from 172.31.0.100 (Kali), rule 20 = drop + log all other tcp/22, default-action = accept (non-SSH local traffic unaffected). Config backups at `/config/config.boot.pre-sshacl-20260417` on each router.
**Mitigates:** CVSS 9.8+ OpenSSH 5.5p1 CVEs on VyOS 1.0.2 (EOL, unpatchable). Removes network path to SSH from any non-Kali source, including internal-compromised lateral movement paths.
**Verified:** Kali→both routers SSH working (accept counter ticking); WebServer / DatabaseServer → routers SSH dropped (counter confirms drops logged). Transit routing unaffected; Zabbix agents + SNMP monitoring still GREEN. Report at `Reports/Tier3_VyOS_SSH_ACL_Report.docx`.

### Account Baseline Cleanup (Session 20, April 19, 2026)

**Finding that drove this:** Oscar flagged user inventory on CD2 and DB. Investigation revealed **six unauthorized local accounts on CD2** (james/jasonc/allegrar/oscarp/botnet/team6 — three of them in wheel) that Session 18 hardening had missed entirely because the context summary only documented AD users. One unauthorized local account on DB (botnet).

**AD/DNS (192.168.1.5):**
- MinPasswordLength raised 8 → 14 (NIST / CIS alignment). Existing passwords remain valid until next change.
- `allegra` password rotated to 21-char value (old pw was 12 char, would have failed under new policy at next change).
- `james` re-enabled with fresh 20-char password to fill rubric row 14 ("User accounts" plural demo).
- `botnet`, `jason`, `oscar` **deleted from AD** (were disabled; now fully removed). Guest (builtin, disabled) and krbtgt (system) preserved.
- Privileged groups unchanged: Domain Admins / Enterprise Admins / Schema Admins = Administrator only.

**CD2 (192.168.1.51):** 6 local accounts removed via `userdel -r`: james, jasonc, allegrar, oscarp, botnet, team6. Oscar's 1-month-stale GNOME session on tty3 terminated via `loginctl terminate-user` before deletion (required pty sudo — see finding below). Post-state: only `playerone` in /etc/passwd interactive range; wheel = playerone only.

**DB (10.0.1.200):** 1 local account removed: botnet. Post-state: only `playerone` locally; wheel = playerone only.

**SSSD cache flushed** on both RHEL hosts (`sss_cache -E` + `systemctl restart sssd`) to invalidate stale cache entries for deleted AD users. Verified: deleted users no longer resolve; allegra/james/guest1/guest2 still resolve correctly.

**Rubric coverage (4910 Task List rows 13-15) after cleanup:**
- Row 13 Admin login: Administrator (AD) + playerone (local sudo, pty-required)
- Row 14 User accounts: allegra, james (both enabled domain users, neither privileged)
- Row 15 Guest account: guest1, guest2 (both enabled AD)

**Finding for pentest writeup — CD2 sudo TTY requirement:** Non-interactive `sudo -S` on CD2 rejects the valid password; requires `ssh -tt` (pty allocation). Workaround documented. Tracker password value IS correct — the issue is PAM configuration, not credential drift. Referenced in `Archive/Reference/AD_DB_Audit_Report_2026-03-24.md` as an unresolved issue from 3 weeks ago; now confirmed + documented. DB playerone sudo works the same way.

**Proxmox VMIDs documented** in tracker VM & Network sheet: Kali=201, OPNsense=1318, Router-LAN=1319, Router-WAN=1320, CD2=1321, AD/DNS=1322, DB=1323, CD1=1324, WebServer=1325.

### Tier 2 Hardening Applied (Session 21, April 19, 2026)

**DatabaseServer (10.0.1.200):** firewalld public zone tightened from permissive (broad service=ssh + port=80/5432/10050/10051 from any source) to source-restricted rich rules only. Ten rich rules now enforced: SSH from 172.31.0.100 only; PostgreSQL 5432 from 10.0.1.100 only; HTTP 80 from LAN + Kali; Zabbix 10051 from DMZ + LAN; Zabbix agent 10050 from localhost; ICMP from DMZ + LAN + Kali. Broad service and port allows removed. Cockpit/dhcpv6-client services removed (unused). Persisted via `firewall-cmd --runtime-to-permanent`; firewalld enabled at boot.

**ClientDesktop2 (192.168.1.51):** Same pattern. Five rich rules: SSH from 172.31.0.100 only; Zabbix agent 10050 from 10.0.1.200 only; ICMP from internal nets. Broad allows removed and persisted.

**Execution pattern:** A→B→C→D per host. Step A = add rich rules alongside existing broad allows (additive, zero lockout risk). Step B = verify authorized flows. Step C = remove broad allows (rich rules now sole enforcement). Step D = `firewall-cmd --runtime-to-permanent`. Commands executed via paramiko → Kali → ssh -tt pty-sudo to each host (CD2 sudo TTY quirk applies here — see CD2 Notes in tracker).

**Mitigates:** Lateral pivot during pentest week. Previously, compromise of any DMZ host meant free access to DB:5432 (PostgreSQL). Compromise of any LAN host meant free SSH access to CD2 and DB. Session 21 reduces these to single authorized source IPs per port.

**Verified:** 10/10 flow paths match expected allow/deny. All services active post-change (sshd, sssd, zabbix-server, zabbix-agent, postgresql-17, httpd, firewalld). Report at `Reports/Tier2_Firewalld_Report.docx`. Execution logs (baseline, Step A, Step B, Step C, Step D, final verification) saved locally at `~/session21_tier2_firewalld/` on admin workstation.

### Suricata Ruleset Deployment (Session 21c, April 19, 2026)

**Rulesets loaded:** 15 — 12 ET Open (emerging-malware, exploit, exploit_kit, scan, current_events, attack_response, shellcode, netbios, sql, smtp, dos, hunting) + 3 abuse.ch (feodotracker, sslblacklist, sslipblacklist). Threatfox excluded — 151K rules / 57 MB exceeded Suricata live-reload budget.

**Total alert signatures in engine:** 34,353. Suricata PID 95333, 335 MB RSS (up from 119 MB pre-deployment). Engine stable, inspecting WAN + DMZ + LAN.

**Install method:** **Manual, not via OPNsense UI.** The standard UI-driven rule update hung indefinitely because OPNsense WAN (172.31.0.2) cannot reach Fastly-hosted sources (rules.emergingthreats.net, urlhaus.abuse.ch, github.com) — TCP handshake succeeds but TLS Client Hello hangs. Google and Cloudflare reachable from OPNsense; same destinations reach fine from Kali via same gateway. Block is upstream (cyberlab / CSUSB), not our infrastructure. Diagnosis: `Reports/Suricata_Deployment_Report.docx` Section 3.

**Workaround:** downloaded rule tarballs on Kali (which has unblocked path), SCP'd to OPNsense, hand-edited `/usr/local/etc/suricata/installed_rules.yaml` to reference the 15 files in `/usr/local/etc/suricata/opnsense.rules/`, restarted Suricata with `service suricata restart`. Rule files and tarballs cached at `/tmp/suricata-rules/` on Kali for future re-use.

**CRITICAL caveat for future sessions:** the OPNsense UI believes nothing is installed (rules.sqlite not updated). **Do NOT click "Download & Update Rules" in the UI** — it will regenerate `installed_rules.yaml` and wipe our rule references. To update rules in the future, repeat the manual workflow or root-cause the upstream block.

**Report:** `Reports/Suricata_Deployment_Report.docx` (10 sections: intended workflow, root cause, workaround steps, ruleset table, validation state, known caveats, rollback).

### Tracker AD Users Sheet Added (Session 21c, April 19, 2026)

New `AD Users` sheet in `Team6_Tracker.xlsx` (position 4, after Quick Reference) with 7 current AD accounts + 3 deleted (historical block, greyed-italic). Columns: SamAccountName, Enabled, Password, Role, Member Of, Last Rotated, Purpose/Notes. Reflects Session 20 cleanup state (Administrator, allegra/[REDACTED], james/[REDACTED], guest1/guest2, Guest-disabled, krbtgt + botnet/jason/oscar deleted). VM & Network r15 (AD/DNS) Notes updated to point to the new sheet instead of the previous stale `[REDACTED]` one-liner.

---

## Key Credentials (Quick Copy)

All VM passwords are in the tracker's VM & Network sheet. These are the service/app creds:

| Service | Username | Password | Location |
|---------|----------|----------|----------|
| Zabbix Web UI | Admin | [REDACTED] | http://10.0.1.200/zabbix |
| Zabbix DB | zabbix | [REDACTED] | PostgreSQL on 10.0.1.200 |
| PostgreSQL app | rentadog_app | (in tracker/1Password) | Port 5432 on 10.0.1.200 |
| Bark Bot Admin | — | [REDACTED] | http://10.0.1.100/pages/admin_contacts.php |
| MailEnable (all) | james@rentadog.local | [REDACTED] | SMTP 25/587, IMAP 143, POP3 110 |
| MailEnable (admin) | admin@rentadog.local | [REDACTED] | Same ports |
| AD user allegra | allegra | [REDACTED] | Domain: nestlerteam6.local |
| AD guests | guest1, guest2 | [REDACTED] | Domain Users group |
| Cyberlab API | team6@csusb.edu | [REDACTED] | ai.cyberlab.csusb.edu |
| Cyberlab API Key | — | [REDACTED-API-KEY] | dolphin3:latest model |
| DB sudo (playerone) | playerone | [REDACTED] | DatabaseServer (rotated Session 16) |

---

## What's Left To Do

1. ~~**Vulnerability scanning**~~ — **COMPLETED Session 18.** Nmap 7.98 (all 9 hosts, 4 zones) + Nikto 2.5.0 (WebServer via SSH tunnel). Key findings: VyOS routers CRITICAL (OpenSSH 5.5p1, CVSS 9.8+), WebServer HIGH (TRACE, phpMyAdmin exposed, missing headers, directory indexing), firewall effectiveness HIGH (only SSH visible from WAN). See `Reports/Vuln_Assessment.docx`. Reusable copy at `04-Homelab/Vulnerability_Scan_Reference.docx`.
2. ~~**Initial hardening**~~ — **COMPLETED Session 18.** Apache hardened (TRACE off, headers, phpMyAdmin locked, XSS fixed), AD anonymous enumeration blocked (RestrictAnonymous=2), PostgreSQL locked to localhost+DMZ, SSH hardened (fail2ban on Kali, sshd config on DB). Report updated with Section 7: Remediation Actions Taken.
3. **Continue vulnerability testing + hardening** — Pentest week prep. More scans, fix remaining issues, harden further.
4. **System Security Plan (SSP)** — Required deliverable, not yet written.
5. **Download Suricata rulesets** — ET Open via OPNsense GUI (Services → Intrusion Detection → Download).
6. ~~**Full backup restore test**~~ — **PASSED Session 17.** Restored `rentadog_20260412_020001.sql.gz` into temp DB: 16/16 tables, 57 constraints, 37 indexes, 14 FKs, all row counts match, zero errors. Test DB dropped after verification. See `Reports/DB_Restore_Test.docx`.
7. **CD1 bridge confirmation** — nest05a4 vs nest06a4 (ask Professor Nestler).
8. ~~**WebServer duplicate SID**~~ — **FIXED Session 17.** Sysprep /generalize /oobe /reboot → new SID S-1-5-21-755779914-2621603520-1372550196. Domain rejoined, hostname restored, all services verified. See `Reports/WebServer_SID_Fix.docx`.

---

## Key Files

| File | Purpose |
|------|---------|
| `Planning/Team6_Tracker.xlsx` | Master tracker — 9 sheets: VMs, services, firewall, DNS, software, backups, connections, change log |
| `Team6_System_Overview.docx` | Team-facing system explainer for studying/presenting (keep updated) |
| `Deliverables/RentADog_Project_Blueprint.docx` | 19-page project blueprint with roles, schema, timeline |
| `Deliverables/RentADog_Deployment_Guide.docx` | Deployment procedures |
| `Network/layout.png` | Network topology diagram |
| `rentadog/` | Website source code (PHP/JS/CSS) |
| `Database/rentadog_setup.sql` | Database schema (14 tables) |
| `Reports/Vuln_Assessment.docx` | Full vuln scan report — Nmap + Nikto findings, all 9 hosts, prioritized recommendations + Section 7: Remediation Actions Taken |
| `Reports/` | Session deployment reports (historical reference) |
| `Archive/` | Old planning docs, duplicates, superseded guides |

---

## Access Patterns

**Remote → Kali:** `ssh kali@[REDACTED-TAILSCALE]` (Tailscale)
**Kali → Linux VMs:** `ssh playerone@<ip>` (key auth, no password needed)
**Kali → Windows VMs:** `ssh Administrator@<ip>` (key auth or password from tracker)
**Kali → VyOS routers:** `ssh vyos@<ip>` (key auth, algorithm compat handled by ~/.ssh/config)
**Kali → OPNsense:** `ssh root@172.31.0.2` (key auth, algorithm compat handled by ~/.ssh/config)
**Sandbox → Kali:** paramiko to [REDACTED-TAILSCALE], then hop to any VM
