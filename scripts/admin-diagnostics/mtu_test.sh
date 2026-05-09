#!/bin/sh
echo "## Layer-by-layer test: raw TCP handshake vs TLS vs HTTP"
echo
echo "### 1. Raw TCP 443 to ET Open (no TLS)"
nc -zv -w 5 rules.emergingthreats.net 443 2>&1
echo
echo "### 2. Raw TCP 443 to google (works elsewhere)"
nc -zv -w 5 google.com 443 2>&1
echo
echo "### 3. Raw TCP 80 to ET Open"
nc -zv -w 5 rules.emergingthreats.net 80 2>&1
echo
echo "### 4. MTU path discovery test"
ping -D -s 1472 -c 3 rules.emergingthreats.net 2>&1 | tail -5
echo
echo "### 5. Current WAN interface MTU"
ifconfig vtnet0 | grep -i mtu
echo
echo "### 6. Curl verbose — where does TLS hang?"
timeout 10 curl -v --max-time 8 https://rules.emergingthreats.net/ 2>&1 | grep -iE "^\*|^<|^>" | head -15
