<?php
// AJAX endpoint to test all active alert rules for the current admin
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
header('Content-Type: application/json');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get all active alert rules for this admin
try {
    $stmt = $pdo->prepare("
        SELECT ar.*, as_settings.email_notifications, as_settings.in_app_notifications, as_settings.email_frequency
        FROM alert_rules ar
        LEFT JOIN alert_settings as_settings ON ar.created_by = as_settings.admin_id
        WHERE ar.is_active = 1 AND ar.created_by = ?
    ");
    $stmt->execute([$admin_id]);
    $alert_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching alert rules']);
    exit();
}

require_once __DIR__ . '/process_alerts.php';

$total_alerts_triggered = 0;
$results = [];
foreach ($alert_rules as $rule) {
    try {
        $alerts_triggered = processAlertRule($pdo, $rule);
        $total_alerts_triggered += $alerts_triggered;
        $results[] = [
            'rule' => $rule['name'],
            'triggered' => $alerts_triggered
        ];
    } catch (Exception $e) {
        $results[] = [
            'rule' => $rule['name'],
            'triggered' => 0,
            'error' => $e->getMessage()
        ];
    }
}
echo json_encode([
    'success' => true,
    'total_triggered' => $total_alerts_triggered,
    'results' => $results
]);
