#!/bin/sh
echo "## Suricata process + memory (should be reloaded, same PID 2501 or new)"
ps -o pid,rss,vsz,etime,command -p 2501 2>&1
echo
echo "## eve.json growth"
wc -l /var/log/suricata/eve.json
echo
echo "## alert events now (was 0)"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json
echo
echo "## Rules loaded — last reload events in suricata.log"
grep -iE "rule|reload|sig_file|threshold" /var/log/suricata/suricata.log 2>&1 | tail -25
echo
echo "## Latest eve.json entries (last 3)"
tail -3 /var/log/suricata/eve.json 2>&1 | head -c 1500
