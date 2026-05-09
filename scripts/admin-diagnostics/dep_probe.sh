#!/bin/bash
echo "## home dir contents (size / recent activity)"
sudo -n ls -la /home/ 2>/dev/null || ls -la /home/
echo
echo "## home dirs for each suspect user — any files?"
for u in james jasonc allegrar oscarp botnet team6; do
    if [ -d "/home/$u" ]; then
        n=$(find "/home/$u" -mindepth 1 -not -name ".*" 2>/dev/null | wc -l)
        total=$(find "/home/$u" -mindepth 1 2>/dev/null | wc -l)
        last=$(find "/home/$u" -type f -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | awk '{print $NF}')
        echo "/home/$u -> $n non-dotfile entries, $total total, most-recent: $last"
    fi
done
echo
echo "## grep scripts for these user names"
grep -rIlE "james|jasonc|allegrar|oscarp|botnet|team6" /home/playerone/scripts/ /etc/systemd/system/ /etc/cron.d/ /var/spool/cron/ 2>/dev/null | head -20
echo
echo "## active crontabs referencing these users"
for u in playerone james jasonc allegrar oscarp botnet team6; do
    crontab -u "$u" -l 2>/dev/null | grep -v '^#' | grep -v '^$' && echo "(^ from $u)"
done
echo
echo "## processes running as any of these users"
ps -eo user,pid,cmd --no-headers | awk '$1 ~ /^(james|jasonc|allegrar|oscarp|botnet|team6)$/ {print}'
echo
echo "## systemd services referencing user= james|jasonc|allegrar|oscarp|botnet|team6"
grep -rIlE "^User=(james|jasonc|allegrar|oscarp|botnet|team6)$" /etc/systemd/ 2>/dev/null | head
echo
echo "## SSH authorized_keys for these users"
for u in james jasonc allegrar oscarp botnet team6 playerone; do
    if [ -f "/home/$u/.ssh/authorized_keys" ]; then
        n=$(wc -l < "/home/$u/.ssh/authorized_keys")
        echo "/home/$u/.ssh/authorized_keys: $n key(s)"
    fi
done
