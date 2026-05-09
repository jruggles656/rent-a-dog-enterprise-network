<?php
/**
 * Rent a Dog — Admin Help Desk Dashboard (Task 4.5)
 * IST 4910 Team 6
 *
 * Staff dashboard with:
 *   - Ticket metrics (total, open, avg resolution time, by category/priority)
 *   - Ticket list with status/priority management
 *   - Ability to respond, update status, assign, and mark resolved
 */

$base_path = '/';
require_once __DIR__ . '/../includes/admin_auth.php';
admin_auth_require([
    'title'       => 'Help Desk Admin',
    'theme_color' => '#8B5E3C',
    'theme_hover' => '#7A5030',
]);
require_once __DIR__ . '/../includes/db.php';

// ── Handle status update ──
$action_msg = null;
if (isset($_POST['action']) && $db_available) {
    try {
        $ticket_id = (int)$_POST['ticket_id'];

        if ($_POST['action'] === 'update_status') {
            $new_status = $_POST['new_status'];
            $now = date('Y-m-d H:i:s');

            $updates = ['status = :status'];
            $params = [':status' => $new_status, ':id' => $ticket_id];

            // Set timestamps based on status transitions
            if ($new_status === 'in_progress') {
                // Mark first response time if not yet set
                $updates[] = "first_response_at = COALESCE(first_response_at, :now)";
                $params[':now'] = $now;
            } elseif ($new_status === 'resolved') {
                $updates[] = "resolved_at = :now";
                $updates[] = "first_response_at = COALESCE(first_response_at, :now)";
                $params[':now'] = $now;
            } elseif ($new_status === 'closed') {
                $updates[] = "closed_at = :now";
                $updates[] = "resolved_at = COALESCE(resolved_at, :now)";
                $updates[] = "first_response_at = COALESCE(first_response_at, :now)";
                $params[':now'] = $now;
            }

            $sql = "UPDATE support_tickets SET " . implode(', ', $updates) . " WHERE ticket_id = :id";
            $pdo->prepare($sql)->execute($params);
            $action_msg = ['type' => 'success', 'text' => "Ticket updated to: " . ucfirst(str_replace('_', ' ', $new_status))];
        }

        if ($_POST['action'] === 'add_note') {
            $is_internal = isset($_POST['is_internal']) ? 't' : 'f';
            $stmt = $pdo->prepare("
                INSERT INTO ticket_updates (ticket_id, author_type, author_name, message, is_internal)
                VALUES (:tid, 'employee', :name, :msg, CAST(:internal AS boolean))
            ");
            $stmt->execute([
                ':tid'      => $ticket_id,
                ':name'     => $_POST['author_name'] ?? 'Admin',
                ':msg'      => $_POST['note_message'],
                ':internal' => $is_internal,
            ]);

            // Also set first_response_at if this is a customer-visible reply
            if (!isset($_POST['is_internal'])) {
                $pdo->prepare("
                    UPDATE support_tickets
                    SET first_response_at = COALESCE(first_response_at, CURRENT_TIMESTAMP)
                    WHERE ticket_id = :id
                ")->execute([':id' => $ticket_id]);
            }

            $action_msg = ['type' => 'success', 'text' => 'Note added to ticket.'];
        }
    } catch (PDOException $e) {
        error_log('[RentaDog] Admin helpdesk action failed: ' . $e->getMessage());
        $action_msg = ['type' => 'error', 'text' => 'Action failed: ' . $e->getMessage()];
    }
}

// ── Fetch metrics ──
$metrics = [
    'total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0,
    'avg_resolution' => null, 'urgent_open' => 0,
];
$tickets = [];
$category_stats = [];
$priority_stats = [];

if ($db_available) {
    try {
        // Summary metrics
        $metrics = $pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'open') AS open,
                COUNT(*) FILTER (WHERE status = 'in_progress') AS in_progress,
                COUNT(*) FILTER (WHERE status IN ('resolved', 'closed')) AS resolved,
                ROUND(AVG(resolution_minutes) FILTER (WHERE resolution_minutes IS NOT NULL)) AS avg_resolution,
                COUNT(*) FILTER (WHERE priority = 'urgent' AND status NOT IN ('resolved', 'closed')) AS urgent_open
            FROM support_tickets
        ")->fetch();

        // Per-category breakdown
        $category_stats = $pdo->query("
            SELECT category,
                   COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE status NOT IN ('resolved', 'closed')) AS active,
                   ROUND(AVG(resolution_minutes) FILTER (WHERE resolution_minutes IS NOT NULL)) AS avg_res
            FROM support_tickets
            GROUP BY category
            ORDER BY total DESC
        ")->fetchAll();

        // Per-priority breakdown
        $priority_stats = $pdo->query("
            SELECT priority,
                   COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE status NOT IN ('resolved', 'closed')) AS active
            FROM support_tickets
            GROUP BY priority
            ORDER BY CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END
        ")->fetchAll();

        // All tickets with updates count
        $tickets = $pdo->query("
            SELECT t.*,
                   (SELECT COUNT(*) FROM ticket_updates u WHERE u.ticket_id = t.ticket_id) AS update_count
            FROM support_tickets t
            ORDER BY
                CASE t.status
                    WHEN 'open' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'waiting_on_customer' THEN 3
                    WHEN 'resolved' THEN 4
                    WHEN 'closed' THEN 5
                END,
                CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
                t.created_at ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log('[RentaDog] Admin helpdesk query failed: ' . $e->getMessage());
    }
}

function formatRes($minutes) {
    if ($minutes === null) return '&mdash;';
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Desk Admin &mdash; Rent a Dog</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; color: #1a1a2e; line-height: 1.6; }

        .header { background: linear-gradient(135deg, #3E2723 0%, #5C3D2E 100%); color: #fff; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .header h1 { font-size: 1.5rem; }
        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #8B5E3C; color: #fff; }
        .btn-primary:hover { background: #7A5030; }
        .btn-back { background: rgba(255,255,255,0.15); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,0.25); }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }

        .container { max-width: 1300px; margin: 24px auto; padding: 0 24px; }

        /* ── Metrics Grid ── */
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .metric-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .metric-label { font-size: 0.78rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-value { font-size: 2rem; font-weight: 700; margin-top: 4px; }
        .metric-value.brown  { color: #8B5E3C; }
        .metric-value.orange { color: #E07A55; }
        .metric-value.blue   { color: #3498db; }
        .metric-value.green  { color: #27ae60; }
        .metric-value.red    { color: #e74c3c; }
        .metric-sub { font-size: 0.8rem; color: #aaa; margin-top: 2px; }

        /* ── Breakdown Section ── */
        .breakdown-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .breakdown-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .breakdown-card h3 { font-size: 1rem; margin-bottom: 12px; color: #3E2723; }
        .breakdown-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .breakdown-table th { text-align: left; color: #888; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.3px; padding: 8px 6px; border-bottom: 2px solid #eee; }
        .breakdown-table td { padding: 8px 6px; border-bottom: 1px solid #f0f0f0; }

        /* ── Alert ── */
        .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── Ticket Cards ── */
        .ticket-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid #ddd; }
        .ticket-card.priority-urgent { border-left-color: #e74c3c; }
        .ticket-card.priority-high { border-left-color: #f39c12; }
        .ticket-card.priority-medium { border-left-color: #3498db; }
        .ticket-card.priority-low { border-left-color: #95a5a6; }

        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        .ticket-num { font-family: monospace; font-size: 0.9rem; font-weight: 700; color: #8B5E3C; }
        .ticket-badges { display: flex; gap: 6px; flex-wrap: wrap; }

        .badge { padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge-open { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #cce5ff; color: #004085; }
        .badge-waiting_on_customer { background: #ffeeba; color: #856404; }
        .badge-resolved { background: #d4edda; color: #155724; }
        .badge-closed { background: #e2e3e5; color: #383d41; }
        .badge-urgent { background: #f8d7da; color: #721c24; }
        .badge-high { background: #ffeeba; color: #856404; }
        .badge-medium { background: #cce5ff; color: #004085; }
        .badge-low { background: #e2e3e5; color: #383d41; }
        .badge-cat { background: #e8e0f0; color: #5C3D2E; }
        .badge-source { background: #e8f4fd; color: #2980b9; font-style: italic; }

        .ticket-subject { font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; color: #2d3436; }
        .ticket-from { font-size: 0.85rem; color: #888; margin-bottom: 10px; }
        .ticket-desc { background: #f8f9fa; padding: 14px; border-radius: 8px; margin-bottom: 14px; white-space: pre-wrap; font-size: 0.9rem; }

        .ticket-timestamps { display: flex; gap: 20px; font-size: 0.8rem; color: #aaa; margin-bottom: 14px; flex-wrap: wrap; }
        .ticket-timestamps strong { color: #666; }

        /* ── Inline Actions ── */
        .ticket-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid #eee; }
        .ticket-actions form { display: flex; gap: 6px; align-items: center; }
        .ticket-actions select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem; }

        /* ── Add Note ── */
        .note-toggle { color: #8B5E3C; font-weight: 600; cursor: pointer; font-size: 0.85rem; background: none; border: none; text-decoration: underline; }
        .note-form { display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
        .note-form.show { display: block; }
        .note-form textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; min-height: 80px; }
        .note-form-actions { display: flex; gap: 10px; align-items: center; margin-top: 8px; }
        .note-form label { font-size: 0.82rem; color: #888; cursor: pointer; }

        /* ── Updates / History ── */
        .ticket-updates { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
        .ticket-updates h4 { font-size: 0.85rem; color: #888; margin-bottom: 8px; }
        .update-item { background: #f8f9fa; padding: 10px 14px; border-radius: 8px; margin-bottom: 8px; font-size: 0.88rem; }
        .update-item.internal { background: #fff8e1; border-left: 3px solid #f39c12; }
        .update-meta { font-size: 0.75rem; color: #aaa; margin-top: 4px; }

        .empty-state { text-align: center; color: #b2bec3; padding: 48px 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

        @media (max-width: 768px) {
            .breakdown-row { grid-template-columns: 1fr; }
            .ticket-header { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Help Desk Admin</h1>
        <div class="header-actions">
            <a href="/" class="btn btn-back">Back to Site</a>
            <a href="/pages/admin_contacts.php" class="btn btn-back">Contact Dashboard</a>
            <a href="?logout=1" class="btn btn-back">Logout</a>
        </div>
    </div>

    <div class="container">

        <?php if ($action_msg): ?>
            <div class="alert alert-<?= $action_msg['type'] ?>"><?= htmlspecialchars($action_msg['text']) ?></div>
        <?php endif; ?>

        <?php if (!$db_available): ?>
            <div class="alert alert-error">Database is unavailable. Cannot display help desk data.</div>
        <?php else: ?>

        <!-- ═══ METRICS ═══ -->
        <div class="metrics">
            <div class="metric-card">
                <div class="metric-label">Total Tickets</div>
                <div class="metric-value brown"><?= $metrics['total'] ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Open</div>
                <div class="metric-value orange"><?= $metrics['open'] ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">In Progress</div>
                <div class="metric-value blue"><?= $metrics['in_progress'] ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Resolved / Closed</div>
                <div class="metric-value green"><?= $metrics['resolved'] ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Avg Resolution Time</div>
                <div class="metric-value brown"><?= formatRes($metrics['avg_resolution']) ?></div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Urgent (Open)</div>
                <div class="metric-value red"><?= $metrics['urgent_open'] ?></div>
            </div>
        </div>

        <!-- ═══ BREAKDOWN ═══ -->
        <div class="breakdown-row">
            <div class="breakdown-card">
                <h3>By Category</h3>
                <table class="breakdown-table">
                    <thead><tr><th>Category</th><th>Total</th><th>Active</th><th>Avg Resolution</th></tr></thead>
                    <tbody>
                    <?php foreach ($category_stats as $cat): ?>
                        <tr>
                            <td><?= ucfirst(htmlspecialchars($cat['category'])) ?></td>
                            <td><?= $cat['total'] ?></td>
                            <td><?= $cat['active'] ?></td>
                            <td><?= formatRes($cat['avg_res']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($category_stats)): ?>
                        <tr><td colspan="4" style="color:#aaa; text-align:center;">No data yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="breakdown-card">
                <h3>By Priority</h3>
                <table class="breakdown-table">
                    <thead><tr><th>Priority</th><th>Total</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($priority_stats as $pri): ?>
                        <tr>
                            <td><?= ucfirst(htmlspecialchars($pri['priority'])) ?></td>
                            <td><?= $pri['total'] ?></td>
                            <td><?= $pri['active'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($priority_stats)): ?>
                        <tr><td colspan="3" style="color:#aaa; text-align:center;">No data yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ TICKET LIST ═══ -->
        <?php if (empty($tickets)): ?>
            <div class="empty-state">No tickets yet. Customers can submit tickets from the Help Desk page.</div>
        <?php endif; ?>

        <?php foreach ($tickets as $i => $t): ?>
        <?php
            // Fetch updates for this ticket
            $updates = $pdo->prepare("
                SELECT * FROM ticket_updates
                WHERE ticket_id = :tid
                ORDER BY created_at ASC
            ");
            $updates->execute([':tid' => $t['ticket_id']]);
            $ticket_updates = $updates->fetchAll();
        ?>
        <div class="ticket-card priority-<?= htmlspecialchars($t['priority']) ?>">
            <div class="ticket-header">
                <div>
                    <span class="ticket-num"><?= htmlspecialchars($t['ticket_number']) ?></span>
                </div>
                <div class="ticket-badges">
                    <span class="badge badge-<?= htmlspecialchars($t['status']) ?>"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($t['status']))) ?></span>
                    <span class="badge badge-<?= htmlspecialchars($t['priority']) ?>"><?= ucfirst(htmlspecialchars($t['priority'])) ?></span>
                    <span class="badge badge-cat"><?= ucfirst(htmlspecialchars($t['category'])) ?></span>
                    <?php if ($t['update_count'] > 0): ?>
                        <span class="badge badge-source"><?= $t['update_count'] ?> update<?= $t['update_count'] > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-subject"><?= htmlspecialchars($t['subject']) ?></div>
            <div class="ticket-from">
                From: <?= htmlspecialchars($t['guest_name'] ?? 'Unknown') ?> &lt;<?= htmlspecialchars($t['guest_email'] ?? '') ?>&gt;
            </div>
            <div class="ticket-desc"><?= htmlspecialchars($t['description']) ?></div>

            <div class="ticket-timestamps">
                <span><strong>Created:</strong> <?= $t['created_at'] ?></span>
                <?php if ($t['first_response_at']): ?>
                    <span><strong>First Response:</strong> <?= $t['first_response_at'] ?></span>
                <?php endif; ?>
                <?php if ($t['resolved_at']): ?>
                    <span><strong>Resolved:</strong> <?= $t['resolved_at'] ?> (<?= formatRes($t['resolution_minutes']) ?>)</span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="ticket-actions">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="ticket_id" value="<?= $t['ticket_id'] ?>">
                    <select name="new_status">
                        <option value="open" <?= $t['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="waiting_on_customer" <?= $t['status'] === 'waiting_on_customer' ? 'selected' : '' ?>>Waiting on Customer</option>
                        <option value="resolved" <?= $t['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= $t['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </form>

                <button class="note-toggle" onclick="document.getElementById('note-<?= $i ?>').classList.toggle('show')">+ Add Note</button>
            </div>

            <!-- Add Note Form -->
            <div class="note-form" id="note-<?= $i ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    <input type="hidden" name="ticket_id" value="<?= $t['ticket_id'] ?>">
                    <input type="hidden" name="author_name" value="Admin">
                    <textarea name="note_message" placeholder="Write a response or internal note..." required></textarea>
                    <div class="note-form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
                        <label><input type="checkbox" name="is_internal" value="1"> Internal only (not visible to customer)</label>
                    </div>
                </form>
            </div>

            <!-- Existing Updates -->
            <?php if (!empty($ticket_updates)): ?>
            <div class="ticket-updates">
                <h4>History (<?= count($ticket_updates) ?> update<?= count($ticket_updates) > 1 ? 's' : '' ?>)</h4>
                <?php foreach ($ticket_updates as $u): ?>
                <div class="update-item <?= $u['is_internal'] ? 'internal' : '' ?>">
                    <?= htmlspecialchars($u['message']) ?>
                    <div class="update-meta">
                        <?= htmlspecialchars($u['author_name'] ?? $u['author_type']) ?>
                        &middot; <?= $u['created_at'] ?>
                        <?= $u['is_internal'] ? ' &middot; <em>Internal</em>' : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</body>
</html>
