<?php
/**
 * SMTP Connection Test
 * Tests the SMTP connection without sending an email
 */

require_once 'config.php';
require_once 'config/email_config.php';

// Check if PHPMailer is available
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h4>❌ PHPMailer Not Available</h4>";
    echo "<p>PHPMailer is required for SMTP connection testing.</p>";
    echo "</div>";
    exit;
}

if (!SMTP_ENABLED) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px;'>";
    echo "<h4>⚠️ SMTP Disabled</h4>";
    echo "<p>SMTP is disabled in your configuration. Enable it in email_config.php to test SMTP connection.</p>";
    echo "</div>";
    exit;
}

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Enable verbose debugging
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "<div style='background: #e9ecef; padding: 10px; margin: 5px 0; border-radius: 3px; font-family: monospace; font-size: 12px;'>";
        echo htmlspecialchars($str);
        echo "</div>";
    };
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    $mail->Timeout = 30; // 30 second timeout
    
    echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px;'>";
    echo "<h4>🔄 Testing SMTP Connection...</h4>";
    echo "<p><strong>Host:</strong> " . htmlspecialchars(SMTP_HOST) . "</p>";
    echo "<p><strong>Port:</strong> " . SMTP_PORT . "</p>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars(SMTP_USERNAME) . "</p>";
    echo "<p><strong>Encryption:</strong> " . htmlspecialchars(SMTP_ENCRYPTION) . "</p>";
    echo "</div>";
    
    // Test connection
    if ($mail->smtpConnect()) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
        echo "<h4>✅ SMTP Connection Successful!</h4>";
        echo "<p>Your SMTP configuration is working correctly.</p>";
        echo "</div>";
        $mail->smtpClose();
    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
        echo "<h4>❌ SMTP Connection Failed</h4>";
        echo "<p>Could not connect to SMTP server. Please check your configuration.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h4>❌ SMTP Connection Error</h4>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Possible Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Check your SMTP credentials (username/password)</li>";
    echo "<li>Verify SMTP host and port settings</li>";
    echo "<li>Ensure your email provider allows SMTP access</li>";
    echo "<li>Check if 2-factor authentication requires an app password</li>";
    echo "<li>Verify firewall settings allow outbound SMTP connections</li>";
    echo "</ul>";
    echo "</div>";
}
?>

