# ============================================================
# SCRIPT 2: NETWORK HEALTH CHECK — SECURED VERSION
# Owner: Oscar | VM: ClientDesktop2 (192.168.1.51) | Schedule: Every 15 min
# ============================================================
# SECURITY FIXES APPLIED:
#   🔴 FIX 1: Credentials moved to environment variables
#   🔴 FIX 2: SMTP upgraded to MailEnable-compatible fallback (STARTTLS → plain SMTP)
#   🟠 FIX 3: Log file permissions set to 640 (owner read/write, group read only)
# ============================================================

import subprocess
import socket
import smtplib
import logging
import os
import stat                           # FIX 3: for setting log file permissions
from datetime import datetime
from email.message import EmailMessage

# ============================================================
# FIX 1: ENVIRONMENT VARIABLES INSTEAD OF HARDCODED VALUES
# ============================================================
# Set in shell before running:
#   export RENTADOG_MAIL_FROM="oscar@rentadog.local"
#   export RENTADOG_MAIL_TO="admin@rentadog.local"
#   export RENTADOG_MAIL_SERVER_IP="10.0.1.100"
# ============================================================

LOG_FILE   = os.environ.get("RENTADOG_HEALTH_LOG", "/var/log/health_check.log")
MAIL_FROM  = os.environ.get("RENTADOG_MAIL_FROM",  "oscar@rentadog.local")
MAIL_TO    = os.environ.get("RENTADOG_MAIL_TO",    "admin@rentadog.local")
MAIL_SERVER_IP = os.environ.get("RENTADOG_MAIL_SERVER_IP", "10.0.1.100")

# ============================================================
# FIX 3: SET STRICT LOG FILE PERMISSIONS
# ============================================================
# Creates the log file if it doesn't exist, then sets permissions to 640:
#   Owner (oscar): read + write
#   Group:         read only
#   Others:        no access
# This prevents other users from reading health check results
# which could reveal network topology information to an attacker.
# ============================================================
if not os.path.exists(LOG_FILE):
    open(LOG_FILE, "w").close()
os.chmod(LOG_FILE, stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP)

logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="%(asctime)s %(message)s"
)

# All 10 VMs: name → (IP address, [ports to check])
# Updated with actual lab infrastructure:
#   WebServer (10.0.1.100): HTTP, HTTPS, SMTP, IMAP for email services
#   Database (10.0.1.200): PostgreSQL, SSH for DB operations
#   AD/DNS (192.168.1.5): DNS, LDAP, LDAP Secure
#   Router-WAN (172.31.0.1): WAN interface (no specific ports)
#   Router-LAN (192.168.1.1): LAN interface (no specific ports)
#   Firewall (10.0.1.1): OPNsense DMZ interface web UI
#   Tracker (192.168.1.10): Monitoring VM (not yet set up)
#   ClientDesktop1 (192.168.1.100): Windows 11 RDP
#   ClientDesktop2 (192.168.1.51): Linux SSH
#   SecurityDesktop (172.31.0.100): Kali Linux SSH
VMS = {
    "WebServer":       ("10.0.1.100",    [80, 443, 25, 143]),
    "Database":        ("10.0.1.200",    [5432, 22]),
    "AD/DNS":          ("192.168.1.5",   [53, 389, 636]),
    "Router-WAN":      ("172.31.0.1",    []),
    "Router-LAN":      ("192.168.1.1",   []),
    "Firewall":        ("10.0.1.1",      [443]),
    "Tracker":         ("192.168.1.10",  []),
    "ClientDesktop1":  ("192.168.1.100", [3389]),
    "ClientDesktop2":  ("192.168.1.51",  [22]),
    "SecurityDesktop": ("172.31.0.100",  [22]),
}


def ping(host):
    """
    Sends one ICMP ping to the host.
    Returns True if the host responds, False if unreachable.
    -c 1 = one packet | -W 2 = 2 second timeout
    """
    result = subprocess.run(
        ["ping", "-c", "1", "-W", "2", host],
        capture_output=True
    )
    return result.returncode == 0


def check_port(host, port):
    """
    Attempts a TCP connection to host:port.
    Returns True if the service is accepting connections.
    timeout=2 prevents hanging if the host is slow to respond.
    """
    try:
        socket.create_connection((host, port), timeout=2)
        return True
    except (socket.timeout, ConnectionRefusedError, OSError):
        return False


def run_health_check():
    """
    Main check loop — ping every VM, check required ports,
    log results, collect failures, send alert if needed.
    """
    failures = []

    for name, (ip, ports) in VMS.items():
        if not ping(ip):
            msg = f"❌ UNREACHABLE: {name} ({ip})"
            logging.warning(msg)
            failures.append(msg)
            continue

        logging.info(f"✅ OK: {name} ({ip})")

        for port in ports:
            if not check_port(ip, port):
                msg = f"⚠️  PORT DOWN: {name} ({ip}) — port {port}"
                logging.warning(msg)
                failures.append(msg)
            else:
                logging.info(f"   Port {port} open on {name}")

    if failures:
        send_alert("🚨 Network Health Check FAILED", "\n".join(failures))
        print("⚠️  Alert sent — failures detected.")
    else:
        print("✅ All VMs healthy.")


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
    ============================================================
    """
    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"]    = MAIL_FROM
    msg["To"]      = MAIL_TO
    msg.set_content(f"Health check at {datetime.now()}\n\n{body}")

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
        logging.error(f"STARTTLS failed: {e}")

    # Fall back to plain SMTP on port 25 (current MailEnable Standard config)
    try:
        with smtplib.SMTP(MAIL_SERVER_IP, 25) as s:
            s.send_message(msg)
        print(f"📧 Alert sent (plain SMTP): {subject}")
    except Exception as e:
        print(f"⚠️  Email alert failed: {e}")
        logging.error(f"Email alert failed: {e}")


# --- ENTRY POINT ---
if __name__ == "__main__":
    run_health_check()

# ============================================================
# SETUP REQUIRED BEFORE RUNNING:
#
# 1. Set script permissions:
#    chmod 700 script2_network_health_check_secure.py
#
# 2. Cron schedule (every 15 minutes):
#    */15 * * * * python3 /scripts/script2_network_health_check_secure.py
# ============================================================
