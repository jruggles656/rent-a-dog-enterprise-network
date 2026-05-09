# Rent a Dog — Deployment & Integration Guide

**Purpose:** Step-by-step plan for taking the locally-built website and deploying it onto the Cyberlab enterprise network, integrating with all required services, and meeting every grading requirement.

**Current Date:** February 22, 2026
**Current State:** Website fully built and working locally (PHP dev server on localhost:8000). No database, no server deployment, no AI API integration yet.

---

## Timeline Overview

| When | What | Depends On | Owner |
|------|------|-----------|-------|
| **Weeks 3-4** | Firewall + routing working | Jason configures OPNsense | Jason |
| **Weeks 3-4** | PostgreSQL installed + schema created | Oscar sets up Database VM | Oscar |
| **Week 5** | Apache (XAMPP) on WebServer | Firewall rules in place | James ✅ DONE 3/18 |
| **Week 5** | Deploy website files to WebServer | XAMPP running | James ✅ DONE 3/18 |
| **Week 6** | Switch from hardcoded data to PostgreSQL | DB schema + firewall rule (5432) | James + Oscar |
| **Week 6** | Email server (Postfix/Dovecot) | WebServer accessible | James |
| **Weeks 7-8** | Bark Bot GPU API integration | GPU API credentials from Dr. Nestler | James |
| **Weeks 7-8** | Agentic workflow deployment | PostgreSQL + Email working | James + Oscar |
| **Week 8** | AD user accounts for staff | AD/DNS configured | Allegra |
| **Weeks 9-10** | Zabbix monitoring | Tracker VM configured | Allegra |
| **Weeks 11-12** | Security hardening + credential changes | Everything deployed | ALL |
| **Week 12** | Self-pentest from Kali | Everything hardened | ALL |
| **Weeks 13-14** | IST 4620 pentest — be ready | Everything locked down | ALL |
| **Week 15** | Final presentation | Remediation report done | ALL |

---

## Phase 1: Prerequisites (Weeks 3-4) — BEFORE You Can Deploy

These must happen BEFORE the website can go live. You can't do anything on the WebServer until Jason's firewall rules allow traffic.

### 1A. Firewall Rules (Jason)

Jason must configure these OPNsense rules or the WebServer is unreachable:

| Rule | Source | Destination | Port | Why |
|------|--------|-------------|------|-----|
| **Critical** | LAN (192.168.1.0/24) | DMZ (10.0.1.0/24) | TCP 80, 443 | Workstations access website |
| **Critical** | WAN (172.31.0.0/24) | DMZ (10.0.1.0/24) | TCP 80, 443 | External access to website |
| **Critical** | DMZ WebServer (10.0.1.100) | DMZ Database (10.0.1.200) | TCP 5432 | PHP connects to PostgreSQL |
| **Critical** | DMZ (10.0.1.0/24) | LAN (192.168.1.0/24) | TCP 53 | DNS resolution via AD |
| **Email** | LAN (192.168.1.0/24) | DMZ (10.0.1.0/24) | TCP 25, 587, 993 | Send/receive email |
| **GPU API** | DMZ WebServer (10.0.1.100) | External/GPU API | TCP 443 | Bark Bot calls GPU API |
| **SSH** | LAN (192.168.1.0/24) | DMZ (10.0.1.0/24) | TCP 22 | SSH into WebServer for deployment |

**Action item for James:** Message Jason and say: "I need SSH (22), HTTP (80/443), and PostgreSQL (5432) rules between LAN→DMZ and WebServer→Database before I can deploy. Also need outbound 443 from WebServer for the GPU API."

### 1B. PostgreSQL Setup (Oscar)

Oscar must do this on the Database VM (10.0.1.200):

1. Install PostgreSQL 15
2. Create database: `rentadog`
3. Create app user: `rentadog_app` (NOT the postgres superuser)
4. Create all 8 tables (schema is in Guide 2 and the Blueprint)
5. Insert sample data (8 dogs, 3 experiences)
6. Configure `pg_hba.conf` to allow connections from 10.0.1.100 (WebServer)
7. Configure `postgresql.conf`: `listen_addresses = 'localhost, 10.0.1.200'`

**The 8 tables Oscar needs to create:**

