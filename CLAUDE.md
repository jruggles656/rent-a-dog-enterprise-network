# CLAUDE.md — Rent a Dog Enterprise Network

## What This Is
GitHub portfolio project for IST 4910 (Enterprise System Administration, Spring 2026, CSUSB).
9-VM enterprise network with AI-powered incident response. See README.md for full details.

## Repo Structure
- `website/` — Rent a Dog PHP app (vanilla PHP + PostgreSQL via PDO)
- `database/` — PostgreSQL schema
- `scripts/` — All automation (security, monitoring, hardening, diagnostics)
- `ai-integration/` — OpenClaw config, agent personas, autoblock, IR pipeline
- `pentest/` — Vulnerability scans, 21 findings from pentest week
- `logs/` — Suricata digests, autoblock logs
- `docs/` — Reports, architecture docs, deliverables
- `network/` — Topology diagrams

## Security
- No real credentials, API keys, Tailscale IPs, or student IDs in this repo
- Private IPs (10.x, 192.168.x) are fine — they're lab-internal
- Sanitize any sensitive values before committing
