<?php
/**
 * Rent a Dog — PayPal Sandbox Configuration
 * IST 4910 Team 6
 *
 * SETUP INSTRUCTIONS:
 * 1. Go to https://developer.paypal.com/dashboard/
 * 2. Log in (or create a free PayPal developer account)
 * 3. Go to Apps & Credentials → click "Create App"
 * 4. Name it "RentADog" → select "Sandbox" → Create
 * 5. Copy the Client ID and Secret below
 * 6. Under "Sandbox test accounts" create a buyer account for demo
 *
 * The sandbox lets you test the full PayPal checkout flow with fake money.
 * No real charges are ever made in sandbox mode.
 */

// ── PayPal Sandbox Credentials ─────────────────────────────────
// Replace these with your actual sandbox credentials from developer.paypal.com
// FIND-012 / A3: PayPal sandbox creds loaded from admin.env (outside docroot)
require_once __DIR__ . '/secrets.php';
define('PAYPAL_CLIENT_ID',  rentadog_secret('PAYPAL_CLIENT_ID'));
define('PAYPAL_SECRET',     rentadog_secret('PAYPAL_SECRET'));
define('PAYPAL_MODE',       'sandbox');  // 'sandbox' for testing, 'live' for production

// API base URLs
define('PAYPAL_API_BASE', PAYPAL_MODE === 'sandbox'
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com'
);

// JS SDK URL (loaded on checkout page)
define('PAYPAL_SDK_URL', 'https://www.paypal.com/sdk/js?client-id=' . PAYPAL_CLIENT_ID . '&currency=USD');

// ── Demo mode flag ─────────────────────────────────────────────
// When credentials are placeholders, the system runs in demo mode:
// - PayPal button appears but uses a simulated flow
// - A fake transaction ID is generated for the demo
// - Everything else (DB writes, confirmation) works normally
define('PAYPAL_DEMO_MODE', PAYPAL_CLIENT_ID === 'REPLACE_WITH_YOUR_SANDBOX_CLIENT_ID');
