#!/bin/bash
read -s SUDO_PW
echo "$SUDO_PW" | sudo -S -p '' -k true 2>&1 && echo "OK" || echo "FAIL"
