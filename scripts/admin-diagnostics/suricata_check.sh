#!/bin/sh
echo "## updater still running?"
pgrep -lf "rule-updater|installRules"
echo
echo "## rules dir contents"
ls -la /usr/local/etc/suricata/rules/
echo
echo "## rule file line counts"
wc -l /usr/local/etc/suricata/rules/*.rules 2>/dev/null | tail -25
echo
echo "## suricata process"
pgrep -lf "^/usr/local/bin/suricata"
echo
echo "## eve.json line count (pre was 3327)"
wc -l /var/log/suricata/eve.json
echo
echo "## any alerts yet?"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json 2>/dev/null