```sql
-- Group 1: Core Business
CREATE TABLE breeds (
    breed_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tier VARCHAR(10) CHECK (tier IN ('Basic', 'Premium', 'VIP')),
    hourly_rate DECIMAL(6,2) NOT NULL,
    description TEXT
);

CREATE TABLE dogs (
    dog_id SERIAL PRIMARY KEY,
    breed_id INTEGER REFERENCES breeds(breed_id),
    name VARCHAR(100) NOT NULL,
    age INTEGER,
    weight DECIMAL(5,1),
    status VARCHAR(20) DEFAULT 'available' CHECK (status IN ('available', 'rented', 'resting')),
    photo_url TEXT
);

CREATE TABLE experience_packages (
    package_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(6,2) NOT NULL,
    duration_minutes INTEGER NOT NULL
);

-- Group 2: Customers & Orders
CREATE TABLE customers (
    customer_id SERIAL PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    order_id SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(customer_id),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(8,2),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled'))
);

CREATE TABLE order_items (
    item_id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(order_id),
    item_type VARCHAR(20) CHECK (item_type IN ('rental', 'experience')),
    dog_id INTEGER REFERENCES dogs(dog_id),
    package_id INTEGER REFERENCES experience_packages(package_id),
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(6,2),
    rental_hours INTEGER
);

-- Group 3: Scheduling
CREATE TABLE availability (
    slot_id SERIAL PRIMARY KEY,
    dog_id INTEGER REFERENCES dogs(dog_id),
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_booked BOOLEAN DEFAULT FALSE
);

-- Group 4: Admin/Audit
CREATE TABLE activity_log (
    log_id SERIAL PRIMARY KEY,
    user_type VARCHAR(20),
    user_id INTEGER,
    action VARCHAR(100),
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Additional table for AI agentic workflow
CREATE TABLE contacts (
    contact_id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200),
    message TEXT,
    category VARCHAR(50) DEFAULT 'uncategorized',
    ai_response TEXT,
    status VARCHAR(20) DEFAULT 'new' CHECK (status IN ('new', 'auto_responded', 'escalated', 'resolved')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP
);
```

**Sample data Oscar needs to insert:**

```sql
-- Breeds (matches data.php)
INSERT INTO breeds (name, tier, hourly_rate, description) VALUES
('Labrador Retriever', 'Basic', 15.00, 'Friendly and outgoing family dog'),
('Beagle', 'Basic', 15.00, 'Curious and merry scent hound'),
('Poodle', 'Basic', 18.00, 'Intelligent and elegant companion'),
('Golden Retriever', 'Premium', 25.00, 'Devoted and gentle sporting dog'),
('Siberian Husky', 'Premium', 28.00, 'Loyal and outgoing working dog'),
('Corgi', 'Premium', 25.00, 'Alert and affectionate herding dog'),
('French Bulldog', 'VIP', 40.00, 'Playful and adaptable companion'),
('Samoyed', 'VIP', 45.00, 'Gentle and adaptable fluffy companion');

-- Dogs (matches data.php)
INSERT INTO dogs (breed_id, name, age, weight, status, photo_url) VALUES
(1, 'Biscuit', 3, 65.0, 'available', '/images/biscuit.jpg'),
(2, 'Pepper', 2, 22.0, 'available', '/images/pepper.jpg'),
(3, 'Maple', 4, 45.0, 'available', '/images/maple.jpg'),
(4, 'Sunny', 2, 70.0, 'available', '/images/sunny.jpg'),
(5, 'Luna', 3, 50.0, 'rented', '/images/luna.jpg'),
(6, 'Chester', 1, 28.0, 'available', '/images/chester.jpg'),
(7, 'Mochi', 2, 24.0, 'available', '/images/mochi.jpg'),
(8, 'Cloud', 3, 55.0, 'available', '/images/cloud.jpg');

-- Experiences (matches data.php)
INSERT INTO experience_packages (name, description, price, duration_minutes) VALUES
('Dog Cafe', 'Enjoy coffee and pastries with a furry companion in our cozy indoor cafe.', 35.00, 90),
('Dog Yoga', 'Find your zen with a gentle pup by your side in our outdoor yoga sessions.', 45.00, 60),
('Dog Garden', 'Enjoy a private garden session with your chosen dog — play fetch, relax, or just enjoy the sunshine.', 30.00, 120);
```

**Action item for James:** Send Oscar this schema + sample data and say: "Here's the exact schema and data for the rentadog database. The table names and column names need to match exactly because the PHP code will query against them."

---

## Phase 2: WebServer Setup (Week 5) — Installing Nginx + PHP

Once Jason's firewall rules are in place and you can SSH into the WebServer:

### Step 1: SSH into WebServer

```bash
ssh james@10.0.1.100
# (from ClientDesktop2 at 192.168.1.101, or from your laptop if VPN/routing works)
```

### Step 2: Install Nginx + PHP-FPM

```bash
# On RHEL (WebServer VM)
sudo dnf install nginx php-fpm php-pgsql php-json php-mbstring php-curl -y
sudo systemctl enable --now nginx
sudo systemctl enable --now php-fpm
```

### Step 3: Configure Nginx

Create `/etc/nginx/conf.d/rentadog.conf`:

```nginx
server {
    listen 80;
    server_name rentadog.local 10.0.1.100;

    root /var/www/rentadog;
    index index.php;

    # Serve static files directly
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 7d;
        access_log off;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(ht|git|claude) {
        deny all;
    }

    # Deny access to agents directory (Python scripts)
    location /agents/ {
        deny all;
    }
}
```

