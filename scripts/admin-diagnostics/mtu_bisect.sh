#!/bin/sh
echo "## Find the actual working MTU — bisect"
for size in 1472 1400 1380 1360 1320 1280 1200; do
    res=$(ping -D -s $size -c 2 -t 3 -q rules.emergingthreats.net 2>&1 | grep "packet loss" | head -1)
    echo "  MTU payload=$size (total=$((size+28))): $res"
done
echo
echo "## Compare with Kali path"
echo "  (Kali reaches via same WAN gateway but works — will test MSS clamp fix)"
