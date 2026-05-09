<?php
/**
 * Rent a Dog — Order Confirmation Page
 * IST 4910 Team 6
 *
 * Celebratory page shown after a successful checkout.
 * Displays PayPal transaction reference (sandbox or demo).
 */
$page_title = 'Order Confirmed';
require_once '../includes/header.php';

$order = $_SESSION['last_order'] ?? null;
if (!$order) {
    header('Location: ../index.php');
    exit;
}
?>

  <!-- ============================================================
       ORDER CONFIRMATION
       ============================================================ -->
  <main class="confirmation-page">
    <div class="container">

      <div class="confirmation-card reveal">

        <!-- Celebratory Icon -->
        <div class="confirmation-icon" aria-hidden="true">
          <svg width="80" height="80" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="50" cy="65" rx="22" ry="18" fill="#8B5E3C"/>
            <ellipse cx="28" cy="35" rx="10" ry="13" transform="rotate(-15 28 35)" fill="#8B5E3C"/>
            <ellipse cx="72" cy="35" rx="10" ry="13" transform="rotate(15 72 35)" fill="#8B5E3C"/>
            <ellipse cx="38" cy="28" rx="9" ry="12" transform="rotate(5 38 28)" fill="#8B5E3C"/>
            <ellipse cx="62" cy="28" rx="9" ry="12" transform="rotate(-5 62 28)" fill="#8B5E3C"/>
          </svg>
        </div>

        <!-- Heading -->
        <h1 class="confirmation-heading">Your adventure is booked!</h1>
        <p class="confirmation-order-number handwritten">
          Order <?= htmlspecialchars($order['number']) ?>
        </p>

        <!-- Payment Info -->
        <?php if (!empty($order['payment_method']) && $order['payment_method'] === 'paypal'): ?>
        <div class="confirmation-payment-badge">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 4px; color: #27ae60;">
            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <?php if (!empty($order['demo'])): ?>
            Payment simulated (Demo Mode)
          <?php else: ?>
            Paid via PayPal Sandbox
          <?php endif; ?>
        </div>
        <?php if (!empty($order['transaction_ref'])): ?>
        <p class="confirmation-transaction">
          Transaction: <code><?= htmlspecialchars($order['transaction_ref']) ?></code>
        </p>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Order Summary -->
        <div class="confirmation-summary">
          <h3>What you booked</h3>

          <?php foreach ($order['items'] as $item): ?>
          <div class="confirmation-item">
            <span class="confirmation-item-name"><?= htmlspecialchars($item['name']) ?></span>
            <span class="confirmation-item-detail text-muted">
              <?php if (($item['type'] ?? '') === 'dog'): ?>
                <?= htmlspecialchars($item['breed'] ?? '') ?> &middot; <?= $item['hours'] ?? 1 ?> hr<?= ($item['hours'] ?? 1) > 1 ? 's' : '' ?>
              <?php else: ?>
                Experience &middot; 1 session
              <?php endif; ?>
            </span>
            <span class="confirmation-item-price">
              $<?= number_format(($item['type'] ?? '') === 'dog' ? ($item['rate'] ?? 0) * ($item['hours'] ?? 1) : ($item['rate'] ?? 0), 2) ?>
            </span>
          </div>
          <?php endforeach; ?>

          <div class="confirmation-divider"></div>

          <div class="confirmation-total">
            <span>Total (incl. service fee)</span>
            <span>$<?= number_format($order['total'], 2) ?></span>
          </div>
        </div>

        <!-- Email Confirmation Note -->
        <p class="confirmation-email">
          A confirmation email has been sent to
          <strong><?= htmlspecialchars($order['email']) ?></strong>
        </p>

        <!-- Back to Home -->
        <a href="<?= $base_path ?>/" class="btn-primary btn-lg">Back to Homepage</a>

      </div><!-- /.confirmation-card -->

    </div><!-- /.container -->
  </main>

<?php require_once '../includes/footer.php'; ?>
