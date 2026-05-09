#!/bin/bash
read -s SUDO_PW
echo "=== BEFORE: active sessions ==="
loginctl list-sessions --no-legend
echo
echo "=== BEFORE: processes under oscarp ==="
pgrep -u oscarp | wc -l
echo
echo "=== terminate user oscarp ==="
echo "$SUDO_PW" | sudo -S -p '' loginctl terminate-user oscarp 2>&1
sleep 2
echo
echo "=== AFTER: active sessions ==="
loginctl list-sessions --no-legend
echo
echo "=== AFTER: processes under oscarp ==="
pgrep -u oscarp | wc -l
echo
echo "=== oscarp home dir still there? (should be — we havent userdel'd yet) ==="
ls -la /home/oscarp 2>&1 | head -5