### Step 4: Create site directory and set permissions

```bash
sudo mkdir -p /var/www/rentadog
sudo chown -R nginx:nginx /var/www/rentadog
sudo chmod -R 755 /var/www/rentadog
```

### Step 5: Configure SELinux (RHEL-specific)

```bash
sudo setsebool -P httpd_can_network_connect_db 1   # PHP can connect to PostgreSQL
sudo setsebool -P httpd_can_network_connect 1       # PHP can make outbound HTTP (GPU API)
sudo setsebool -P httpd_can_sendmail 1              # PHP can send email
sudo chcon -R -t httpd_sys_content_t /var/www/rentadog/
```

### Step 6: Test Nginx

```bash
sudo nginx -t           # Check config syntax
sudo systemctl restart nginx
curl http://localhost    # Should get a response
```

---

## Phase 3: Deploy Website Files (Week 5)

### What to copy

Copy the entire `rentadog/` directory contents to `/var/www/rentadog/` on the WebServer.

### How to copy (pick one method)

**Option A: SCP from your laptop** (if routing works)
```bash
scp -r /path/to/rentadog/* james@10.0.1.100:/var/www/rentadog/
```

**Option B: SCP via jump through ClientDesktop2**
```bash
# From your laptop → ClientDesktop2 → WebServer
scp -r /path/to/rentadog/* james@192.168.1.101:/tmp/rentadog/
# Then SSH into ClientDesktop2 and SCP to WebServer
ssh james@192.168.1.101
scp -r /tmp/rentadog/* james@10.0.1.100:/var/www/rentadog/
```

**Option C: Git** (if you push to a repo)
```bash
# On WebServer
cd /var/www/rentadog
git clone <your-repo-url> .
```

### What NOT to copy

Exclude these from deployment:
- `CLAUDE.md` — dev notes only
- `RENTADOG_UI_DESIGN_PLAN.md` — design spec only
- `DEPLOYMENT_GUIDE.md` — this file
- `.claude/` — Claude Code config
- Any `.md` files in the root

### Files that go to the server

```
/var/www/rentadog/
├── index.php
├── css/style.css
├── js/main.js
├── js/barkbot.js
├── images/  (all image files)
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── data.php          ← will be modified to use DB
│   └── cart-actions.php
├── api/
│   ├── chat.php           ← will be modified for GPU API
│   └── contact_handler.php ← will be modified for DB
├── pages/
│   ├── breeds.php
│   ├── experience.php
│   ├── checkout.php
│   ├── confirmation.php
│   ├── about.php
│   └── contact.php
└── agents/
    └── customer_service.py  ← cron job, not web-accessible
```

### Post-deploy verification

From a workstation (ClientDesktop1 or ClientDesktop2), open a browser to:
- `http://10.0.1.100` — homepage should load
- `http://10.0.1.100/pages/breeds.php` — breeds page
- Click through all pages, test cart, test theme toggle

**This satisfies grading requirement #4 (Web Management):** "Access website from browser" ✓

---

## Phase 4: Database Integration (Week 6) — The Big Switch

This is the most important code change. You're switching from hardcoded arrays in `data.php` to live PostgreSQL queries.

### Step 1: Create the database connection file

Create a NEW file: `includes/db.php`

```php
<?php
$db_host = '10.0.1.200';
$db_port = '5432';
$db_name = 'rentadog';
$db_user = 'rentadog_app';
$db_pass = 'CHANGE_ME_BEFORE_DEPLOY';  // Oscar will set this

try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}
?>
```

### Step 2: Modify `includes/data.php`

Replace the hardcoded `$dogs` and `$experiences` arrays with database queries. Keep the cart helper functions — they stay the same (session-based).

**What changes:**

```php
// OLD (hardcoded):
$dogs = [
    ['id' => 1, 'name' => 'Biscuit', ...],
    ...
];

// NEW (database):
require_once __DIR__ . '/db.php';

$stmt = $pdo->query("
    SELECT d.dog_id as id, d.name, b.name as breed, b.tier,
           b.hourly_rate as price, d.status, d.photo_url as image,
           b.description as personality
    FROM dogs d
    JOIN breeds b ON d.breed_id = b.breed_id
    ORDER BY b.tier, d.name
");
$dogs = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT package_id as id, name, description, price, duration_minutes as duration
    FROM experience_packages
    ORDER BY package_id
");
$experiences = $stmt->fetchAll();
```

**Important:** The column aliases (`as id`, `as breed`, etc.) must match what the existing PHP templates and JavaScript expect. Check every `$dog['key']` reference in your templates and make sure the aliases line up.

### Step 3: Modify `checkout.php` to save orders to DB

