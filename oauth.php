<?php
require_once 'config.php';
session_start();

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (isset($_GET['action']) && $_GET['action'] === 'connect') {
    // Generate state parameter for security
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    
    // Build OAuth URL
    $oauth_url = META_OAUTH_URL . "?" . http_build_query([
        'client_id' => META_APP_ID,
        'redirect_uri' => META_REDIRECT_URI,
        'scope' => META_SCOPE,
        'state' => $state,
        'response_type' => 'code'
    ]);
    
    // Redirect to Facebook OAuth
    header('Location: ' . $oauth_url);
    exit;
}

// If no action specified, return error
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
