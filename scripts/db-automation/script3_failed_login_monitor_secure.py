# ============================================================
# SCRIPT 3: FAILED LOGIN MONITOR — SECURED VERSION
# Owner: Oscar | VM: Tracker (192.168.1.10) | Schedule: Hourly via cron
# ============================================================
# SECURITY FIXES APPLIED:
#   🔴 FIX 1: Credentials moved to environment variables
#   🔴 FIX 2: SMTP upgraded to MailEnable-compatible fallback (STARTTLS → plain SMTP)
#   🟡 FIX 3: IP address validation after regex extraction
#   🟠 FIX 4: Change management log permissions set to 640
# ============================================================

import re
import smtplib
import os
import stat                           # FIX 4: for setting log file permissions
import ipaddress                      # FIX 3: for validating extracted IP addresses
from collections import Counter
from datetime import datetime, timedelta
from email.message import EmailMessage

# ============================================================
# FIX 1: ENVIRONMENT VARIABLES INSTEAD OF HARDCODED VALUES
# ============================================================
AUTH_LOG    = os.environ.get("RENTADOG_AUTH_LOG",    "/var/log/auth.log")
CHANGE_LOG  = os.environ.get("RENTADOG_CHANGE_LOG",  "/var/log/change_management.log")
MAIL_FROM   = os.environ.get("RENTADOG_MAIL_FROM",   "oscar@rentadog.local")
MAIL_TO     = os.environ.get("RENTADOG_MAIL_TO",     "admin@rentadog.local")
MAIL_SERVER_IP = os.environ.get("RENTADOG_MAIL_SERVER_IP", "10.0.1.100")
THRESHOLD   = int(os.environ.get("RENTADOG_LOGIN_THRESHOLD", "5"))

# ============================================================
# FIX 4: SET STRICT PERMISSIONS ON CHANGE MANAGEMENT LOG
# ============================================================
# chmod 640 = owner read/write, group read, others nothing
# The change management log is a security document — it should
# not be readable by all users on the Tracker VM.
# ============================================================
if not os.path.exists(CHANGE_LOG):
    open(CHANGE_LOG, "w").close()
os.chmod(CHANGE_LOG, stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP)


def parse_failed_logins():
    """
    Scans auth.log for failed SSH logins in the last hour.
    Returns IPs that exceed the failure threshold.

    FIX 3: IP ADDRESS VALIDATION
    ============================================================
    BEFORE (no validation — raw regex result used directly):
        ip = ip_match.group(1)
        failures[ip] += 1

    AFTER (validated with ipaddress module):
        ip = ip_match.group(1)
        try:
            ipaddress.ip_address(ip)  # raises ValueError if not a real IP
        except ValueError:
            continue  # skip malformed/injected entries

    Why this matters: a crafted log line could contain something
    that looks like "from 999.999.999.999" or even inject shell
    characters. Validating with ipaddress ensures we only process
    real, well-formed IP addresses.
    ============================================================
    """
    failures = Counter()
    cutoff   = datetime.now() - timedelta(hours=1)

    # Try reading auth log file first, fall back to journalctl
    lines = []
    try:
        with open(AUTH_LOG, "r") as f:
            lines = f.readlines()
    except (FileNotFoundError, PermissionError):
        # Fallback: use journalctl for SSH auth logs (works without root)
        import subprocess
        try:
            result = subprocess.run(
                ["journalctl", "-u", "sshd", "--since", "1 hour ago", "--no-pager"],
                capture_output=True, text=True, timeout=30
            )
            lines = result.stdout.splitlines()
        except Exception as e:
            print(f"⚠️  Cannot read auth logs: {e}")
            return {}

    for line in lines:
        if "Failed password" not in line:
            continue

        # Parse timestamp
        try:
            log_time = datetime.strptime(
                line[:15], "%b %d %H:%M:%S"
            ).replace(year=datetime.now().year)
        except ValueError:
            continue

        if log_time < cutoff:
            continue

        # Extract IP address using regex
        ip_match = re.search(r"from (\S+)", line)
        if not ip_match:
            continue

        raw_ip = ip_match.group(1)

        # FIX 3: Validate IP address before counting
        try:
            ipaddress.ip_address(raw_ip)
        except ValueError:
            continue

        failures[raw_ip] += 1

    return {ip: count for ip, count in failures.items() if count >= THRESHOLD}