Currently checkout is display-only. Add order creation:

```php
// In checkout.php, when form is submitted:
require_once __DIR__ . '/../includes/db.php';

// Create customer (or find existing)
$stmt = $pdo->prepare("
    INSERT INTO customers (first_name, last_name, email, phone)
    VALUES (:first, :last, :email, :phone)
    ON CONFLICT (email) DO UPDATE SET phone = :phone
    RETURNING customer_id
");
$stmt->execute([
    ':first' => $_POST['first_name'],
    ':last'  => $_POST['last_name'],
    ':email' => $_POST['email'],
    ':phone' => $_POST['phone']
]);
$customer_id = $stmt->fetchColumn();

// Create order
$stmt = $pdo->prepare("
    INSERT INTO orders (customer_id, total_amount, status)
    VALUES (:cid, :total, 'confirmed')
    RETURNING order_id
");
$stmt->execute([':cid' => $customer_id, ':total' => $cart_total]);
$order_id = $stmt->fetchColumn();

// Insert order items
foreach ($_SESSION['cart'] as $item) {
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, item_type, dog_id, package_id, unit_price, rental_hours, quantity)
        VALUES (:oid, :type, :did, :pid, :price, :hours, :qty)
    ");
    $stmt->execute([
        ':oid'   => $order_id,
        ':type'  => $item['type'],       // 'rental' or 'experience'
        ':did'   => $item['dog_id'] ?? null,
        ':pid'   => $item['package_id'] ?? null,
        ':price' => $item['price'],
        ':hours' => $item['hours'] ?? null,
        ':qty'   => $item['quantity'] ?? 1
    ]);
}

// Clear cart after successful order
$_SESSION['cart'] = [];
```

### Step 4: Modify `contact_handler.php` to save to DB

Currently the contact form handler doesn't store anything. Change it to insert into the `contacts` table:

```php
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->prepare("
    INSERT INTO contacts (name, email, subject, message)
    VALUES (:name, :email, :subject, :message)
");
$stmt->execute([
    ':name'    => htmlspecialchars($_POST['name']),
    ':email'   => htmlspecialchars($_POST['email']),
    ':subject' => htmlspecialchars($_POST['subject']),
    ':message' => htmlspecialchars($_POST['message'])
]);
```

This is critical because the **agentic workflow** reads from this `contacts` table.

### Step 5: Test database queries

**This satisfies grading requirement #5 (Database Management):**
- "Access database interface" — Oscar demonstrates psql or pgAdmin ✓
- "Query database for list of goods" — The website itself queries breeds/dogs/experiences ✓

**Queries the grader might ask Oscar to run:**

```sql
-- List all goods (dogs by tier)
SELECT b.name as breed, b.tier, b.hourly_rate, d.name as dog_name, d.status
FROM dogs d JOIN breeds b ON d.breed_id = b.breed_id
ORDER BY b.tier, b.hourly_rate;

-- List all services (experiences)
SELECT name, price, duration_minutes FROM experience_packages;

-- Show recent orders with items
SELECT o.order_id, c.email, o.total_amount, o.status, o.order_date,
       oi.item_type, oi.unit_price
FROM orders o
JOIN customers c ON o.customer_id = c.customer_id
JOIN order_items oi ON o.order_id = oi.order_id
ORDER BY o.order_date DESC;

-- Check dog availability
SELECT d.name, d.status, b.tier
FROM dogs d JOIN breeds b ON d.breed_id = b.breed_id
WHERE d.status = 'available';
```

---

## Phase 5: Email Server (Week 6) — Postfix + Dovecot

### What to set up on WebServer (10.0.1.100)

```bash
sudo dnf install postfix dovecot -y
sudo systemctl enable --now postfix
sudo systemctl enable --now dovecot
```

### Create email accounts for staff

```bash
# Create system users for email
sudo useradd -m james.ruggles
sudo useradd -m jason.cortez
sudo useradd -m oscar.ponce
sudo useradd -m allegra.ramirez
sudo passwd james.ruggles   # Set passwords
# (repeat for each)
```

### Postfix config (`/etc/postfix/main.cf`)

Key settings:
```
myhostname = mail.rentadog.local
mydomain = rentadog.local
myorigin = $mydomain
inet_interfaces = all
mydestination = $myhostname, localhost.$mydomain, localhost, $mydomain
mynetworks = 10.0.1.0/24, 192.168.1.0/24, 127.0.0.0/8
```

### Dovecot config (`/etc/dovecot/dovecot.conf`)

Key settings:
```
protocols = imap
listen = *
mail_location = maildir:~/Maildir
```

### DNS records needed (AD/DNS server — Allegra)

```
rentadog.local.     A       10.0.1.100
mail.rentadog.local. A      10.0.1.100
rentadog.local.     MX  10  mail.rentadog.local.
```

### Test from workstations

