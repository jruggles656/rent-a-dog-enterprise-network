<?php
/**
 * Rent a Dog — PayPal Checkout Handler
 * IST 4910 Team 6
 *
 * Handles two AJAX actions from the checkout page:
 *   1. create_order  — Creates a PayPal order via API, returns order ID
 *   2. capture_order — Captures payment after buyer approves, saves to DB
 *
 * In demo mode (no real credentials), simulates the PayPal response
 * so the full flow works for class demos without a PayPal account.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paypal_config.php';

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {

    // ════════════════════════════════════════════════════════════
    // ACTION: create_order
    // Called when the customer clicks the PayPal button.
    // Creates a PayPal order and returns the order ID for approval.
    // ════════════════════════════════════════════════════════════
    case 'create_order':
        $amount = floatval($input['amount'] ?? 0);
        $order_description = $input['description'] ?? 'Rent a Dog order';

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid amount']);
            exit;
        }

        // ── Demo Mode ──
        if (PAYPAL_DEMO_MODE) {
            $demo_id = 'DEMO-' . strtoupper(bin2hex(random_bytes(8)));
            echo json_encode([
                'id'   => $demo_id,
                'demo' => true,
            ]);
            exit;
        }

        // ── Live Sandbox Mode — Create order via PayPal API ──
        $access_token = getPayPalAccessToken();
        if (!$access_token) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to authenticate with PayPal']);
            exit;
        }

        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'description' => $order_description,
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
        ];

        $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
            ],
            CURLOPT_POSTFIELDS => json_encode($order_data),
            CURLOPT_TIMEOUT    => 30,
        ]);
        $response = json_decode(curl_exec($ch), true);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300 && isset($response['id'])) {
            echo json_encode(['id' => $response['id']]);
        } else {
            error_log('[RentaDog] PayPal create order failed: ' . json_encode($response));
            http_response_code(500);
            echo json_encode(['error' => 'PayPal order creation failed']);
        }
        break;

    // ════════════════════════════════════════════════════════════
    // ACTION: capture_order
    // Called after the buyer approves the payment in the PayPal popup.
    // Captures the funds and saves the order to our database.
    // ════════════════════════════════════════════════════════════
    case 'capture_order':
        $paypal_order_id = $input['paypal_order_id'] ?? '';
        $customer        = $input['customer'] ?? [];
        $cart_items      = $input['cart_items'] ?? [];
        $total           = floatval($input['total'] ?? 0);
        // Client-supplied claim that we're in demo mode. NOT trusted for the
        // capture decision below — server-side PAYPAL_DEMO_MODE is the only
        // source of truth. Kept for telemetry + defensive logging only.
        // (FIND-009 / A2 hardening 2026-04-21)
        $client_demo_claim = $input['demo'] ?? false;

        if (empty($paypal_order_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing PayPal order ID']);
            exit;
        }

        // Defensive log: if a client claims demo while server is not in demo
        // mode, that's either a bug in checkout.js OR a tampering attempt.
        // Either way, audit it. The capture decision below ignores the claim.
        if ($client_demo_claim && !PAYPAL_DEMO_MODE) {
            error_log(sprintf(
                '[A2-defense] capture_order received demo=true from client but server is in LIVE mode; ignoring claim. ip=%s order_id=%s email=%s',
                $_SERVER['REMOTE_ADDR'] ?? '?',
                substr((string)$paypal_order_id, 0, 64),
                substr((string)($input['customer']['email'] ?? '?'), 0, 64)
            ));
        }

        $transaction_ref = $paypal_order_id;
        // Initial status reflects server-side mode, not client input.
        $payment_status  = PAYPAL_DEMO_MODE ? 'completed' : 'pending';
        $capture_id      = null;

        // ── Live Sandbox Mode — Capture the payment via real PayPal API ──
        // Server-side PAYPAL_DEMO_MODE is the ONLY source of truth here.
        // Client's `demo` flag is intentionally not consulted.
        if (!PAYPAL_DEMO_MODE) {
            $access_token = getPayPalAccessToken();
            if (!$access_token) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to authenticate with PayPal']);
                exit;
            }

            $ch = curl_init(PAYPAL_API_BASE . "/v2/checkout/orders/{$paypal_order_id}/capture");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token,
                ],
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_TIMEOUT    => 30,
            ]);
            $response = json_decode(curl_exec($ch), true);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code >= 200 && $http_code < 300 && isset($response['status'])) {
                $payment_status = strtolower($response['status']) === 'completed' ? 'completed' : 'pending';
                // Get the capture ID (the actual transaction reference)
                $captures = $response['purchase_units'][0]['payments']['captures'] ?? [];
                if (!empty($captures)) {
                    $capture_id = $captures[0]['id'];
                    $transaction_ref = $capture_id;
                }
            } else {
                error_log('[RentaDog] PayPal capture failed: ' . json_encode($response));
                http_response_code(500);
                echo json_encode(['error' => 'PayPal payment capture failed']);
                exit;
            }
        }

        // ── Save to Database ──
        $order_number = null;
        $db_saved = false;

        if ($db_available && !empty($customer)) {
            try {
                $pdo->beginTransaction();

                $first_name = trim($customer['first_name'] ?? '');
                $last_name  = trim($customer['last_name'] ?? '');
                $email      = trim($customer['email'] ?? '');
                $phone      = trim($customer['phone'] ?? '');

                // 1. Find or create customer
                $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $customer_id = $existing['customer_id'];
                    $pdo->prepare("
                        UPDATE customers SET first_name = :fn, last_name = :ln, phone = :phone
                        WHERE customer_id = :id
                    ")->execute([
                        ':fn' => $first_name, ':ln' => $last_name,
                        ':phone' => $phone, ':id' => $customer_id,
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (first_name, last_name, email, phone, password_hash)
                        VALUES (:fn, :ln, :email, :phone, :pw_hash)  /* pgcrypto disabled — see FIND-010; no customer-login flow exists in this app */
                        RETURNING customer_id
                    ");
                    $stmt->execute([
                        ':fn' => $first_name, ':ln' => $last_name,
                        ':email' => $email, ':phone' => $phone,
                        // pgcrypto's gen_salt() unavailable on DB (OpenSSL ABI mismatch); customers.password_hash unused (no customer-login flow). FIND-010.
                        ':pw_hash' => '$2y$12$pgcrypto.disabled.no.customer.login.in.app.FIND-010-x',
                    ]);
                    $customer_id = $stmt->fetchColumn();
                }

                // 2. Create order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (customer_id, total_amount, status)
                    VALUES (:cid, :total, :ord_status)  /* FIND-011: schema CHECK constraint allows pending|paid|shipped|complete|cancelled|refunded; was 'confirmed' (invalid) */
                    RETURNING order_id
                ");
                // FIND-011: map A2's $payment_status to schema-valid orders.status enum.
                $order_status = ($payment_status === 'completed') ? 'paid' : 'pending';
                $stmt->execute([':cid' => $customer_id, ':total' => $total, ':ord_status' => $order_status]);
                $order_id = $stmt->fetchColumn();
                $order_number = 'RAD-' . date('Y') . '-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);

                // 3. Create order items
                $item_stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, item_type, dog_id, package_id, quantity, unit_price, rental_hours)
                    VALUES (:oid, :type, :did, :pid, :qty, :price, :hours)
                ");

                foreach ($cart_items as $item) {
                    if (($item['type'] ?? '') === 'dog') {
                        $item_stmt->execute([
                            ':oid'   => $order_id,
                            ':type'  => 'DOG',
                            ':did'   => $item['id'],
                            ':pid'   => null,
                            ':qty'   => 1,
                            ':price' => $item['rate'],
                            ':hours' => $item['hours'] ?? 1,
                        ]);
                    } else {
                        $item_stmt->execute([
                            ':oid'   => $order_id,
                            ':type'  => 'PACKAGE',
                            ':did'   => null,
                            ':pid'   => $item['id'],
                            ':qty'   => 1,
                            ':price' => $item['rate'],
                            ':hours' => null,
                        ]);
                    }
                }

                // 4. Create payment record with PayPal transaction ref
                $pdo->prepare("
                    INSERT INTO payments (order_id, amount, payment_method, transaction_ref, status)
                    VALUES (:oid, :amt, 'paypal', :ref, :status)
                ")->execute([
                    ':oid'    => $order_id,
                    ':amt'    => $total,
                    ':ref'    => $transaction_ref,
                    ':status' => $payment_status,
                ]);

                // 5. Log activity
                $pdo->prepare("
                    INSERT INTO activity_log (user_type, user_id, action, details)
                    VALUES ('customer', :uid, 'place_order', :details)
                ")->execute([
                    ':uid'     => $customer_id,
                    ':details' => "Order {$order_number} — \${$total} via PayPal ({$transaction_ref})",
                ]);

                $pdo->commit();
                $db_saved = true;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('[RentaDog] PayPal checkout DB error: ' . $e->getMessage());
            }
        }

        // Fallback order number
        if (!$order_number) {
            $order_number = 'RAD-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        // Store in session for confirmation page
        $_SESSION['last_order'] = [
            'number'          => $order_number,
            'total'           => $total,
            'email'           => htmlspecialchars($customer['email'] ?? ''),
            'items'           => $cart_items,
            'db_saved'        => $db_saved,
            'payment_method'  => 'paypal',
            'transaction_ref' => $transaction_ref,
            'demo'            => PAYPAL_DEMO_MODE,
        ];

        // Clear cart
        $_SESSION['cart'] = [];

        echo json_encode([
            'success'         => true,
            'order_number'    => $order_number,
            'transaction_ref' => $transaction_ref,
            'demo'            => PAYPAL_DEMO_MODE,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}


// ════════════════════════════════════════════════════════════════
// Helper: Get PayPal Access Token (OAuth2 client credentials)
// ════════════════════════════════════════════════════════════════
function getPayPalAccessToken() {
    $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response['access_token'] ?? null;
}