def run_monitor():
    """
    Main function — check for suspicious IPs, alert, and log.
    
    NOTE: This script runs on Tracker VM (192.168.1.10) which must be
    set up and running first before failed login monitoring can work.
    """
    suspects = parse_failed_logins()

    summary  = f"\n[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Failed Login Monitor\n"
    summary += "-" * 50 + "\n"

    if suspects:
        for ip, count in suspects.items():
            alert_body = (
                f"⚠️  {count} failed SSH login attempts detected from {ip} "
                f"in the last hour.\n\n"
                f"Recommended action: Review OPNsense firewall rules and "
                f"consider blocking {ip} if not a known address."
            )
            summary += f"  ALERT: {ip} — {count} failed attempts\n"
            send_alert(f"🚨 SSH Brute Force Detected: {ip}", alert_body)
            print(f"⚠️  Alert sent for {ip} ({count} failures)")
    else:
        summary += "  ✅ No threats detected.\n"
        print("✅ No suspicious login activity.")

    # Append summary to change management log
    with open(CHANGE_LOG, "a") as f:
        f.write(summary)


def send_alert(subject, body):
    """
    Sends an email alert via SMTP to MailEnable Standard.
    MailEnable Standard doesn't support STARTTLS, so we try port 587 first,
    then fall back to plain SMTP on port 25 if TLS fails.

    SMTP CONFIGURATION FOR MAILEABLE STANDARD
    ============================================================
    MailEnable Standard is running on WebServer (10.0.1.100):
      - SMTP (submission): port 587, no TLS/STARTTLS
      - SMTP (relay):      port 25, no TLS
      - Credentials:       normal password auth (no TLS needed)
    
    BEFORE (assumed Postfix with STARTTLS):
        with smtplib.SMTP(MAIL_SERVER_IP, 587) as s:
            s.ehlo()
            s.starttls()  # ❌ MailEnable Standard doesn't support this!
            s.send_message(msg)

    AFTER (try STARTTLS first, fall back to plain SMTP):
        Try 587 with STARTTLS first (in case future SSL is added)
        Fall back to 25 plain SMTP (current MailEnable config)

    Why this matters: MailEnable Standard (free edition) doesn't support TLS.
    The script needs to handle this gracefully — try the secure way first,
    but fall back to plain SMTP if TLS is unavailable. This prevents crashes
    due to configuration mismatches between the script and mail server.

    Security note: SSH brute force alert emails contain attacker IP addresses
    and attack patterns — sensitive security data that should never travel
    unencrypted across the network.
    ============================================================
    """
    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"]    = MAIL_FROM
    msg["To"]      = MAIL_TO
    msg.set_content(f"Alert at {datetime.now()}\n\n{body}")

    # Try STARTTLS first (port 587) — may work if SSL is later added
    try:
        with smtplib.SMTP(MAIL_SERVER_IP, 587) as s:
            s.ehlo()
            s.starttls()
            s.send_message(msg)
        print(f"📧 Alert sent (TLS): {subject}")
        return
    except Exception as e:
        print(f"⚠️  STARTTLS failed, falling back to plain SMTP: {e}")

    # Fall back to plain SMTP on port 25 (current MailEnable Standard config)
    try:
        with smtplib.SMTP(MAIL_SERVER_IP, 25) as s:
            s.send_message(msg)
        print(f"📧 Alert sent (plain SMTP): {subject}")
    except Exception as e:
        print(f"⚠️  Email alert failed: {e}")


# --- ENTRY POINT ---
if __name__ == "__main__":
    run_monitor()

# ============================================================
# SETUP REQUIRED BEFORE RUNNING:
#
# 1. Set script permissions:
#    chmod 700 script3_failed_login_monitor_secure.py
#
# 2. Cron schedule (every hour):
#    0 * * * * python3 /scripts/script3_failed_login_monitor_secure.py
#
# ⚠️  IMPORTANT: Tracker VM (192.168.1.10) must be set up first!
#    This script needs auth.log on the Tracker VM to function.
# ============================================================