On ClientDesktop1 (Windows 11):
- Install Thunderbird
- Configure account: `james.ruggles@rentadog.local`
- IMAP server: `10.0.1.100`
- SMTP server: `10.0.1.100`
- Send email to `oscar.ponce@rentadog.local`

**This satisfies grading requirement #6 (Email):**
- "Access the email server admin interface" ✓
- "Create a new user. Test email account." ✓
- "Compose and send email to other user. Check that it is received." ✓

---

## Phase 6: AI Infrastructure (Weeks 7-8) — GPU API + Agentic Workflow

This covers grading requirement #7 (AI Infrastructure) — the NEW requirement from Dr. Nestler.

### 6A. Bark Bot GPU API Integration

**Current state:** Bark Bot works with keyword-based responses in `api/chat.php`. Need to swap in real GPU API calls.

**What needs to happen:**

1. Get GPU API credentials from Dr. Nestler or lab admin
2. Get the API endpoint URL (likely something like `https://gpu.cyberlab.csusb.edu/v1/chat/completions`)
3. Ask Jason to add firewall rule: WebServer (10.0.1.100) → GPU API endpoint on port 443

**Modify `api/chat.php`:**

```php
<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

// System prompt with Rent a Dog business knowledge
$system_prompt = "You are Bark Bot, the friendly AI assistant for Rent a Dog.

BUSINESS INFO:
- We rent dogs by tier: Basic ($15-18/hr), Premium ($25-28/hr), VIP ($40-45/hr)
- Basic: Labrador, Beagle, Poodle
- Premium: Golden Retriever, Husky, Corgi
- VIP: French Bulldog, Samoyed
- Experiences: Dog Cafe ($35, 90 min), Dog Yoga ($45, 60 min), Dog Garden ($30, 120 min)
- Hours: 9 AM - 7 PM daily
- Location: Downtown area
- All dogs are vaccinated, trained, and friendly

PERSONALITY: Enthusiastic, helpful, uses occasional dog puns. Keep responses concise (2-3 sentences max).";

// Call Cyberlab GPU API
$api_url = 'GPU_API_ENDPOINT_HERE';  // Replace with actual URL
$api_key = 'GPU_API_KEY_HERE';       // Replace with actual key

$payload = [
    'model' => 'MODEL_NAME_HERE',    // Replace with actual model
    'messages' => [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $message]
    ],
    'max_tokens' => 200,
    'temperature' => 0.7
];

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    $reply = $data['choices'][0]['message']['content'] ?? 'Woof! Something went wrong.';
} else {
    // Fallback to keyword-based responses if API is down
    $reply = fallback_response($message);
}

echo json_encode(['reply' => $reply]);

function fallback_response($msg) {
    $msg = strtolower($msg);
    if (strpos($msg, 'price') !== false || strpos($msg, 'cost') !== false) {
        return 'Our tiers start at $15/hr (Basic), $25/hr (Premium), and $40/hr (VIP)!';
    }
    if (strpos($msg, 'experience') !== false) {
        return 'We offer Dog Cafe ($35), Dog Yoga ($45), and Dog Garden ($30)!';
    }
    return "Woof! I'd love to help. Ask me about our dogs, pricing, or experiences!";
}
```

**This satisfies:**
- "Deploy at least one AI-powered chatbot on customer-facing website" ✓
- "Make API calls to Cyberlab's GPU infrastructure for AI model inference" ✓

### 6B. Agentic Workflow — Customer Service Automation

**File:** `agents/customer_service.py`
**Runs as:** Cron job every 5 minutes on WebServer
**What it does:**
1. Reads new (unprocessed) contact form submissions from `contacts` table
2. Sends each message to GPU API for categorization
3. Auto-responds to simple questions via email (Postfix)
4. Flags complex issues for staff review

**The Python script:**

