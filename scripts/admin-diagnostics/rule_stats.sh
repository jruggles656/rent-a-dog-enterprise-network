
echo "## Rules load stats from suricata_$(date +%Y%m%d).log"
grep -iE "rules loaded|rules parsed|loading rules|signatures" /var/log/suricata/suricata_$(date +%Y%m%d).log | tail -10
echo
echo "## Any engine error?"
grep -iE "error|fatal" /var/log/suricata/suricata_$(date +%Y%m%d).log | tail -15
echo
echo "## Interfaces Suricata is capturing on"
grep -iE "interface|netmap|iface" /var/log/suricata/suricata_$(date +%Y%m%d).log | tail -10
echo
echo "## Test: rule action breakdown in our files"
grep -h -oE "^(alert|drop|reject|pass)" /usr/local/etc/suricata/opnsense.rules/*.rules 2>/dev/null | sort | uniq -c | sort -rn | head
echo
echo "## sample rule (first non-comment line from emerging-scan)"
grep -v "^#" /usr/local/etc/suricata/opnsense.rules/emerging-scan.rules | grep -v "^$" | head -2
