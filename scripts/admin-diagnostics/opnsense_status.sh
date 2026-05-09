#!/bin/sh
echo "## suricata process"; pgrep -lf suricata | head -5
echo "## eve.json"; ls -la /var/log/suricata/eve.json 2>/dev/null; wc -l /var/log/suricata/eve.json 2>/dev/null
echo "## rules dir"; ls /usr/local/etc/suricata/rules/ 2>/dev/null | head -30
echo "## opnsense.rules dir"; ls /usr/local/etc/suricata/opnsense.rules/ 2>/dev/null | head -30
echo "## suricata config enabled rules"; grep -E "^\s*-\s+\S+\.rules" /usr/local/etc/suricata/suricata.yaml 2>/dev/null | head -40
echo "## alert count in eve.json"; grep -c '"event_type":"alert"' /var/log/suricata/eve.json 2>/dev/null
echo "## last 5 alerts (signature only)"
grep '"event_type":"alert"' /var/log/suricata/eve.json 2>/dev/null | tail -5
echo "## eve types breakdown"
awk -F'"event_type":"' '{print $2}' /var/log/suricata/eve.json 2>/dev/null | awk -F'"' '{print $1}' | sort | uniq -c | sort -rn | head
