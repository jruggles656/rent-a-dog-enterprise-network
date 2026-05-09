#!/bin/sh
echo "## config.xml user section"
python3 -c "
import xml.etree.ElementTree as ET
tree = ET.parse('/conf/config.xml')
for u in tree.findall('.//user'):
    name = u.findtext('name','?')
    uid = u.findtext('uid','?')
    scope = u.findtext('scope','?')
    pw_hash = u.findtext('password','(none)')
    # redact hash
    print(f'user={name} uid={uid} scope={scope} has_pw={\"yes\" if pw_hash and pw_hash != \"(none)\" else \"no\"} pw_prefix={pw_hash[:8]}...')" 2>&1
echo
echo "## /etc/master.passwd root entry"
grep "^root:" /etc/master.passwd | cut -d: -f1 | head -1
echo
echo "## Available password-change tools"
ls /usr/local/sbin/opnsense-* 2>/dev/null | head
which opnsense-shell
