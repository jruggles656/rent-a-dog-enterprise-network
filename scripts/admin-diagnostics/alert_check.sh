
echo "alert events:"
grep -c '"event_type":"alert"' /var/log/suricata/eve.json
echo
echo "last 5 alert signatures:"
grep '"event_type":"alert"' /var/log/suricata/eve.json | tail -5 | python3 -c "
import json, sys
for line in sys.stdin:
    try:
        e = json.loads(line)
        a = e.get('alert', {})
        print(f\"  [{a.get('signature_id')}] {a.get('signature')[:80]} (src={e.get('src_ip')} -> {e.get('dest_ip')}:{e.get('dest_port')})\")
    except: pass
"
echo
echo "event type breakdown:"
awk -F'"event_type":"' '{print $2}' /var/log/suricata/eve.json 2>/dev/null | awk -F'"' '{print $1}' | sort | uniq -c | sort -rn
