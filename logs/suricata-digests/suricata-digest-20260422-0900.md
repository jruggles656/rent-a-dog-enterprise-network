# Suricata Digest — 2026-04-22T09:00:01-07:00

- Window: last 15 min (since `2026-04-22T08:44:01-07:00`)
- External alerts: **1** (1 unique sigs)  ·  Self/admin: **3** (2 unique)  ·  Critical: **0**

## External alerts (by severity)
- 🟡 **sev 2** · sid `2003068` · Attempted Information Leak
  `ET SCAN Potential SSH Scan OUTBOUND`
  10.0.1.200 → 172.31.0.2:22/TCP · ×1 since 2026-04-22T15:19:42

## Self / admin traffic (not attackers — de-prioritized)
- 🛠️ sev 2 · Attempted Information Leak · `ET SCAN Potential SSH Scan`  · 172.31.0.100 (Kali (sole authorized admin source)) → 192.168.1.5:22/TCP · ×1
- 🛠️ sev 2 · Attempted Information Leak · `ET SCAN Potential SSH Scan`  · 172.31.0.100 (Kali (sole authorized admin source)) → 10.0.1.200:22/TCP · ×2
