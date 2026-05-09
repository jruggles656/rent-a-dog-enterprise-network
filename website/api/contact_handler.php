<?php
/**
 * Contact Form Handler
 *
 * Handles POST from the contact form.
 * Inserts into PostgreSQL contacts table (for AI agentic workflow).
 * Falls back to session storage if database is unavailable.
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/contact.php');
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    header('Location: ../pages/contact.php?error=missing_fields');
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../pages/contact.php?error=invalid_email');
    exit;
}

$saved = false;

// Try database INSERT first
if ($db_available) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, email, subject, message, category, status)
            VALUES (:name, :email, :subject, :message, 'uncategorized', 'new')
        ");
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':subject' => $subject,
            ':message' => $message,
        ]);
        $saved = true;

        // Log the activity
        $pdo->prepare("
            INSERT INTO activity_log (user_type, action, details)
            VALUES ('customer', 'contact_form', :details)
        ")->execute([
            ':details' => "Contact from {$name} ({$email}): {$subject}",
        ]);
    } catch (PDOException $e) {
        error_log('[RentaDog] Contact INSERT failed: ' . $e->getMessage());
    }
}

// Fallback: store in session if DB unavailable
if (!$saved) {
    if (!isset($_SESSION['contact_submissions'])) {
        $_SESSION['contact_submissions'] = [];
    }
    $_SESSION['contact_submissions'][] = [
        'name'      => htmlspecialchars($name),
        'email'     => htmlspecialchars($email),
        'subject'   => htmlspecialchars($subject),
        'message'   => htmlspecialchars($message),
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

header('Location: ../pages/contact.php?success=1');
exit;
