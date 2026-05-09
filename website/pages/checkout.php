<?php
/**
 * Rent a Dog — Checkout Page (with PayPal Sandbox)
 * IST 4910 Team 6
 *
 * Customer details form + order summary + PayPal payment button.
 * In demo mode (no PayPal credentials), simulates the payment flow.
 * In sandbox mode, uses real PayPal Sandbox API for test payments.
 */
$page_title = 'Checkout';
require_once '../includes/header.php';
require_once '../includes/paypal_config.php';

$cart = getCart();
$cart_total = getCartTotal();
$order_total = $cart_total + 5; // +$5 service fee
?>

  <!-- ============================================================
       CHECKOUT PAGE
       ============================================================ -->
  <main class="checkout-page">
    <div class="container">

      <!-- Page Header -->
      <header class="page-header reveal">
        <h1 class="page-title">Checkout</h1>
        <p class="handwritten page-subtitle">You're almost there!</p>
      </header>

      <?php if (empty($cart)): ?>
        <!-- ── Empty Cart Message ──────────────────────────────── -->
        <div class="checkout-empty reveal">
          <span class="checkout-empty-icon" aria-hidden="true">&#128062;</span>
          <h2>Your cart is empty</h2>
          <p class="text-muted">Looks like you haven't picked a furry friend yet.</p>
          <a href="<?= $base_path ?>/pages/breeds.php" class="btn-primary btn-lg">Browse Our Dogs</a>
        </div>

      <?php else: ?>
        <!-- ── Two-Column Checkout Grid ───────────────────────── -->
        <div class="checkout-grid reveal">

          <!-- Left Column — Customer Info Form -->
          <div class="checkout-form-col">
            <div class="checkout-form" id="checkoutForm">
              <h2>Your Details</h2>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="first_name">First Name</label>
                  <input type="text" id="first_name" name="first_name" class="form-input" required>
                </div>
                <div class="form-group">
                  <label class="form-label" for="last_name">Last Name</label>
                  <input type="text" id="last_name" name="last_name" class="form-input" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-input">
              </div>

              <div class="form-group">
                <label class="form-label" for="requests">Special Requests</label>
                <textarea id="requests" name="requests" class="form-input form-textarea" rows="4" placeholder="Any allergies, preferences, or special notes..."></textarea>
              </div>

              <!-- Payment Section -->
              <div class="checkout-payment-section">
                <h2>Payment</h2>

                <?php if (PAYPAL_DEMO_MODE): ?>
                <div class="checkout-demo-notice">
                  <strong>Demo Mode</strong> — PayPal sandbox credentials not configured yet.
                  Clicking the button below simulates a PayPal payment for demo purposes.
                </div>
                <?php else: ?>
                <p class="checkout-payment-info">Pay securely with PayPal Sandbox (test mode — no real charges).</p>
                <?php endif; ?>

                <!-- PayPal Button Container -->
                <div id="paypal-button-container"></div>

                <!-- Fallback / Demo button (shown when PayPal SDK can't load) -->
                <div id="paypal-demo-btn" style="display:none;">
                  <button type="button" class="btn-paypal" id="demoPay">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: -4px; margin-right: 6px;">
                      <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797H9.603c-.5 0-.924.362-1.003.856l-.007.045-1.1 6.987-.006.033a.397.397 0 0 1-.41.085z"/>
                    </svg>
                    Pay with PayPal (Demo)
                  </button>
                </div>

                <!-- Processing spinner -->
                <div id="paypal-processing" style="display:none;" class="checkout-processing">
                  <div class="checkout-spinner"></div>
                  <span>Processing your payment...</span>
                </div>

                <!-- Error message -->
                <div id="paypal-error" style="display:none;" class="alert alert-error"></div>
              </div>

            </div>
          </div>

          <!-- Right Column — Order Summary (sticky) -->
          <div class="checkout-summary-col">
            <div class="order-summary">
              <h3>Order Summary</h3>

              <?php foreach ($cart as $item): ?>
              <div class="summary-item">
                <div class="summary-item-info">
                  <strong><?= htmlspecialchars($item['name']) ?></strong>
                  <span class="text-muted">
                    <?php if ($item['type'] === 'dog'): ?>
                      <?= $item['hours'] ?> hr<?= $item['hours'] > 1 ? 's' : '' ?> &times; $<?= $item['rate'] ?>
                    <?php else: ?>
                      1 session
                    <?php endif; ?>
                  </span>
                </div>
                <span class="summary-item-price">$<?= $item['type'] === 'dog' ? $item['rate'] * $item['hours'] : $item['rate'] ?></span>
              </div>
              <?php endforeach; ?>

              <div class="summary-divider"></div>

              <div class="summary-line summary-subtotal">
                <span>Subtotal</span>
                <span>$<?= number_format($cart_total, 2) ?></span>
              </div>

              <div class="summary-line summary-fee">
                <span>Service Fee</span>
                <span>$5.00</span>
              </div>

              <div class="summary-divider"></div>

              <div class="summary-line order-total">
                <span>Total</span>
                <span>$<?= number_format($order_total, 2) ?></span>
              </div>

              <div class="checkout-secure-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 4px;">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                Secured with PayPal <?= PAYPAL_DEMO_MODE ? '(Demo)' : 'Sandbox' ?>
              </div>
            </div>
          </div>

        </div><!-- /.checkout-grid -->
      <?php endif; ?>

    </div><!-- /.container -->
  </main>

  <?php if (!empty($cart)): ?>
  <!-- ============================================================
       PAYPAL CHECKOUT SCRIPT
       ============================================================ -->

  <!-- Define BASE_PATH before PayPal script (footer.php hasn't loaded yet) -->
  <script>if (typeof BASE_PATH === 'undefined') { var BASE_PATH = '<?= $base_path ?>'; }</script>

  <?php if (!PAYPAL_DEMO_MODE): ?>
  <!-- Load PayPal JS SDK (only in sandbox mode with real credentials) -->
  <script src="<?= PAYPAL_SDK_URL ?>"></script>
  <?php endif; ?>

  <script>
  (function() {
    'use strict';

    var PAYPAL_API = BASE_PATH + '/api/paypal_handler.php';
    var ORDER_TOTAL = <?= json_encode(number_format($order_total, 2, '.', '')) ?>;
    var DEMO_MODE = <?= json_encode(PAYPAL_DEMO_MODE) ?>;
    var CART_ITEMS = <?= json_encode(array_values($cart)) ?>;

    var btnContainer = document.getElementById('paypal-button-container');
    var demoBtnWrap  = document.getElementById('paypal-demo-btn');
    var processing   = document.getElementById('paypal-processing');
    var errorDiv     = document.getElementById('paypal-error');

    // ── Validation ──────────────────────────────────────────
    function validateForm() {
      var fn    = document.getElementById('first_name').value.trim();
      var ln    = document.getElementById('last_name').value.trim();
      var email = document.getElementById('email').value.trim();

      if (!fn || !ln || !email) {
        showError('Please fill in your name and email before paying.');
        return false;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError('Please enter a valid email address.');
        return false;
      }
      return true;
    }

    function getCustomerData() {
      return {
        first_name: document.getElementById('first_name').value.trim(),
        last_name:  document.getElementById('last_name').value.trim(),
        email:      document.getElementById('email').value.trim(),
        phone:      document.getElementById('phone').value.trim(),
      };
    }

    function showError(msg) {
      errorDiv.textContent = msg;
      errorDiv.style.display = 'block';
      processing.style.display = 'none';
    }

    function hideError() {
      errorDiv.style.display = 'none';
    }

    // ── Capture / Finalize ──────────────────────────────────
    function captureOrder(paypalOrderId, isDemo) {
      processing.style.display = 'flex';
      hideError();

      fetch(PAYPAL_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:          'capture_order',
          paypal_order_id: paypalOrderId,
          customer:        getCustomerData(),
          cart_items:      CART_ITEMS,
          total:           parseFloat(ORDER_TOTAL),
          demo:            isDemo,
        }),
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          // Redirect to confirmation
          window.location.href = BASE_PATH + '/pages/confirmation.php';
        } else {
          showError(data.error || 'Payment failed. Please try again.');
        }
      })
      .catch(function(err) {
        showError('Something went wrong. Please try again.');
        console.error('Capture error:', err);
      });
    }

    // ── PayPal SDK Buttons (real sandbox) ────────────────────
    if (!DEMO_MODE && typeof paypal !== 'undefined') {
      paypal.Buttons({
        style: {
          layout: 'vertical',
          color:  'gold',
          shape:  'rect',
          label:  'paypal',
          height: 45,
        },

        // Pre-check: validate form before the popup opens
        onClick: function(data, actions) {
          if (!validateForm()) {
            return actions.reject();
          }
          hideError();
          return actions.resolve();
        },

        // Step 1: Create order on our server
        createOrder: function() {
          console.log('[PayPal] Creating order, calling:', PAYPAL_API);
          return fetch(PAYPAL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action:      'create_order',
              amount:      parseFloat(ORDER_TOTAL),
              description: 'Rent a Dog — Order',
            }),
          })
          .then(function(res) {
            console.log('[PayPal] Server response status:', res.status);
            return res.json();
          })
          .then(function(data) {
            console.log('[PayPal] Server response data:', data);
            if (data.id) return data.id;
            throw new Error(data.error || 'Failed to create order');
          });
        },

        // Step 2: Buyer approved — capture payment
        onApprove: function(data) {
          captureOrder(data.orderID, false);
        },

        onError: function(err) {
          showError('PayPal encountered an error. Please try again.');
          console.error('[PayPal] Error:', err);
        },

        onCancel: function() {
          showError('Payment cancelled. You can try again when ready.');
        },
      }).render('#paypal-button-container');

    } else {
      // ── Demo Mode Button ────────────────────────────────────
      btnContainer.style.display = 'none';
      demoBtnWrap.style.display = 'block';

      document.getElementById('demoPay').addEventListener('click', function() {
        if (!validateForm()) return;
        hideError();

        processing.style.display = 'flex';
        demoBtnWrap.style.display = 'none';

        // Step 1: Create a demo order
        fetch(PAYPAL_API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action:      'create_order',
            amount:      parseFloat(ORDER_TOTAL),
            description: 'Rent a Dog — Order (Demo)',
          }),
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data.id) {
            // Simulate a 2-second "PayPal approval" delay for demo
            setTimeout(function() {
              captureOrder(data.id, true);
            }, 2000);
          } else {
            showError(data.error || 'Demo order creation failed.');
            demoBtnWrap.style.display = 'block';
          }
        })
        .catch(function(err) {
          showError('Something went wrong. Please try again.');
          demoBtnWrap.style.display = 'block';
          console.error('Demo error:', err);
        });
      });
    }
  })();
  </script>
  <?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
