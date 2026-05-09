#!/bin/sh
echo "===== SURICATA POST-STATE ($(date)) ====="
echo
echo "## updater process (should be gone)"
pgrep -lf "rule-updater|installRules" || echo "  none running ✓"
echo
echo "## rule files now loaded"
ls -la /usr/local/etc/suricata/rules/ | head -30
echo
echo "## total rule lines across all rule files"
wc -l /usr/local/etc/suricata/rules/*.rules 2>/dev/null | tail -25
echo
echo "## rules.sqlite size (pre was 28672)"
ls -la /usr/local/etc/suricata/rules/rules.sqlite
echo
echo "## suricata process (may have new PID after SIGUSR2 reload)"
pgrep -lf "^/usr/local/bin/suricata"
echo
echo "## eve.json line count (pre was 3327)"
wc -l /var/log/suricata/eve.json
echo
echo "## alert count (was 0)"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json
echo
echo "## rule-file references in suricata config"
grep -E "^\\s*-\\s+\\S+\\.rules" /usr/local/etc/suricata/suricata.yaml 2>/dev/null
echo
echo "## action breakdown in loaded rules (alert vs drop vs other)"
grep -h -oE "^(alert|drop|reject|pass)" /usr/local/etc/suricata/rules/*.rules 2>/dev/null | sort | uniq -c | sort -rn
echo
echo "## last 5 lines of suricata.log"
tail -5 /var/log/suricata/suricata.log 2>/dev/null
