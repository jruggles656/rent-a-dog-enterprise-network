
echo "=== POST-SCAN VERIFICATION ==="
echo
echo "## Suricata process"
pgrep -lf "^/usr/local/bin/suricata"
ps -o pid,rss,etime,command -p $(pgrep -f "^/usr/local/bin/suricata") | head -2
echo
echo "## eve.json line count"
wc -l /var/log/suricata/eve.json
echo
echo "## alert count"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json
echo
echo "## event type breakdown"
awk -F'"event_type":"' '{print $2}' /var/log/suricata/eve.json 2>/dev/null | awk -F'"' '{print $1}' | sort | uniq -c | sort -rn | head
echo
echo "## Top 10 alert signatures fired"
grep '"event_type":"alert"' /var/log/suricata/eve.json | python3 -c "
import json,sys,collections
c = collections.Counter()
for line in sys.stdin:
    try:
        e = json.loads(line)
        sig = e.get('alert',{}).get('signature','?')
        c[sig] += 1
    except: pass
for sig, count in c.most_common(10):
    print(f'  [{count:4d}] {sig[:100]}')"
echo
echo "## Total rules loaded (from stats.log)"
tail -30 /var/log/suricata/stats.log 2>/dev/null | grep -iE "detect.engines|rules|signatures" | head
