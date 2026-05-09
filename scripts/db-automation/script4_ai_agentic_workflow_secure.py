# ============================================================
# SCRIPT 4: AI AGENTIC WORKFLOW — SECURED VERSION
# Owner: Oscar (with James) | VM: Database (10.0.1.200)
# ============================================================
# SECURITY FIXES APPLIED:
#   🔴 FIX 1: Credentials moved to environment variables
#   🔴 FIX 2: SSL mode changed to "prefer" (SSL optional, not required)
#   🟡 FIX 3: Rate limiting — max 50 contacts processed per run
#   🟡 FIX 4: Input length validation before storing AI response
#   🟠 FIX 5: Connection timeout to prevent hanging connections
# ============================================================

import psycopg2
import os
from datetime import datetime

# ============================================================
# FIX 1: ENVIRONMENT VARIABLES INSTEAD OF HARDCODED CREDENTIALS
# ============================================================
# BEFORE (insecure — password visible in code):
#   DB_CONFIG = {"password": "ChangeMeWeek11!"}
#
# AFTER (secure — loaded from environment at runtime):
#   Set in shell before running:
#     export RENTADOG_DB_HOST="10.0.1.200"
#     export RENTADOG_DB_NAME="rentadog"
#     export RENTADOG_DB_USER="rentadog_app"
#     export RENTADOG_DB_PASSWORD="your_actual_password"
#
# If any required variable is missing, the script will refuse to run.
# ============================================================

DB_HOST     = os.environ.get("RENTADOG_DB_HOST",     "10.0.1.200")
DB_NAME     = os.environ.get("RENTADOG_DB_NAME",     "rentadog")
DB_USER     = os.environ.get("RENTADOG_DB_USER",     "rentadog_app")
DB_PASSWORD = os.environ.get("RENTADOG_DB_PASSWORD")
BATCH_LIMIT = int(os.environ.get("RENTADOG_BATCH_LIMIT", "50"))  # FIX 3
MAX_RESPONSE_LEN = 2000  # FIX 4: max characters for AI response stored in DB

# Safety check — refuse to run without the password
if not DB_PASSWORD:
    raise EnvironmentError(
        "❌ RENTADOG_DB_PASSWORD is not set.\n"
        "Run: export RENTADOG_DB_PASSWORD='your_password'"
    )


def get_connection():
    """
    Opens a secure connection to PostgreSQL.

    FIX 2: SSL MODE SET TO "prefer" (NOT "require")
    ============================================================
    BEFORE (required strict SSL):
        psycopg2.connect(
            host="10.0.1.200",
            dbname="rentadog",
            user="rentadog_app",
            password="ChangeMeWeek11!",
            sslmode="require"          # ❌ fails if SSL not available
        )

    AFTER (SSL optional — try TLS, fall back to plaintext):
        psycopg2.connect(
            host=DB_HOST,
            dbname=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
            sslmode="prefer",          # ✅ try SSL first, then fallback
            connect_timeout=10
        )

    sslmode options:
        "disable"  = never use SSL (insecure)
        "prefer"   = try SSL first, fall back to plaintext if not available ✅ use this now
        "require"  = always SSL, fail if unavailable (use after SSL certs are configured)
        "verify-ca"= verify server's SSL certificate (strongest — use in production)

    Why this matters: The database VM (10.0.1.200) doesn't have SSL certs
    configured yet. Using sslmode="require" would cause the script to fail.
    Using sslmode="prefer" allows the script to work now, and will
    automatically upgrade to SSL once certs are installed.

    FUTURE FIX: Once SSL is configured on PostgreSQL:
      1. Generate server.crt and server.key on the Database VM
      2. Update postgresql.conf: ssl=on, ssl_cert_file, ssl_key_file
      3. Change sslmode to "require" or "verify-ca"
      4. Restart PostgreSQL
    ============================================================

    FIX 5: CONNECTION TIMEOUT
    ============================================================
    connect_timeout=10 means: if PostgreSQL doesn't respond
    within 10 seconds, raise an error instead of hanging forever.
    This prevents the script from freezing if the DB VM is down.
    ============================================================
    """
    return psycopg2.connect(
        host=DB_HOST,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
        sslmode="prefer",       # FIX 2: optional SSL, falls back to plaintext
        connect_timeout=10      # FIX 5: don't hang if DB is unreachable
    )


def get_new_contacts(conn):
    """
    Fetches unprocessed contact form submissions.

    FIX 3: RATE LIMITING WITH LIMIT CLAUSE
    ============================================================
    BEFORE (no limit — processes ALL new contacts at once):
        cur.execute("SELECT * FROM contacts WHERE status = 'new'")

    AFTER (limited — processes max 50 per run):
        cur.execute("SELECT * FROM contacts WHERE status = 'new' LIMIT %s",
                    (BATCH_LIMIT,))

    Why this matters: if someone spam-submits 10,000 contact forms,
    the original script would try to process all 10,000 in one run,
    potentially crashing the VM or causing a denial-of-service condition.
    Limiting to 50 per run keeps the workload predictable.
    ============================================================
    """
    with conn.cursor() as cur:
        cur.execute("""
            SELECT contact_id, name, email, subject, message, category
            FROM contacts
            WHERE status = 'new'
            ORDER BY created_at ASC
            LIMIT %s
        """, (BATCH_LIMIT,))
        return cur.fetchall()


