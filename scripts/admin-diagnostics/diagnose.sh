#!/bin/sh
echo "## updater process runtime (how long running?)"
ps -o pid,etime,command -p 96466 -p 96622 2>&1
echo
echo "## what is rule-updater.py actually doing right now? (open files)"
procstat -f 96622 2>&1 | head -20
echo
echo "## truss for 5 sec to see system calls"
timeout 5 truss -p 96622 2>&1 | tail -20 || echo "(truss needed root or unavailable)"
echo
echo "## network test — can OPNsense reach emergingthreats.net?"
host rules.emergingthreatspro.com 2>&1
host rules.emergingthreats.net 2>&1
echo
echo "## curl test to ET Open"
curl -sS -m 8 -o /dev/null -w "HTTP=%{http_code} time=%{time_total}s url=%{url_effective}\n" https://rules.emergingthreats.net/open/suricata-7.0.8/emerging.rules.tar.gz
echo
echo "## curl test to abuse.ch"
curl -sS -m 8 -o /dev/null -w "HTTP=%{http_code} time=%{time_total}s\n" https://urlhaus.abuse.ch/downloads/urlhaus.rules/
echo
echo "## DNS configured"
cat /etc/resolv.conf
echo
echo "## default route"
netstat -rn | grep default | head -3
