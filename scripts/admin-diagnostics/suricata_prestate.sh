#!/bin/sh
echo "===== SURICATA PRE-STATE ($(date)) ====="
echo
echo "## suricata process"
pgrep -lf suricata | head -5
echo
echo "## eve.json size + line count"
ls -la /var/log/suricata/eve.json
wc -l /var/log/suricata/eve.json
echo
echo "## event-type breakdown (currently: only anomaly + ssh flow, zero alerts)"
awk -F'"event_type":"' '{print $2}' /var/log/suricata/eve.json 2>/dev/null | awk -F'"' '{print $1}' | sort | uniq -c | sort -rn
echo
echo "## alert count (must be 0 pre-change)"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json 2>/dev/null
echo
echo "## rules dir (should be just OPNsense.rules stub)"
ls -la /usr/local/etc/suricata/rules/
echo
echo "## rule files referenced in suricata.yaml"
grep -E "^\s*-\s+\S+\.rules" /usr/local/etc/suricata/suricata.yaml 2>/dev/null
echo
echo "## OPNsense rules stub size (confirm it's still the 7-line stub)"
wc -l /usr/local/etc/suricata/rules/*.rules 2>/dev/null
echo
echo "## interfaces Suricata is listening on"
ps axo pid,args | grep -v grep | grep suricata | head
