#!/bin/bash
echo "## LOCAL /etc/passwd (file contents — local accounts only)"
cat /etc/passwd
echo
echo "## INTERACTIVE local users (uid >= 1000, not nobody)"
awk -F: '$3>=1000 && $3<65534 {print $1, "uid="$3, "home="$6, "shell="$7}' /etc/passwd
echo
echo "## getent passwd (local + SSSD-enumerated AD)"
getent passwd | awk -F: '$3>=1000 && $3<65534 {print $1, "uid="$3, "source="($3>=1000&&$3<10000?"local":"AD/SSSD")}'
echo
echo "## groups containing wheel/admin/sudo"
getent group wheel adm sudo 2>/dev/null
echo
echo "## sudoers files"
sudo -n cat /etc/sudoers 2>/dev/null | grep -vE '^\s*#|^\s*$' | head -20
ls /etc/sudoers.d/ 2>/dev/null
sudo -n grep -rH "" /etc/sudoers.d/ 2>/dev/null | grep -vE '^\s*#|^\s*$' | head
echo
echo "## id lookups for each of Oscars listed names"
for u in playerone oscar allegra botnet james jason team6 guest1 guest2; do
    line=$(id "$u" 2>&1 | head -1)
    # classify as local vs AD by uid range
    uid=$(id -u "$u" 2>/dev/null)
    if [ -z "$uid" ]; then
        src="MISSING"
    elif [ "$uid" -lt 10000 ]; then
        src="LOCAL"
    else
        src="AD/SSSD"
    fi
    echo "$u [$src]: $line"
done