```python
#!/usr/bin/env python3
"""
Rent a Dog — Customer Service Agentic Workflow
Runs via cron: */5 * * * * /usr/bin/python3 /var/www/rentadog/agents/customer_service.py

Reads new contact submissions → categorizes with AI → auto-responds or escalates.
"""

import psycopg2
import json
import smtplib
import requests
from email.mime.text import MIMEText
from datetime import datetime

# --- Configuration ---
DB_CONFIG = {
    'host': '10.0.1.200',
    'port': 5432,
    'dbname': 'rentadog',
    'user': 'rentadog_app',
    'password': 'CHANGE_ME'
}

GPU_API_URL = 'GPU_API_ENDPOINT_HERE'
GPU_API_KEY = 'GPU_API_KEY_HERE'
GPU_MODEL = 'MODEL_NAME_HERE'

SMTP_HOST = 'localhost'  # Postfix on same server
FROM_EMAIL = 'support@rentadog.local'
STAFF_EMAIL = 'james.ruggles@rentadog.local'

# --- Database ---
def get_new_contacts(conn):
    """Fetch unprocessed contact submissions."""
    cur = conn.cursor()
    cur.execute("""
        SELECT contact_id, name, email, subject, message
        FROM contacts
        WHERE status = 'new'
        ORDER BY created_at ASC
        LIMIT 10
    """)
    rows = cur.fetchall()
    cur.close()
    return [
        {'id': r[0], 'name': r[1], 'email': r[2], 'subject': r[3], 'message': r[4]}
        for r in rows
    ]

def update_contact(conn, contact_id, category, ai_response, status):
    """Update contact with AI categorization and response."""
    cur = conn.cursor()
    cur.execute("""
        UPDATE contacts
        SET category = %s, ai_response = %s, status = %s, responded_at = %s
        WHERE contact_id = %s
    """, (category, ai_response, status, datetime.now(), contact_id))
    conn.commit()
    cur.close()

# --- AI Categorization ---
def categorize_with_ai(subject, message):
    """Send to GPU API for categorization + draft response."""
    prompt = f"""You are a customer service AI for Rent a Dog (dog rental business).

Analyze this customer inquiry and respond with valid JSON only:

Subject: {subject}
Message: {message}

Respond with JSON:
{{
    "category": "pricing|availability|booking|complaint|general",
    "can_auto_respond": true/false,
    "draft_response": "your helpful response here",
    "confidence": 0.0-1.0
}}

Auto-respond if it's a simple question about pricing, hours, or services.
Escalate if it's a complaint, complex booking issue, or needs human judgment."""

    try:
        resp = requests.post(
            GPU_API_URL,
            headers={
                'Content-Type': 'application/json',
                'Authorization': f'Bearer {GPU_API_KEY}'
            },
            json={
                'model': GPU_MODEL,
                'messages': [{'role': 'user', 'content': prompt}],
                'max_tokens': 300,
                'temperature': 0.3
            },
            timeout=30
        )
        if resp.status_code == 200:
            content = resp.json()['choices'][0]['message']['content']
            return json.loads(content)
    except Exception as e:
        print(f"AI categorization failed: {e}")

    # Fallback: escalate everything if AI fails
    return {
        'category': 'general',
        'can_auto_respond': False,
        'draft_response': '',
        'confidence': 0.0
    }

# --- Email ---
def send_email(to_addr, subject, body):
    """Send email via local Postfix."""
    msg = MIMEText(body)
    msg['From'] = FROM_EMAIL
    msg['To'] = to_addr
    msg['Subject'] = subject

    try:
        with smtplib.SMTP(SMTP_HOST, 25) as server:
            server.send_message(msg)
        return True
    except Exception as e:
        print(f"Email send failed: {e}")
        return False

def send_auto_response(contact, ai_result):
    """Send auto-response to customer."""
    body = f"""Hi {contact['name']},

Thank you for reaching out to Rent a Dog!

{ai_result['draft_response']}

If you have any more questions, feel free to reply or call us during business hours (9 AM - 7 PM).

Best regards,
The Rent a Dog Team
"""
    send_email(contact['email'], f"Re: {contact['subject']}", body)

def send_escalation(contact, ai_result):
    """Alert staff about complex inquiry."""
    body = f"""[ESCALATED CONTACT - Needs Human Review]

From: {contact['name']} ({contact['email']})
Subject: {contact['subject']}
Category: {ai_result['category']}
Confidence: {ai_result['confidence']}

Original Message:
{contact['message']}

AI Draft (not sent to customer):
{ai_result['draft_response']}
"""
    send_email(STAFF_EMAIL, f"[ESCALATED] {contact['subject']}", body)

# --- Main Workflow ---
def main():
    conn = psycopg2.connect(**DB_CONFIG)
    contacts = get_new_contacts(conn)

    if not contacts:
        return  # Nothing to process

    for contact in contacts:
        ai_result = categorize_with_ai(contact['subject'], contact['message'])

        if ai_result['can_auto_respond'] and ai_result['confidence'] >= 0.8:
            # Auto-respond to simple queries
            send_auto_response(contact, ai_result)
            update_contact(conn, contact['id'], ai_result['category'],
                         ai_result['draft_response'], 'auto_responded')
            print(f"Auto-responded to contact #{contact['id']}: {ai_result['category']}")
        else:
            # Escalate to staff
            send_escalation(contact, ai_result)
            update_contact(conn, contact['id'], ai_result['category'],
                         ai_result['draft_response'], 'escalated')
            print(f"Escalated contact #{contact['id']}: {ai_result['category']}")

    conn.close()

if __name__ == '__main__':
    main()
```

**Set up the cron job on WebServer:**

```bash
# Install Python dependencies
sudo pip3 install psycopg2-binary requests

# Add cron job
crontab -e
# Add this line:
*/5 * * * * /usr/bin/python3 /var/www/rentadog/agents/customer_service.py >> /var/log/rentadog-agent.log 2>&1
```

