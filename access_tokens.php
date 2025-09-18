<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Database connection
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

$admin_id = $_SESSION['admin_id'];
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_token'])) {
    $access_token = trim($_POST['access_token']);
    
    if (empty($access_token)) {
        $message = 'Access token is required.';
        $message_type = 'error';
    } else {
        try {
            // Check if admin already has a token
            $stmt = $pdo->prepare("SELECT id FROM facebook_access_tokens WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
            $existing_token = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_token) {
                // Update existing token
                $stmt = $pdo->prepare("UPDATE facebook_access_tokens SET access_token = ?, last_update = NOW(), updated_at = NOW() WHERE admin_id = ?");
                $stmt->execute([$access_token, $admin_id]);
                $message = 'Access token updated successfully!';
                $message_type = 'success';
            } else {
                // Insert new token
                $stmt = $pdo->prepare("INSERT INTO facebook_access_tokens (admin_id, access_token, last_update, created_at, updated_at) VALUES (?, ?, NOW(), NOW(), NOW())");
                $stmt->execute([$admin_id, $access_token]);
                $message = 'Access token saved successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error updating access token: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current token for display
$current_token = '';
try {
    $stmt = $pdo->prepare("SELECT access_token, last_update FROM facebook_access_tokens WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token_data) {
        $current_token = $token_data['access_token'];
    }
} catch (PDOException $e) {
    // Handle error silently
}

// Set page variables for template
$page_title = 'Access Tokens - JJ Reporting Dashboard';

// Include header template
include 'templates/header.php';
?>

    <!-- Include sidebar template -->
    <?php include 'templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <?php include 'templates/top_header.php'; ?>

        <!-- Access Tokens Content -->
        <div class="dashboard-content">
            <div class="access-tokens-container">
                <div class="tokens-header">
                    <h2>Facebook Access Token Management</h2>
                    <p>Update your Facebook access token to maintain API connectivity for your ad accounts.</p>
                </div>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="token-form-container">
                    <form method="POST" class="token-form">
                        <div class="form-group">
                            <label for="access_token">
                                <i class="fas fa-key"></i>
                                Facebook Access Token
                            </label>
                            <textarea 
                                id="access_token" 
                                name="access_token" 
                                placeholder="Enter your Facebook access token here..."
                                rows="4"
                                required
                            ><?php echo htmlspecialchars($current_token); ?></textarea>
                            <small class="form-help">
                                <i class="fas fa-info-circle"></i>
                                Paste your Facebook access token above. This token will be used to access your Facebook Ad accounts.
                            </small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_token" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update Token
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearToken()">
                                <i class="fas fa-eraser"></i>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($current_token): ?>
                    <div class="token-info">
                        <h3>Current Token Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Token Status:</span>
                                <span class="info-value status-active">
                                    <i class="fas fa-check-circle"></i>
                                    Active
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Updated:</span>
                                <span class="info-value">
                                    <?php 
                                    if (isset($token_data['last_update'])) {
                                        echo date('M j, Y \a\t g:i A', strtotime($token_data['last_update']));
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Token Preview:</span>
                                <span class="info-value token-preview">
                                    <?php echo substr($current_token, 0, 20) . '...' . substr($current_token, -10); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-token">
                        <i class="fas fa-key"></i>
                        <h3>No Access Token Found</h3>
                        <p>You haven't set up a Facebook access token yet. Please add one above to start managing your ad accounts.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php 
    $inline_scripts = "
        function clearToken() {
            if (confirm('Are you sure you want to clear the access token?')) {
                document.getElementById('access_token').value = '';
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.remove();
                }, 300);
            });
        }, 5000);
    ";
    include 'templates/footer.php'; 
    ?>
