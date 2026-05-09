# HEARTBEAT.md — Pentest Week IR Check

Every heartbeat (main agent, 30 min interval), do this — don't loop back to older tasks.

## Algorithm

1. Read `~/.openclaw/workspace/logs/suricata-state.json`.
2. If `unread_alerts == 0` OR the file doesn't exist → reply `HEARTBEAT_OK`. Stop. Do not load any other context.
3. If `unread_alerts > 0`:
   a. Read the file path at `state.latest_digest` (a small markdown digest).
   b. Read `~/.openclaw/workspace/agents/team6/ir-watcher.md` to load the IR analyst persona.
   c. Produce a correlation — one bullet per distinct attacker source IP or distinct TTP. Format: `HH:MM — [SEV] src_ip → target (pattern) · outcome (blocked by X / alert only / escalate)`.
   d. Append those bullets to today's `~/.openclaw/workspace/memory/YYYY-MM-DD.md` (create file + `# YYYY-MM-DD` header if missing).
   e. Cap: **5 bullets max per heartbeat** unless a real incident is unfolding. Aggregate noise, correlate signal.
   f. Atomically update state.json: set `unread_alerts: 0`, add `last_llm_read_at: <ISO timestamp>`.
4. Do **NOT** send Telegram messages yourself. Critical-severity paging is already handled by cron → `telegram-alert.sh`.
5. Stay silent in the chat surface. Only speak up (in the channel that triggered the heartbeat) if `state.last_batch_criticals > 0` AND you have a genuinely new correlation observation not already captured in today's memory file. One short line only. Otherwise: `HEARTBEAT_OK`.

## Why this shape

- **Cron does firehose work** (SSH to OPNsense, parse eve.json, dedup, pattern-match). Zero LLM tokens.
- **You do correlation + narrative** (reading a pre-digested small file, writing a short bullet to memory). Small tokens, proportional to actual pentest activity.
- **Idle nights cost near-zero** — if `unread_alerts == 0` you exit in under 100 tokens.
- **Busy pentest nights produce a writeup timeline for free** — every heartbeat's bullets in memory/ become the skeleton of the post-pentest report.

## Active window

**2026-04-20 through 2026-04-27** — pentest week. During this window, every alert is potentially in-scope evidence for the writeup. After 2026-04-28, pivot to compile mode: read the full `memory/` folder, produce timeline + attack chain summary.

## Files you'll touch

- Read: `logs/suricata-state.json`, `logs/suricata-digest-*.md`, `agents/team6/ir-watcher.md`, `MEMORY.md`
- Write: `memory/YYYY-MM-DD.md` (append), `logs/suricata-state.json` (update unread counter)

## What you never touch

- Do NOT modify `critical-patterns.txt`, `scripts/*`, firewall rules, Suricata configs, or VM state.
- Do NOT delete eve.json or digests — all evidence for the writeup.
- Do NOT post to the general Group 6 topic — stay in IR Watcher thread (71) if you need to message (but you usually shouldn't).