**This satisfies:**
- "Create at least one agentic workflow that can make decisions and take actions autonomously" ✓
- The workflow autonomously: reads contacts → categorizes with AI → decides to auto-respond or escalate → takes action (sends email)

---

## Phase 7: Security Fixes (Weeks 11-12) — Before Pentest

These are the known security issues from the CLAUDE.md that MUST be fixed before the IST 4620 pentest in weeks 13-14.

### HIGH Priority — XSS Vulnerabilities

**Fix `renderCartItems()` in `main.js` (~lines 398-420):**
Replace all `innerHTML` with safe DOM manipulation or use `textContent`.

```javascript
// BAD (current):
cartItem.innerHTML = `<span>${item.name}</span>`;

// GOOD (fix):
const span = document.createElement('span');
span.textContent = item.name;
cartItem.appendChild(span);
```

**Fix `showToast()` in `main.js` (~lines 450-453):**
```javascript
// BAD:
toast.innerHTML = message;

// GOOD:
toast.textContent = message;
```

### MEDIUM Priority — CSRF Protection

Add CSRF tokens to all forms:

```php
// In session start (data.php or header.php):
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In every form:
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// In every form handler:
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid request');
}
```

### MEDIUM Priority — POST-only cart actions

In `cart-actions.php`, reject GET requests:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}
```

### LOW Priority — Session hardening

Add to the top of `data.php` (or wherever session_start is called):

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);    // Only if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
```

### Credential Changes (ALL team members)

Before week 13, change EVERY default password:
- [ ] OPNsense: `root/opnsense` → strong password
- [ ] VyOS routers: `vyos/vyos` → strong password
- [ ] PostgreSQL: `postgres/postgres` → strong password
- [ ] Zabbix: `Admin/zabbix` → strong password
- [ ] All RHEL SSH passwords
- [ ] Windows AD admin passwords
- [ ] `rentadog_app` database user password

---

## Phase 8: What the Grader Will Check (All 12 Requirements)

Here's exactly what gets assessed and how your project covers each one:

### 1. Business Concept ✅
- **Measure:** "List of items submitted"
- **Your answer:** 3 goods (Basic/Premium/VIP dog rentals) + 3 services (Dog Cafe/Dog Yoga/Dog Garden)
- **Where it shows:** Homepage tier cards, breeds page, experience pages

### 2. Project Management
- **Measure:** Gantt chart, Visio diagram, CSET report, Kanban board
- **Action needed:** Create Gantt chart (MS Project), Visio network diagram, run CSET tool, maintain Kanban_Tasks.md
- **Owner:** Shared — assign someone to each deliverable

### 3. Account Management
- **Measure:** Log in as admin, user, and guest
- **Action needed:** Create AD accounts (admin.ruggles, staff.cortez, guest) on AD/DNS server
- **Owner:** Allegra (AD/DNS lead)

### 4. Web Management ✅
- **Measure:** "Access website from browser", "access shopping cart", "purchase a good", "purchase a service"
- **Your answer:** Website at 10.0.1.100, cart works, can add dogs (goods) and experiences (services) to cart and checkout
- **Demo:** Open browser → browse dogs → add to cart → checkout → order confirmation

### 5. Database Management ✅ (after Phase 4)
- **Measure:** "Access database interface", "Query database for list of goods"
- **Your answer:** psql on Database VM, run `SELECT * FROM breeds; SELECT * FROM dogs; SELECT * FROM experience_packages;`
- **Owner:** Oscar demonstrates

### 6. Email ✅ (after Phase 5)
- **Measure:** "Access email admin", "create user", "send email between users"
- **Your answer:** Postfix/Dovecot on WebServer, Thunderbird on workstations, send test email
- **Demo:** james.ruggles@rentadog.local → oscar.ponce@rentadog.local

### 7. AI Infrastructure ✅ (after Phase 6)
- **Measure:** AI chatbot deployed, GPU API calls, agentic workflow
- **Your answer:** Bark Bot on every page (chatbot + GPU API), customer_service.py cron job (agentic workflow)
- **Demo:** Chat with Bark Bot → show it calling GPU API → submit contact form → show auto-response email

### 8. Workstations
- **Measure:** Win + RHEL workstations, auth, email, website access
- **Action needed:** ClientDesktop1 (Win11) and ClientDesktop2 (RHEL) need to: join AD domain, access website, send email, run applications
- **Owner:** Jason/Allegra

### 9. Analytics
- **Measure:** Network monitoring, device monitoring, dashboard
- **Action needed:** Install Zabbix on Tracker VM, add all 10 VMs as hosts, create dashboard
- **Owner:** Allegra (Tracker VM lead)

