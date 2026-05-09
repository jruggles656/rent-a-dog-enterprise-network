<?php
/**
 * Rent a Dog — Database Connection (PDO → PostgreSQL)
 * IST 4910 Team 6 | Database VM: 10.0.1.200
 *
 * Usage: require_once __DIR__ . '/db.php';
 *        Then use $pdo for queries, or check $db_available flag.
 *
 * Graceful degradation: if PostgreSQL is unreachable, $pdo is null
 * and $db_available is false. Pages fall back to hardcoded sample data.
 */

$db_host = '10.0.1.200';
$db_port = '5432';
$db_name = 'rentadog';
$db_user = 'rentadog_app';
// FIND-012 / A3: DB password loaded from C:/ProgramData/RentaDog/admin.env (outside docroot)
require_once __DIR__ . '/secrets.php';
$db_pass = rentadog_secret('DB_PASSWORD');

$pdo = null;
$db_available = false;

try {
    $pdo = new PDO(
        "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $db_available = true;
} catch (PDOException $e) {
    // Connection failed — site continues with hardcoded data
    // Log error for debugging (visible in Apache error log)
    error_log('[RentaDog] PostgreSQL connection failed: ' . $e->getMessage());
    $pdo = null;
    $db_available = false;
}
