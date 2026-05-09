<?php
/**
 * Rent a Dog — Help Desk Page (Task 4.5)
 * IST 4910 Team 6
 *
 * Customer-facing help desk with:
 *   - Ticket submission form
 *   - Ticket lookup by email or ticket number
 *   - Live status tracking with resolution time display
 */
$page_title = 'Help Desk';
require_once '../includes/header.php';

// Handle ticket lookup
$lookup_results = null;
$lookup_error = null;
$lookup_query = '';

if (isset($_GET['lookup']) && $db_available) {
    $lookup_query = trim($_GET['lookup']);
    if (!empty($lookup_query)) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    ticket_number, subject, description, category, priority, status,
                    created_at, first_response_at, resolved_at, closed_at,
                    resolution_minutes, guest_name, guest_email
                FROM support_tickets
                WHERE ticket_number = :q
                   OR LOWER(guest_email) = LOWER(:q)
                ORDER BY created_at DESC
            ");
            $stmt->execute([':q' => $lookup_query]);
            $lookup_results = $stmt->fetchAll();

            if (empty($lookup_results)) {
                $lookup_error = 'No tickets found. Check your ticket number or email and try again.';
            }
        } catch (PDOException $e) {
            error_log('[RentaDog] Ticket lookup failed: ' . $e->getMessage());
            $lookup_error = 'Something went wrong. Please try again.';
        }
    }
}

// Status display helpers
function statusLabel($status) {
    $map = [
        'open'                => 'Open',
        'in_progress'         => 'In Progress',
        'waiting_on_customer' => 'Waiting on You',
        'resolved'            => 'Resolved',
        'closed'              => 'Closed',
    ];
    return $map[$status] ?? ucfirst($status);
}

function statusClass($status) {
    $map = [
        'open'                => 'status-open',
        'in_progress'         => 'status-progress',
        'waiting_on_customer' => 'status-waiting',
        'resolved'            => 'status-resolved',
        'closed'              => 'status-closed',
    ];
    return $map[$status] ?? 'status-open';
}

function priorityClass($priority) {
    $map = [
        'low'    => 'priority-low',
        'medium' => 'priority-medium',
        'high'   => 'priority-high',
        'urgent' => 'priority-urgent',
    ];
    return $map[$priority] ?? 'priority-medium';
}

