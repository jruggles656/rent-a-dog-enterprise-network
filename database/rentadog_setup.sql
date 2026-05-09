-- ============================================================
-- RENT A DOG — PostgreSQL Database Setup Script
-- IST 4910 Team 6 | Oscar Ponce — Database Lead
-- Database VM: 10.0.1.200
-- ============================================================
-- USAGE:
--   psql -U postgres -f rentadog_setup.sql
-- ============================================================


-- ------------------------------------------------------------
-- STEP 1: Create database and restricted app user
-- ------------------------------------------------------------

-- Run as postgres superuser
CREATE DATABASE rentadog;

-- Create restricted app user (NOT superuser)
CREATE USER rentadog_app WITH PASSWORD 'ChangeMeWeek11!';

-- Grant only necessary privileges (SELECT, INSERT, UPDATE)
-- No DELETE, No DROP, No superuser access
GRANT CONNECT ON DATABASE rentadog TO rentadog_app;

-- Connect to the rentadog database before continuing
\c rentadog

-- Enable pgcrypto for password hashing
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Grant schema usage to app user
GRANT USAGE ON SCHEMA public TO rentadog_app;


-- ============================================================
-- STEP 2: Create all 13 tables
-- ============================================================


-- ------------------------------------------------------------
-- GROUP 1: Core Business
-- ------------------------------------------------------------

CREATE TABLE breeds (
    breed_id    SERIAL PRIMARY KEY,
    name        VARCHAR(50) NOT NULL,
    tier        VARCHAR(10)  NOT NULL CHECK (tier IN ('Basic', 'Premium', 'VIP')),
    hourly_rate DECIMAL(6,2) NOT NULL CHECK (hourly_rate > 0),
    daily_rate  DECIMAL(6,2) GENERATED ALWAYS AS (hourly_rate * 6) STORED,
    description TEXT
);

CREATE TABLE dogs (
    dog_id      SERIAL PRIMARY KEY,
    breed_id    INTEGER      NOT NULL REFERENCES breeds(breed_id) ON DELETE RESTRICT,
    name        VARCHAR(50) NOT NULL,
    age         INTEGER      CHECK (age >= 0 AND age <= 30),
    weight      DECIMAL(5,1) CHECK (weight > 0),
    status      VARCHAR(20)  NOT NULL DEFAULT 'available'
                             CHECK (status IN ('available', 'rented', 'grooming', 'inactive')),
    photo_url   TEXT
);

CREATE TABLE experience_packages (
    package_id       SERIAL PRIMARY KEY,
    name             VARCHAR(50) NOT NULL,
    description      TEXT,
    price            DECIMAL(6,2) NOT NULL CHECK (price > 0),
    duration_minutes INTEGER      NOT NULL CHECK (duration_minutes > 0),
    package_type     VARCHAR(20)  NOT NULL DEFAULT 'individual'
                                  CHECK (package_type IN ('individual', 'group')),
    max_party_size   INTEGER      DEFAULT 1
);

-- Products (3 goods: food, toys, accessories)
CREATE TABLE products (
    product_id  SERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    category    VARCHAR(30)  NOT NULL CHECK (category IN ('food', 'toys', 'accessories')),
    description TEXT,
    price       DECIMAL(6,2) NOT NULL CHECK (price > 0),
    stock_qty   INTEGER      NOT NULL DEFAULT 0 CHECK (stock_qty >= 0),
    image_url   TEXT
);


-- ------------------------------------------------------------
-- GROUP 2: Customers & Authentication
-- ------------------------------------------------------------

