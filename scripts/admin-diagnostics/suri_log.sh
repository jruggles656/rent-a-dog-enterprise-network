#!/bin/sh
echo "## last 60 lines of today's suricata log"
tail -60 /var/log/suricata/suricata_20260419.log
echo
echo "## rules loaded/error messages"
grep -iE "rule|loaded|sig_file|error|parse|signature" /var/log/suricata/suricata_20260419.log | tail -30
