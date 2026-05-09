#!/bin/sh
echo "========== From OPNsense =========="
echo
echo "## traceroute to ET Open (where do packets die?)"
traceroute -n -w 2 -q 1 -m 8 rules.emergingthreats.net 2>&1 | head -15
echo
echo "## traceroute to google (works — compare path)"
traceroute -n -w 2 -q 1 -m 8 google.com 2>&1 | head -10
echo
echo "## OPNsense WAN outbound firewall rules (pfctl)"
pfctl -s rules 2>/dev/null | grep -iE "out|wan|block" | head -20
echo
echo "## Check if OPNsense's OWN rules block this"
pfctl -sr 2>&1 | grep -E "block out|reject" | head -10