CREATE TABLE customers (
    customer_id   SERIAL PRIMARY KEY,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,   -- bcrypt via pgcrypto
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE addresses (
    address_id  SERIAL PRIMARY KEY,
    customer_id INTEGER      NOT NULL REFERENCES customers(customer_id) ON DELETE CASCADE,
    street      VARCHAR(150) NOT NULL,
    city        VARCHAR(100) NOT NULL,
    state       VARCHAR(50)  NOT NULL,
    zip         VARCHAR(20)  NOT NULL,
    is_default  BOOLEAN      NOT NULL DEFAULT FALSE
);


-- ------------------------------------------------------------
-- GROUP 3: Orders & Payments
-- ------------------------------------------------------------

CREATE TABLE orders (
    order_id            SERIAL PRIMARY KEY,
    customer_id         INTEGER     NOT NULL REFERENCES customers(customer_id) ON DELETE RESTRICT,
    order_date          TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount        DECIMAL(8,2),
    status              VARCHAR(20) NOT NULL DEFAULT 'pending'
                                    CHECK (status IN ('pending', 'paid', 'shipped', 'complete', 'cancelled', 'refunded')),
    shipping_address_id INTEGER     REFERENCES addresses(address_id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    item_id     SERIAL PRIMARY KEY,
    order_id    INTEGER     NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    item_type   VARCHAR(20) NOT NULL CHECK (item_type IN ('DOG', 'PACKAGE', 'PRODUCT')),
    dog_id      INTEGER     REFERENCES dogs(dog_id) ON DELETE SET NULL,
    package_id  INTEGER     REFERENCES experience_packages(package_id) ON DELETE SET NULL,
    product_id  INTEGER     REFERENCES products(product_id) ON DELETE SET NULL,
    quantity    INTEGER     NOT NULL DEFAULT 1 CHECK (quantity > 0),
    unit_price  DECIMAL(6,2) NOT NULL,
    rental_hours INTEGER,   -- for DOG rentals
    line_total  DECIMAL(8,2) GENERATED ALWAYS AS (unit_price * quantity) STORED
);

-- Payments: NO card numbers, NO CVV — provider reference only (PCI compliant)
CREATE TABLE payments (
    payment_id      SERIAL PRIMARY KEY,
    order_id        INTEGER     NOT NULL REFERENCES orders(order_id) ON DELETE RESTRICT,
    amount          DECIMAL(8,2) NOT NULL CHECK (amount > 0),
    payment_method  VARCHAR(50) NOT NULL DEFAULT 'stripe',
    transaction_ref VARCHAR(255),          -- Stripe/PayPal transaction ID only
    status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                                CHECK (status IN ('pending', 'completed', 'failed', 'refunded')),
    paid_at         TIMESTAMPTZ
);


-- ------------------------------------------------------------
-- GROUP 4: Scheduling
-- ------------------------------------------------------------

CREATE TABLE availability (
    slot_id    SERIAL PRIMARY KEY,
    dog_id     INTEGER NOT NULL REFERENCES dogs(dog_id) ON DELETE CASCADE,
    date       DATE    NOT NULL,
    start_time TIME    NOT NULL,
    end_time   TIME    NOT NULL,
    is_booked  BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT valid_time_range CHECK (end_time > start_time)
);


-- ------------------------------------------------------------
-- GROUP 5: Staff & Roles (optional — layered on top)
-- ------------------------------------------------------------

CREATE TABLE roles (
    role_id     SERIAL PRIMARY KEY,
    role_name   VARCHAR(50)  NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE employees (
    employee_id SERIAL PRIMARY KEY,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,  -- bcrypt via pgcrypto
    first_name  VARCHAR(50)  NOT NULL,
    last_name   VARCHAR(50)  NOT NULL,
    phone       VARCHAR(20),
    role_id     INTEGER      REFERENCES roles(role_id) ON DELETE SET NULL,
    hire_date   DATE,
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- GROUP 6: Admin & Audit
-- ------------------------------------------------------------

CREATE TABLE activity_log (
    log_id    SERIAL PRIMARY KEY,
    user_type VARCHAR(20) CHECK (user_type IN ('customer', 'employee', 'system')),
    user_id   INTEGER,
    action    VARCHAR(100) NOT NULL,
    details   TEXT,
    timestamp TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- GROUP 7: AI Agentic Workflow
-- ------------------------------------------------------------

CREATE TABLE contacts (
    contact_id   SERIAL PRIMARY KEY,
    name         VARCHAR(100),
    email        VARCHAR(100),
    subject      VARCHAR(200),
    message      TEXT,
    category     VARCHAR(50)  NOT NULL DEFAULT 'uncategorized',
    ai_response  TEXT,
    status       VARCHAR(20)  NOT NULL DEFAULT 'new'
                              CHECK (status IN ('new', 'responded', 'escalated', 'closed')),
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMPTZ
);


-- ============================================================
-- STEP 3: Indexes (performance + security)
-- ============================================================

-- Customers
CREATE INDEX idx_customers_email       ON customers(email);

-- Orders
CREATE INDEX idx_orders_customer_id    ON orders(customer_id);
CREATE INDEX idx_orders_status         ON orders(status);

-- Order Items
CREATE INDEX idx_order_items_order_id  ON order_items(order_id);
CREATE INDEX idx_order_items_dog_id    ON order_items(dog_id);

-- Dogs
CREATE INDEX idx_dogs_breed_id         ON dogs(breed_id);
CREATE INDEX idx_dogs_status           ON dogs(status);

-- Availability
CREATE INDEX idx_availability_dog_date ON availability(dog_id, date);

-- Contacts (for AI workflow)
CREATE INDEX idx_contacts_status       ON contacts(status);

-- Activity log
CREATE INDEX idx_activity_log_ts       ON activity_log(timestamp);

-- Payments
CREATE INDEX idx_payments_order_id     ON payments(order_id);


-- ============================================================
-- STEP 4: Grant table permissions to app user
-- ============================================================

-- Core tables: SELECT, INSERT, UPDATE only (no DELETE for safety)
GRANT SELECT, INSERT, UPDATE ON
    breeds, dogs, experience_packages, products,
    customers, addresses,
    orders, order_items, payments,
    availability, contacts, activity_log,
    roles, employees
TO rentadog_app;

-- Allow app user to use SERIAL sequences
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO rentadog_app;


-- ============================================================
-- STEP 5: Seed data
-- ============================================================

-- Roles
INSERT INTO roles (role_name, description) VALUES
    ('admin',   'Full access to admin portal'),
    ('staff',   'Day-to-day operations access'),
    ('viewer',  'Read-only reporting access');

-- Breeds (3 tiers — matches James schema + blueprint)
INSERT INTO breeds (name, tier, hourly_rate, description) VALUES
    ('Tibetan Mastiff', 'VIP',     50.00, 'Majestic and loyal guardian'),
    ('Samoyed',         'VIP',     50.00, 'Fluffy and friendly cloud dog'),
    ('Akita',           'VIP',     50.00, 'Noble and courageous protector'),
    ('Golden Retriever','Premium', 40.00, 'Friendly and devoted family dog'),
    ('Labrador',        'Premium', 40.00, 'Outgoing and active companion'),
    ('Beagle',          'Premium', 40.00, 'Curious and merry hound'),
    ('Labradoodle',     'Basic',   30.00, 'Playful and hypoallergenic mix'),
    ('Goldendoodle',    'Basic',   30.00, 'Gentle and intelligent crossbreed'),
    ('Puggle',          'Basic',   30.00, 'Charming and sociable small mix');

-- Dogs
INSERT INTO dogs (breed_id, name, age, weight, status, photo_url) VALUES
    (1, 'Zeus',   4, 140.0, 'available', '/assets/images/dogs/zeus.jpg'),
    (2, 'Luna',   2,  45.0, 'available', '/assets/images/dogs/luna.jpg'),
    (3, 'Kuma',   3, 100.0, 'available', '/assets/images/dogs/kuma.jpg'),
    (4, 'Biscuit',3,  65.0, 'available', '/assets/images/dogs/biscuit.jpg'),
    (5, 'Coco',   2,  70.0, 'available', '/assets/images/dogs/coco.jpg'),
    (6, 'Daisy',  4,  22.0, 'available', '/assets/images/dogs/daisy.jpg'),
    (7, 'Mochi',  1,  35.0, 'available', '/assets/images/dogs/mochi.jpg'),
    (8, 'Teddy',  2,  55.0, 'available', '/assets/images/dogs/teddy.jpg');

-- Experience Packages (individual + group — matches Excel)
INSERT INTO experience_packages (name, description, price, duration_minutes, package_type, max_party_size) VALUES
    ('Dog Cafe Single',       'Relax with adorable dogs in our cozy cafe setting',                55.00, 120, 'individual', 1),
    ('Dog Yoga Single',       'Find your zen with furry yoga partners',                           65.00,  90, 'individual', 1),
    ('Dog Garden Single',     'Enjoy the outdoors with playful pups',                             30.00,  60, 'individual', 1),
    ('Dog Cafe Group',        'Relax with adorable dogs in our cozy cafe setting (4–6 people)',  180.00, 120, 'group',      6),
    ('Dog Yoga Group',        'Find your zen with furry yoga partners (3–4 people)',             180.00,  90, 'group',      4),
    ('Dog Garden Group',      'Enjoy the outdoors with playful pups (2–4 people)',                60.00,  60, 'group',      4);

-- Products (3 goods: food, toys, accessories)
INSERT INTO products (name, category, description, price, stock_qty) VALUES
    ('Premium Dry Dog Food 5lb',   'food',        'High protein dry kibble blend',              25.00, 50),
    ('Grain-Free Wet Food 12-pack','food',        'Grain-free wet food variety pack',           45.00, 30),
    ('Fresh Meal Box',             'food',        'Fresh refrigerated dog meal subscription',   65.00, 20),
    ('Rope Tug Toy',               'toys',        'Durable braided rope tug toy',                8.00,100),
    ('Squeaky Plush Bundle',       'toys',        'Set of 3 squeaky plush toys',                18.00, 75),
    ('Fetch Ball Set',             'toys',        'Tennis balls + launcher set',                35.00, 40),
    ('Dog Bandana Set',            'accessories', 'Set of 4 seasonal bandanas',                 12.00, 60),
    ('Luxury Dog Bed',             'accessories', 'Memory foam orthopedic pet bed',             80.00, 15),
    ('Stainless Steel Bowl Set',   'accessories', 'Double bowl stand with non-slip base',       22.00, 35);


-- ============================================================
-- STEP 6: Key demo queries (for graded database demo)
-- ============================================================

-- 1. Dogs by breed tier (used by James's PHP)
-- SELECT d.dog_id as id, d.name, b.name as breed,
--        b.tier, b.hourly_rate as price, d.status,
--        d.photo_url as image, b.description as personality
-- FROM dogs d JOIN breeds b ON d.breed_id = b.breed_id;

-- 2. Customer order history
-- SELECT c.first_name, c.last_name, o.order_id, o.order_date,
--        o.total_amount, o.status
-- FROM customers c
-- JOIN orders o ON c.customer_id = o.customer_id
-- ORDER BY o.order_date DESC;

-- 3. Revenue by category
-- SELECT oi.item_type, SUM(oi.line_total) AS revenue
-- FROM order_items oi
-- JOIN orders o ON oi.order_id = o.order_id
-- WHERE o.status IN ('paid', 'complete')
-- GROUP BY oi.item_type;

-- 4. Dog availability report
-- SELECT d.name, b.tier, d.status,
--        COUNT(a.slot_id) FILTER (WHERE NOT a.is_booked) AS open_slots
-- FROM dogs d
-- JOIN breeds b ON d.breed_id = b.breed_id
-- LEFT JOIN availability a ON d.dog_id = a.dog_id
-- GROUP BY d.dog_id, b.tier, d.status;

-- 5. Top customers by spend
-- SELECT c.first_name, c.last_name, c.email,
--        SUM(o.total_amount) AS total_spent
-- FROM customers c
-- JOIN orders o ON c.customer_id = o.customer_id
-- WHERE o.status IN ('paid', 'complete')
-- GROUP BY c.customer_id
-- ORDER BY total_spent DESC
-- LIMIT 10;

-- 6. AI workflow — unresponded contacts
-- SELECT contact_id, name, email, subject, category, created_at
-- FROM contacts
-- WHERE status = 'new'
-- ORDER BY created_at ASC;


-- ============================================================
-- Done! Database is ready.
-- Next: connect James's PHP using rentadog_app credentials
-- ============================================================