function formatResolution($minutes) {
    if ($minutes === null) return null;
    $minutes = (int)$minutes;
    if ($minutes < 60) return $minutes . ' min';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours < 24) return $hours . 'h ' . $mins . 'm';
    $days = floor($hours / 24);
    $hrs = $hours % 24;
    return $days . 'd ' . $hrs . 'h';
}
?>

  <!-- ============================================================
       HELP DESK PAGE
       ============================================================ -->
  <main class="helpdesk-page">
    <div class="container">

      <!-- Page Header -->
      <header class="page-header reveal">
        <h1 class="page-title">Help Desk</h1>
        <p class="handwritten page-subtitle">We're here to help</p>
      </header>

      <!-- Success Banner -->
      <?php if (isset($_GET['submitted']) && $_GET['submitted'] == '1'): ?>
      <div class="alert alert-success reveal">
        <strong>Ticket submitted!</strong> Your ticket number is <strong><?= htmlspecialchars($_GET['ticket'] ?? '') ?></strong>.
        Save it to check your status anytime. We'll get on it soon!
      </div>
      <?php endif; ?>

      <!-- Two-Panel Layout -->
      <div class="helpdesk-grid reveal">

        <!-- LEFT — Submit a Ticket -->
        <div class="helpdesk-col helpdesk-submit-col">
          <div class="helpdesk-card">
            <h2 class="helpdesk-card-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -4px; margin-right: 6px;">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="12" y1="18" x2="12" y2="12"/>
                <line x1="9" y1="15" x2="15" y2="15"/>
              </svg>
              Submit a Ticket
            </h2>
            <p class="helpdesk-card-desc">Having an issue or need help? Let us know and we'll track it to resolution.</p>

            <form method="POST" action="<?= $base_path ?>/api/helpdesk_handler.php" class="helpdesk-form">
              <div class="form-group">
                <label class="form-label" for="hd_name">Your Name</label>
                <input type="text" id="hd_name" name="name" class="form-input" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="hd_email">Email Address</label>
                <input type="email" id="hd_email" name="email" class="form-input" required>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="hd_category">Category</label>
                  <select id="hd_category" name="category" class="form-input" required>
                    <option value="">Select...</option>
                    <option value="general">General</option>
                    <option value="billing">Billing</option>
                    <option value="technical">Technical</option>
                    <option value="rental">Rental Issue</option>
                    <option value="complaint">Complaint</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label" for="hd_priority">Priority</label>
                  <select id="hd_priority" name="priority" class="form-input" required>
                    <option value="medium" selected>Medium</option>
                    <option value="low">Low</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="hd_subject">Subject</label>
                <input type="text" id="hd_subject" name="subject" class="form-input" required placeholder="Brief summary of your issue">
              </div>

              <div class="form-group">
                <label class="form-label" for="hd_description">Description</label>
                <textarea id="hd_description" name="description" class="form-input form-textarea" rows="5" required placeholder="Please describe your issue in detail..."></textarea>
              </div>

              <!-- Hidden field for Bark Bot escalation -->
              <input type="hidden" id="hd_source" name="source" value="helpdesk">

              <button type="submit" class="btn-primary">Submit Ticket</button>
            </form>
          </div>
        </div>

        <!-- RIGHT — Lookup a Ticket -->
        <div class="helpdesk-col helpdesk-lookup-col">
          <div class="helpdesk-card">
            <h2 class="helpdesk-card-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -4px; margin-right: 6px;">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              Track Your Ticket
            </h2>
            <p class="helpdesk-card-desc">Enter your ticket number (e.g. TKT-00001) or email to check status.</p>

            <form method="GET" action="" class="helpdesk-lookup-form">
              <div class="form-group">
                <label class="form-label" for="hd_lookup">Ticket # or Email</label>
                <div class="helpdesk-lookup-row">
                  <input type="text" id="hd_lookup" name="lookup" class="form-input" placeholder="TKT-00001 or your@email.com" value="<?= htmlspecialchars($lookup_query) ?>" required>
                  <button type="submit" class="btn-primary helpdesk-lookup-btn">Look Up</button>
                </div>
              </div>
            </form>

            <?php if ($lookup_error): ?>
              <div class="helpdesk-lookup-empty"><?= htmlspecialchars($lookup_error) ?></div>
            <?php endif; ?>

            <?php if ($lookup_results): ?>
              <div class="helpdesk-results">
                <?php foreach ($lookup_results as $ticket): ?>
                <div class="helpdesk-ticket-card">
                  <div class="helpdesk-ticket-header">
                    <span class="helpdesk-ticket-num"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                    <span class="helpdesk-badge <?= statusClass($ticket['status']) ?>"><?= statusLabel($ticket['status']) ?></span>
                  </div>

                  <h3 class="helpdesk-ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></h3>

                  <div class="helpdesk-ticket-meta">
                    <span class="helpdesk-badge <?= priorityClass($ticket['priority']) ?>"><?= ucfirst(htmlspecialchars($ticket['priority'])) ?></span>
                    <span class="helpdesk-badge helpdesk-badge-cat"><?= ucfirst(htmlspecialchars($ticket['category'])) ?></span>
                  </div>

                  <p class="helpdesk-ticket-desc"><?= htmlspecialchars($ticket['description']) ?></p>

                  <!-- Timeline -->
                  <div class="helpdesk-timeline">
                    <div class="helpdesk-timeline-item active">
                      <span class="helpdesk-timeline-dot"></span>
                      <span class="helpdesk-timeline-label">Submitted</span>
                      <span class="helpdesk-timeline-date"><?= date('M j, g:i A', strtotime($ticket['created_at'])) ?></span>
                    </div>

                    <div class="helpdesk-timeline-item <?= $ticket['first_response_at'] ? 'active' : '' ?>">
                      <span class="helpdesk-timeline-dot"></span>
                      <span class="helpdesk-timeline-label">First Response</span>
                      <span class="helpdesk-timeline-date"><?= $ticket['first_response_at'] ? date('M j, g:i A', strtotime($ticket['first_response_at'])) : 'Pending' ?></span>
                    </div>

                    <div class="helpdesk-timeline-item <?= $ticket['resolved_at'] ? 'active' : '' ?>">
                      <span class="helpdesk-timeline-dot"></span>
                      <span class="helpdesk-timeline-label">Resolved</span>
                      <span class="helpdesk-timeline-date">
                        <?php if ($ticket['resolved_at']): ?>
                          <?= date('M j, g:i A', strtotime($ticket['resolved_at'])) ?>
                          <span class="helpdesk-resolution-time">(<?= formatResolution($ticket['resolution_minutes']) ?>)</span>
                        <?php else: ?>
                          Pending
                        <?php endif; ?>
                      </span>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>

      </div><!-- /.helpdesk-grid -->

    </div><!-- /.container -->
  </main>

<?php require_once '../includes/footer.php'; ?>
