<?php
session_start();

// Database connection (replace with your actual database credentials)
$host = 'localhost';
$dbname = 'report-database';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }
    
    try {
        // Query to check admin credentials
        $stmt = $pdo->prepare("SELECT id, name, username, password FROM admins WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Login successful
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_username'] = $admin['username'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful',
                'redirect' => 'dashboard.php'
            ]);
        } else {
            // Login failed
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
