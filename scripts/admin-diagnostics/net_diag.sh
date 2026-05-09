#!/bin/sh
echo "## updater runtime"
ps -o pid,etime,command -p 96466 -p 96622 2>&1
echo
echo "## DNS test"
host rules.emergingthreats.net 2>&1
echo
echo "## Ping test to external"
ping -c 2 -t 3 8.8.8.8 2>&1 | tail -3
