#!/bin/bash
echo "## services"
systemctl is-active zabbix-server zabbix-agent postgresql-17 httpd sshd 2>&1
echo "## zabbix host availability (sudo-less via psql peer auth)"
echo "SELECT host, status, available, error FROM hosts WHERE status=0 ORDER BY host;" | \
  sudo -S -u postgres psql zabbix -P pager=off <<< "$SUDO_PW" 2>&1 | head -30 || \
  echo "(need sudo pw)"
echo "## recent zabbix alerts (problems)"
echo "SELECT clock::timestamp with time zone, severity, name FROM problem ORDER BY clock DESC LIMIT 10;" | \
  sudo -u postgres psql zabbix -P pager=off 2>&1 | head -30