### 10. Backup and Recovery
- **Measure:** pg_dump backup + successful restore
- **Action needed:** Python backup script (Guide 6), cron job, demonstrate restore
- **Owner:** Oscar (Database lead)

### 11. Network Documentation
- **Measure:** Comprehensive docs
- **Action needed:** Team6_Tracker.xlsx tabs filled in, change log maintained
- **Owner:** ALL — fill in your sections

### 12. Vulnerability Assessment
- **Measure:** Scanning from SecurityDesktop
- **Action needed:** Run Nmap + OpenVAS from Kali against all VMs, document findings
- **Owner:** Allegra (SecurityDesktop lead)

---

## Coordination Checklist — What to Tell Your Teammates

### Message to Jason (Network Lead):
> "Hey Jason, before I can deploy the website I need these firewall rules on OPNsense:
> 1. LAN → DMZ on ports 22, 80, 443 (SSH + HTTP)
> 2. WebServer (10.0.1.100) → Database (10.0.1.200) on port 5432 (PostgreSQL)
> 3. WebServer → external on port 443 (for GPU API calls)
> 4. WAN → DMZ on ports 80, 443 (external website access)
> 5. LAN → DMZ on ports 25, 587, 993 (email)
> Can you set these up this week?"

### Message to Oscar (Database Lead):
> "Hey Oscar, I need the PostgreSQL database set up on the Database VM (10.0.1.200). Here's exactly what I need:
> 1. Database name: `rentadog`
> 2. App user: `rentadog_app` with a password you choose (share with me securely)
> 3. 9 tables — I'll send you the exact CREATE TABLE statements
> 4. Sample data — I'll send the INSERT statements too
> 5. Allow connections from 10.0.1.100 in pg_hba.conf
> The table/column names need to match exactly because my PHP code queries against them."

### Message to Allegra (Security/Monitoring Lead):
> "Hey Allegra, when you set up AD/DNS, I'll need:
> 1. DNS A record: rentadog.local → 10.0.1.100
> 2. DNS MX record: rentadog.local → mail.rentadog.local (10.0.1.100)
> 3. AD user accounts: admin.ruggles, staff.cortez, staff.ponce, staff.ramirez, guest
> Also, when Zabbix is up, add the WebServer (10.0.1.100) as a monitored host."

---

## Quick Reference: What Goes Where

| Component | VM | IP | Port | Owner |
|-----------|-----|-----|------|-------|
| Website (Nginx + PHP) | WebServer | 10.0.1.100 | 80/443 | James |
| PostgreSQL database | Database | 10.0.1.200 | 5432 | Oscar |
| Email (Postfix/Dovecot) | WebServer | 10.0.1.100 | 25/587/993 | James |
| Bark Bot chat endpoint | WebServer | 10.0.1.100 | (same as web) | James |
| Agentic workflow (cron) | WebServer | 10.0.1.100 | N/A | James |
| GPU API | External | TBD | 443 | James |
| Active Directory + DNS | AD/DNS | 192.168.1.5 | 53/389/636 | Allegra |
| Zabbix monitoring | Tracker | 192.168.1.10 | 80 | Allegra |
| OPNsense firewall | Firewall | 192.168.2.1 | 443 (admin) | Jason |
| Kali pentesting | SecurityDesktop | 172.31.0.100 | N/A | Allegra |

---

## Order of Operations Summary

```
Week 3-4:  Jason → firewall rules
           Oscar → PostgreSQL + schema + sample data
           Allegra → AD accounts + DNS records

Week 5:    James → SSH into WebServer
           James → Install Nginx + PHP-FPM
           James → Deploy website files
           James → Test: website loads from workstation browser ✓

Week 6:    James → Create includes/db.php (PDO connection)
           James → Modify data.php (hardcoded → DB queries)
           James → Modify checkout.php (save orders to DB)
           James → Modify contact_handler.php (save to contacts table)
           James → Install Postfix + Dovecot
           James → Create email accounts
           James → Test: order goes into DB, email sends ✓

Week 7-8:  James → Get GPU API credentials from Dr. Nestler
           Jason → Add firewall rule: WebServer → GPU API (443)
           James → Modify api/chat.php (keyword → GPU API)
           James → Deploy customer_service.py + cron job
           James → Test: Bark Bot uses real AI, contact auto-responds ✓

Week 9-10: Allegra → Zabbix on Tracker, monitor all VMs
           Oscar → Python backup script + cron + test restore

Week 11-12: ALL → Change every default credential
            James → Fix XSS, CSRF, session security
            ALL → SSH hardening on all RHEL VMs
            Allegra → Run Nmap + OpenVAS from Kali

Week 13-14: IST 4620 pentest — don't touch anything
            Write remediation report after

Week 15:   Final presentation — demo the working site
```

---

*Created: February 22, 2026*
*For: IST 4910 Final Project — Team 6 — Rent a Dog*
