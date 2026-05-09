<?php
/**
 * Rent a Dog — Help Desk Ticket Submission Handler
 * IST 4910 Team 6
 *
 * Accepts POST from:
 *   - helpdesk.php (customer form)
 *   - Bark Bot escalation (AJAX, source=barkbot)
 *
 * Inserts into support_tickets table.
 * Returns redirect (form) or JSON (AJAX).
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

// Determine if this is an AJAX request (from Bark Bot)
$is_ajax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    isset($_SERVER['CONTENT_TYPE']) &&
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
);

// Parse input
if ($is_ajax) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
} else {
    $data = $_POST;
}

// Validate required fields
$name        = trim($data['name'] ?? '');
$email       = trim($data['email'] ?? '');
$subject     = trim($data['subject'] ?? '');
$description = trim($data['description'] ?? '');
$category    = trim($data['category'] ?? 'general');
$priority    = trim($data['priority'] ?? 'medium');
$source      = trim($data['source'] ?? 'helpdesk');

// Validation
$errors = [];
if (empty($name))        $errors[] = 'Name is required.';
if (empty($email))       $errors[] = 'Email is required.';
if (empty($subject))     $errors[] = 'Subject is required.';
if (empty($description)) $errors[] = 'Description is required.';

// Validate enums
$valid_categories = ['general', 'billing', 'technical', 'rental', 'complaint', 'other'];
$valid_priorities = ['low', 'medium', 'high', 'urgent'];

if (!in_array($category, $valid_categories)) $category = 'general';
if (!in_array($priority, $valid_priorities))  $priority = 'medium';

if (!empty($errors)) {
    if ($is_ajax) {
        http_response_code(400);
        echo json_encode(['error' => implode(' ', $errors)]);
    } else {
        // Redirect back with error
        header('Location: ../pages/helpdesk.php?error=' . urlencode(implode(' ', $errors)));
    }
    exit;
}

// Check DB
if (!$db_available) {
    if ($is_ajax) {
        http_response_code(503);
        echo json_encode(['error' => 'Database unavailable. Please try again later.']);
    } else {
        header('Location: ../pages/helpdesk.php?error=' . urlencode('Database unavailable. Please try again later.'));
    }
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO support_tickets (guest_name, guest_email, subject, description, category, priority, status)
        VALUES (:name, :email, :subject, :desc, :cat, :pri, 'open')
        RETURNING ticket_number
    ");
    $stmt->execute([
        ':name'  => $name,
        ':email' => $email,
        ':subject' => $subject,
        ':desc'    => $description,
        ':cat'     => $category,
        ':pri'     => $priority,
    ]);

    $ticket_number = $stmt->fetchColumn();

    // If source is barkbot, add a system note
    if ($source === 'barkbot') {
        $ticket_id_stmt = $pdo->prepare("SELECT ticket_id FROM support_tickets WHERE ticket_number = :tn");
        $ticket_id_stmt->execute([':tn' => $ticket_number]);
        $ticket_id = $ticket_id_stmt->fetchColumn();

        if ($ticket_id) {
            $note_stmt = $pdo->prepare("
                INSERT INTO ticket_updates (ticket_id, author_type, author_name, message, is_internal)
                VALUES (:tid, 'system', 'Bark Bot', :msg, FALSE)
            ");
            $note_stmt->execute([
                ':tid' => $ticket_id,
                ':msg' => 'This ticket was automatically created via Bark Bot chat escalation.',
            ]);
        }
    }

    if ($is_ajax) {
        echo json_encode([
            'success'       => true,
            'ticket_number' => $ticket_number,
            'message'       => "Ticket {$ticket_number} created successfully!",
        ]);
    } else {
        header('Location: ../pages/helpdesk.php?submitted=1&ticket=' . urlencode($ticket_number));
    }

} catch (PDOException $e) {
    error_log('[RentaDog] Ticket insert failed: ' . $e->getMessage());
    if ($is_ajax) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create ticket. Please try again.']);
    } else {
        header('Location: ../pages/helpdesk.php?error=' . urlencode('Failed to create ticket. Please try again.'));
    }
}
