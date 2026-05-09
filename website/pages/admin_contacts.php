<?php
/**
 * Bark Bot — Admin Contact Dashboard
 * IST 4910 Team 6
 *
 * Shows all contact form submissions with AI-generated categories
 * and draft responses. Includes a button to trigger AI processing
 * of pending contacts.
 */

$base_path = '/';
require_once __DIR__ . '/../includes/admin_auth.php';
admin_auth_require([
    'title'       => 'Bark Bot Admin',
    'theme_color' => '#6c5ce7',
    'theme_hover' => '#5a4bd1',
]);
require_once __DIR__ . '/../includes/db.php';

// Handle process trigger
if (isset($_GET['process']) && $_GET['process'] === '1') {
    $ch = curl_init('http://localhost/api/barkbot_process.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $processResult = json_decode(curl_exec($ch), true);
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bark Bot Admin — Contact Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; color: #1a1a2e; line-height: 1.6; }

        .header { background: linear-gradient(135deg, #2d3436 0%, #636e72 100%); color: #fff; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5rem; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #6c5ce7; color: #fff; }
        .btn-primary:hover { background: #5a4bd1; }
        .btn-back { background: rgba(255,255,255,0.15); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,0.25); }

        .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }

        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 2rem; font-weight: 700; margin-top: 4px; }
        .stat-value.blue { color: #6c5ce7; }
        .stat-value.green { color: #00b894; }
        .stat-value.orange { color: #fdcb6e; }
        .stat-value.red { color: #d63031; }

        .alert { padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .contact-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .contact-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .contact-name { font-size: 1.1rem; font-weight: 600; }
        .contact-email { color: #888; font-size: 0.85rem; }
        .contact-meta { display: flex; gap: 8px; align-items: center; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-new { background: #dfe6e9; color: #636e72; }
        .badge-responded { background: #d4edda; color: #155724; }
        .badge-escalated { background: #fff3cd; color: #856404; }
        .badge-pricing { background: #e8f4fd; color: #2980b9; }
        .badge-booking { background: #e8f8f5; color: #1abc9c; }
        .badge-complaint { background: #fdf2e9; color: #e67e22; }
        .badge-feedback { background: #f4ecf7; color: #8e44ad; }
        .badge-partnership { background: #eaf2f8; color: #2c3e50; }
        .badge-general { background: #f2f3f4; color: #7f8c8d; }

        .contact-subject { font-weight: 600; margin-bottom: 8px; color: #2d3436; }
        .contact-message { background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px; white-space: pre-wrap; }

        .ai-section { border-top: 2px solid #6c5ce7; padding-top: 16px; }
        .ai-label { font-size: 0.8rem; color: #6c5ce7; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .ai-response { background: #f0ecfc; padding: 16px; border-radius: 8px; color: #2d3436; white-space: pre-wrap; }
        .ai-pending { color: #b2bec3; font-style: italic; }

        .timestamp { color: #b2bec3; font-size: 0.8rem; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bark Bot Admin — Contact Dashboard</h1>
        <div class="header-actions">
            <a href="/" class="btn btn-back">Back to Site</a>
            <a href="?process=1" class="btn btn-primary" onclick="this.textContent='Processing...'">Run Bark Bot AI</a>
            <a href="?logout=1" class="btn btn-back">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($processResult)): ?>
            <?php if (isset($processResult['processed'])): ?>
                <div class="alert alert-success">
                    Bark Bot processed <?= $processResult['processed'] ?> of <?= $processResult['total'] ?> pending contacts.
                </div>
            <?php elseif (isset($processResult['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($processResult['error']) ?></div>
            <?php elseif (isset($processResult['message'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($processResult['message']) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($db_available): ?>
            <?php
            // Get stats
            $stats = $pdo->query("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'new') AS new_count,
                    COUNT(*) FILTER (WHERE status = 'responded') AS responded,
                    COUNT(*) FILTER (WHERE ai_response IS NOT NULL) AS ai_processed
                FROM contacts
            ")->fetch();

            // Get all contacts
            $contacts = $pdo->query("
                SELECT * FROM contacts ORDER BY created_at DESC
            ")->fetchAll();
            ?>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Contacts</div>
                    <div class="stat-value blue"><?= $stats['total'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending (New)</div>
                    <div class="stat-value orange"><?= $stats['new_count'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">AI Processed</div>
                    <div class="stat-value green"><?= $stats['ai_processed'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Responded</div>
                    <div class="stat-value green"><?= $stats['responded'] ?></div>
                </div>
            </div>

            <?php if (empty($contacts)): ?>
                <div class="contact-card" style="text-align:center; color:#b2bec3; padding:48px;">
                    No contact submissions yet. Submit a message on the Contact page to test!
                </div>
            <?php endif; ?>

            <?php foreach ($contacts as $c): ?>
                <div class="contact-card">
                    <div class="contact-header">
                        <div>
                            <div class="contact-name"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="contact-email"><?= htmlspecialchars($c['email']) ?></div>
                        </div>
                        <div class="contact-meta">
                            <span class="badge badge-<?= htmlspecialchars($c['category']) ?>"><?= htmlspecialchars($c['category']) ?></span>
                            <span class="badge badge-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span>
                        </div>
                    </div>

                    <div class="contact-subject"><?= htmlspecialchars($c['subject']) ?></div>
                    <div class="contact-message"><?= htmlspecialchars($c['message']) ?></div>

                    <div class="ai-section">
                        <div class="ai-label">Bark Bot AI Response</div>
                        <?php if ($c['ai_response']): ?>
                            <div class="ai-response"><?= htmlspecialchars($c['ai_response']) ?></div>
                        <?php else: ?>
                            <div class="ai-pending">Pending — click "Run Bark Bot AI" to process</div>
                        <?php endif; ?>
                    </div>

                    <div class="timestamp">
                        Submitted: <?= $c['created_at'] ?>
                        <?php if ($c['responded_at']): ?>
                            &nbsp;|&nbsp; AI responded: <?= $c['responded_at'] ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="alert alert-error">Database is unavailable. Cannot display contacts.</div>
        <?php endif; ?>
    </div>
</body>
</html>
