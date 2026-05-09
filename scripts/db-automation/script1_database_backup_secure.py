# ============================================================
# SCRIPT 1: DATABASE BACKUP — SECURED VERSION
# Owner: Oscar | VM: Database (10.0.1.200) | Schedule: Daily via cron
# ============================================================
# SECURITY FIXES APPLIED:
#   🔴 FIX 1: Credentials moved to environment variables (no plaintext passwords)
#   🔴 FIX 2: SMTP upgraded to MailEnable-compatible fallback (STARTTLS → plain SMTP)
#   🟠 FIX 3: SCP uses StrictHostKeyChecking to prevent MITM attacks
#   🟠 FIX 4: Backup files chmod 600 (only owner can read)
#   🟠 FIX 5: Script itself should be chmod 700 (set in shell, not Python)
# ============================================================

import subprocess
import os
import gzip
import smtplib
import stat                          # FIX 4: used to set strict file permissions
from datetime import datetime, timedelta
from email.message import EmailMessage

# ============================================================
# FIX 1: ENVIRONMENT VARIABLES INSTEAD OF HARDCODED CREDENTIALS
# ============================================================
# BEFORE (insecure — password visible in plain text):
#   PASSWORD = "ChangeMeWeek11!"
#
# AFTER (secure — password loaded from the system environment):
#   Set once in the shell before running:
#     export RENTADOG_DB_PASSWORD="your_actual_password"
#     export RENTADOG_MAIL_PASSWORD="your_mail_password"  (if mail auth needed)
#
# os.environ.get() reads the variable from the environment at runtime.
# If the variable isn't set, it returns None — we check for that below.
# ============================================================

BACKUP_DIR   = os.environ.get("RENTADOG_BACKUP_DIR", "/backups/")
TRACKER_VM   = os.environ.get("RENTADOG_TRACKER",    "oscar@192.168.1.10")
DB_PASSWORD  = os.environ.get("RENTADOG_DB_PASSWORD")   # 🔴 no default — must be set
MAIL_FROM    = os.environ.get("RENTADOG_MAIL_FROM",  "admin@rentadog.local")
MAIL_TO      = os.environ.get("RENTADOG_MAIL_TO",    "admin@rentadog.local")
MAIL_SERVER_IP = os.environ.get("RENTADOG_MAIL_SERVER_IP", "10.0.1.100")
KEEP_DAYS    = 7

# Safety check — refuse to run if critical env vars are missing
if not DB_PASSWORD:
    raise EnvironmentError(
        "❌ RENTADOG_DB_PASSWORD is not set. "
        "Run: export RENTADOG_DB_PASSWORD='your_password'"
    )


def backup_database():
    """Main backup function — dump, compress, transfer, rotate."""

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename  = os.path.join(BACKUP_DIR, f"rentadog_{timestamp}.sql.gz")

    # --- STEP 1: Run pg_dump ---
    # PGPASSWORD env var is the secure way to pass a password to pg_dump
    # without it appearing in the process list (ps aux would expose it otherwise)
    env = os.environ.copy()
    env["PGPASSWORD"] = DB_PASSWORD   # pg_dump reads this automatically

    dump = subprocess.run(
        ["pg_dump", "-U", "rentadog_app", "-h", "localhost", "rentadog"],
        capture_output=True,
        env=env                        # pass the modified environment
    )

    if dump.returncode != 0:
        send_alert("🚨 Backup FAILED", dump.stderr.decode())
        return

    # --- STEP 2: Compress with gzip ---
    with gzip.open(filename, "wb") as f:
        f.write(dump.stdout)

    # ============================================================
    # FIX 4: SET STRICT FILE PERMISSIONS ON THE BACKUP FILE
    # ============================================================
    # chmod 600 = only the file owner can read or write it
    # stat.S_IRUSR = owner read | stat.S_IWUSR = owner write
    # This prevents other users on the VM from reading backup data
    os.chmod(filename, stat.S_IRUSR | stat.S_IWUSR)

    # ============================================================
    # FIX 3: SCP WITH STRICT HOST KEY CHECKING
    # ============================================================
    # BEFORE (insecure — accepts any host, vulnerable to MITM):
    #   subprocess.run(["scp", filename, f"{TRACKER_VM}:/backups/"])
    #
    # AFTER (secure — only connects if host key is already known/trusted):
    #   -o StrictHostKeyChecking=yes  = reject connection if host key is unknown
    #   -o BatchMode=yes              = never prompt for passwords (use SSH keys)
    #
    # IMPORTANT: Before this works, run once manually to accept the host key:
    #   ssh oscar@192.168.1.10
    # After that, the key is stored in ~/.ssh/known_hosts and SCP will verify it.
    #
    # NOTE: Tracker VM (192.168.1.10) must be set up and running with SSH
    # configured before this backup transfer will work.
    # --- STEP 3: Transfer to remote backup server (if configured) ---
    if TRACKER_VM:
        scp_result = subprocess.run([
            "scp",
            "-o", "StrictHostKeyChecking=yes",   # reject unknown hosts
            "-o", "BatchMode=yes",                # no interactive prompts
            filename,
            f"{TRACKER_VM}:/backups/"
        ], capture_output=True)

        if scp_result.returncode != 0:
            send_alert("🚨 SCP Transfer FAILED", scp_result.stderr.decode())
            # Continue anyway — local backup is still saved
    else:
        print("ℹ️  No remote backup target configured — backup stored locally only")

    # --- STEP 4: Rotate backups older than 7 days ---
    for file in os.listdir(BACKUP_DIR):
        filepath = os.path.join(BACKUP_DIR, file)
        age = datetime.now() - datetime.fromtimestamp(os.path.getmtime(filepath))
        if age > timedelta(days=KEEP_DAYS):
            os.remove(filepath)
            print(f"🗑️  Deleted old backup: {file}")

    print(f"✅ Backup complete: {filename}")


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
    msg.set_content(body)

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
    backup_database()

# ============================================================
# SETUP REQUIRED BEFORE RUNNING:
#
# 1. Set environment variables (add to ~/.bashrc or cron environment):
#    export RENTADOG_DB_PASSWORD="your_db_password"
#
# 2. Set strict permissions on this script file (run in terminal):
#    chmod 700 script1_database_backup_secure.py
#    (only owner can read/write/execute — others cannot see the file)
#
# 3. Accept Tracker VM SSH host key (run once manually):
#    ssh oscar@192.168.1.10
#    ⚠️  Tracker VM (192.168.1.10) must be set up first!
#
# 4. Cron schedule (daily at 2AM with env var):
#    0 2 * * * RENTADOG_DB_PASSWORD="yourpass" python3 /scripts/script1_database_backup_secure.py
# ============================================================
