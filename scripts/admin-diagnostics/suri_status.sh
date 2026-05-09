#!/bin/sh
echo "## Suricata state"
service suricata status 2>&1 | tail -10
echo
pgrep -lf "/usr/local/bin/suricata"
echo
echo "## Search for suricata log"
find /var/log -iname "*suricata*" 2>/dev/null
find /var/log/suricata -type f 2>/dev/null
echo
echo "## System messages about Suricata"
tail -50 /var/log/messages 2>&1 | grep -i suricata | tail -20
echo
echo "## Rule stats / errors"
grep -iE "loaded|error|fail|parse" /var/log/messages 2>&1 | grep -i suricata | tail -20
echo
echo "## eve.json total + alerts"
wc -l /var/log/suricata/eve.json
grep -c "event_type..alert" /var/log/suricata/eve.json