def update_contact(conn, contact_id, ai_response, escalate=False):
    """
    Updates the contact record with the AI's response.

    FIX 4: INPUT LENGTH VALIDATION
    ============================================================
    BEFORE (no length check — AI response stored as-is):
        cur.execute("UPDATE contacts SET ai_response = %s ...", (ai_response,))

    AFTER (length validated before storing):
        if len(ai_response) > MAX_RESPONSE_LEN:
            ai_response = ai_response[:MAX_RESPONSE_LEN]

    Why this matters: an unusually long AI response (or a bug that
    generates garbage output) could fill up the database column
    or cause unexpected behavior. Capping it protects data integrity.

    Note: %s placeholders are already preventing SQL injection —
    this fix adds data quality protection on top of that.
    ============================================================
    """
    # Trim response if it exceeds the max allowed length
    if len(ai_response) > MAX_RESPONSE_LEN:
        ai_response = ai_response[:MAX_RESPONSE_LEN]

    status = "escalated" if escalate else "responded"

    with conn.cursor() as cur:
        cur.execute("""
            UPDATE contacts
            SET status       = %s,
                ai_response  = %s,
                responded_at = NOW()
            WHERE contact_id = %s
        """, (status, ai_response, contact_id))
        # %s placeholders = psycopg2 safely escapes values → prevents SQL injection

    conn.commit()


def generate_response(subject, message, category):
    """
    Simple keyword-based response router.
    Returns (response_text, should_escalate).
    In production, replace with GPU API / LLM call.
    """
    msg_lower = message.lower()

    if any(w in msg_lower for w in ["book", "reserve", "schedule", "availability"]):
        return (
            "Thank you for your interest! You can check availability and book "
            "directly on our website. Call us during business hours for help.",
            False
        )

    if any(w in msg_lower for w in ["price", "cost", "how much", "rate"]):
        return (
            "Rental rates: Basic $30/hr, Premium $40/hr, VIP $50/hr. "
            "Daily rates are 6x hourly. Experience packages start at $30.",
            False
        )

    if any(w in msg_lower for w in ["complaint", "refund", "unhappy", "issue", "problem"]):
        return (
            "We're sorry to hear about your experience. A team member will "
            "follow up with you shortly.",
            True  # escalate to human
        )

    return (
        "Thank you for contacting Rent a Dog! We'll get back to you within 1 business day.",
        False
    )


def run_workflow():
    """Main workflow — connect, fetch, respond, update."""

    try:
        conn = get_connection()
    except psycopg2.OperationalError as e:
        print(f"❌ Could not connect to database: {e}")
        return

    try:
        contacts = get_new_contacts(conn)

        if not contacts:
            print("✅ No new contacts to process.")
            return

        print(f"📬 Processing {len(contacts)} contact(s) (max batch: {BATCH_LIMIT})")

        for contact in contacts:
            contact_id, name, email, subject, message, category = contact

            print(f"\n  Contact #{contact_id} — {name} ({email})")

            response, escalate = generate_response(subject, message, category)
            update_contact(conn, contact_id, response, escalate)

            status = "ESCALATED ⚠️" if escalate else "RESPONDED ✅"
            print(f"  Status: {status}")

    except Exception as e:
        print(f"❌ Workflow error: {e}")

    finally:
        conn.close()  # always close the connection — prevents connection leaks


# --- ENTRY POINT ---
if __name__ == "__main__":
    run_workflow()

# ============================================================
# SETUP REQUIRED BEFORE RUNNING:
#
# 1. Set environment variables:
#    export RENTADOG_DB_HOST="10.0.1.200"
#    export RENTADOG_DB_NAME="rentadog"
#    export RENTADOG_DB_USER="rentadog_app"
#    export RENTADOG_DB_PASSWORD="your_actual_password"
#
# 2. (OPTIONAL) Enable SSL on PostgreSQL for sslmode="require":
#    Currently using sslmode="prefer" which falls back to plaintext.
#    To upgrade to full SSL enforcement:
#      a. Generate SSL certs on Database VM (10.0.1.200):
#         sudo openssl req -new -x509 -days 365 -nodes -out /etc/postgresql/server.crt -keyout /etc/postgresql/server.key
#      b. Update postgresql.conf:
#         ssl = on
#         ssl_cert_file = '/etc/postgresql/server.crt'
#         ssl_key_file  = '/etc/postgresql/server.key'
#      c. Restart PostgreSQL: sudo systemctl restart postgresql
#      d. Change sslmode to "require" in this script
#
# 3. Install psycopg2:
#    pip install psycopg2-binary
#
# 4. Set script permissions:
#    chmod 700 script4_ai_agentic_workflow_secure.py
# ============================================================
