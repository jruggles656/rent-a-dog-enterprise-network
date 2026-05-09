#!/bin/sh
echo "## Test outbound HTTPS to various destinations from OPNsense"
for url in https://github.com https://google.com https://cloudflare.com https://rules.emergingthreats.net https://urlhaus.abuse.ch; do
    result=$(curl -sS -m 5 -o /dev/null -w "%{http_code}/%{time_total}s" "$url" 2>&1)
    echo "  $url -> $result"
done
echo
echo "## Test outbound HTTP (port 80)"
for url in http://example.com http://rules.emergingthreats.net; do
    result=$(curl -sS -m 5 -o /dev/null -w "%{http_code}/%{time_total}s" "$url" 2>&1)
    echo "  $url -> $result"
done
echo
echo "## From Kali (for comparison — Kali reaches internet freely)"
echo "  Kali would show on its own"
echo
echo "## Default route on OPNsense (what gateway?)"
netstat -rn4 | grep -E "default|^[0-9]" | head -5
