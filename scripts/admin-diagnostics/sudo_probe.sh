#!/bin/bash
read -s SUDO_PW
echo "testing sudo -S with provided pw:"
echo "$SUDO_PW" | sudo -S -p '' -k whoami 2>&1
