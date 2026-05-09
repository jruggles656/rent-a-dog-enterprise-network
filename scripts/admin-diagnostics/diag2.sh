#!/bin/sh
echo "## what socket is the python process talking to?"
sockstat -4 -c -P tcp | grep 96622 | head -10
echo
echo "## tcp connections from python (inherited from ET feed check)"
sockstat -4 -c | grep python3 | head -10
echo
echo "## test ET Open directly from OPNsense (short timeout)"
curl -sS -m 5 -o /dev/null -w "ET Open: HTTP=%{http_code} t=%{time_total}s\n" https://rules.emergingthreats.net/open/suricata-7.0.8/emerging.rules.tar.gz 2>&1 | tail -3
echo
echo "## test abuse.ch"
curl -sS -m 5 -o /dev/null -w "abuse.ch: HTTP=%{http_code} t=%{time_total}s\n" https://feodotracker.abuse.ch/downloads/ipblocklist.txt 2>&1 | tail -3
echo
echo "## opnsense IDS log — see if there are errors"
tail -20 /var/log/suricata/suricata_update.log 2>&1
echo
echo "## system log for suricata-related errors"
tail -20 /var/log/configd/configd_*.log 2>&1 | tail -30
