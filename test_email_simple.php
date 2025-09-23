<?php
/**
 * Simple Email Test (PHP mail() function only)
 * Tests email sending using PHP's built-in mail() function
 */

require_once 'config.php';
require_once 'config/email_config.php';

echo "<h2>Simple Email Test (PHP mail() only)</h2>";

// Check if we can use PHP mail()
if (!function_exists('mail')) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h4>❌ PHP mail() Function Not Available</h4>";
    echo "<p>The PHP mail() function is not available on this server.</p>";
    echo "</div>";
    exit;
}

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<h4>📧 PHP mail() Configuration</h4>";
echo "<p><strong>From Email:</strong> " . htmlspecialchars(FROM_EMAIL) . "</p>";
echo "<p><strong>From Name:</strong> " . htmlspecialchars(FROM_NAME) . "</p>";
echo "<p><strong>Reply To:</strong> " . htmlspecialchars(REPLY_TO_EMAIL) . "</p>";
echo "</div>";

// Email test form
echo "<h3>Send Test Email</h3>";
echo "<form method='POST' style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0;'>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='to_email'><strong>To Email:</strong></label><br>";
echo "<input type='email' id='to_email' name='to_email' value='" . htmlspecialchars(FROM_EMAIL) . "' style='width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;' required>";
echo "</div>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='subject'><strong>Subject:</strong></label><br>";
echo "<input type='text' id='subject' name='subject' value='JJ Reporting Dashboard - Simple Email Test' style='width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;'>";
echo "</div>";
echo "<button type='submit' name='send_simple_test' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>Send Simple Test Email</button>";
echo "</form>";

// Handle email sending
if (isset($_POST['send_simple_test']) && !empty($_POST['to_email'])) {
    $to_email = $_POST['to_email'];
    $subject = $_POST['subject'] ?? 'JJ Reporting Dashboard - Simple Email Test';
    
    echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>🧪 Sending Simple Email Test...</h4>";
    
    try {
        // Validate email
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address: " . htmlspecialchars($to_email));
        }
        
        // Prepare email content
        $html_body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Email Test</title>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f8f9fa;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #007bff; margin: 0; font-size: 28px;'>✅ Email Test Successful!</h1>
                        <p style='color: #666; margin: 10px 0 0 0;'>JJ Reporting Dashboard</p>
                    </div>
                    
                    <div style='background: #e7f3ff; padding: 20px; border-radius: 5px; margin-bottom: 25px;'>
                        <h3 style='margin-top: 0; color: #0056b3;'>Test Information</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'><strong>Sent At:</strong></td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'>" . date('Y-m-d H:i:s') . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'><strong>From:</strong></td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'>" . htmlspecialchars(FROM_NAME) . " &lt;" . htmlspecialchars(FROM_EMAIL) . "&gt;</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'><strong>To:</strong></td>
                                <td style='padding: 8px 0; border-bottom: 1px solid #d1ecf1;'>" . htmlspecialchars($to_email) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0;'><strong>Method:</strong></td>
                                <td style='padding: 8px 0;'>PHP mail() function</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='margin-bottom: 25px;'>
                        <h3 style='color: #333;'>What This Means</h3>
                        <p>If you received this email, it means:</p>
                        <ul>
                            <li>✅ Your server's PHP mail() function is working</li>
                            <li>✅ Email headers are configured correctly</li>
                            <li>✅ HTML email formatting is working</li>
                            <li>✅ Your email configuration is valid</li>
                        </ul>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;'>
                        <p style='margin: 0; color: #666; font-size: 14px;'>
                            This is an automated test email from the JJ Reporting Dashboard.<br>
                            Generated on " . date('F j, Y \a\t g:i A') . "
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $text_body = "Email Test Successful!\n\nThis is a test email from the JJ Reporting Dashboard.\n\nSent at: " . date('Y-m-d H:i:s') . "\nFrom: " . FROM_NAME . " <" . FROM_EMAIL . ">\nTo: " . $to_email . "\n\nIf you received this email, your email configuration is working correctly!";
        
        // Prepare headers
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";
        $headers[] = "Reply-To: " . REPLY_TO_EMAIL;
        $headers[] = "X-Mailer: JJ Reporting Dashboard";
        $headers[] = "X-Priority: 3";
        
        // Send email
        $success = mail($to_email, $subject, $html_body, implode("\r\n", $headers));
        
        if ($success) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>✅ Email Sent Successfully!</h4>";
            echo "<p><strong>Method:</strong> PHP mail() function</p>";
            echo "<p><strong>Recipient:</strong> " . htmlspecialchars($to_email) . "</p>";
            echo "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
            echo "<p><strong>Sent At:</strong> " . date('Y-m-d H:i:s') . "</p>";
            echo "<p><strong>Note:</strong> Check your inbox (and spam folder) for the test email.</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>❌ Email Failed to Send</h4>";
            echo "<p>The PHP mail() function returned false. This could be due to:</p>";
            echo "<ul>";
            echo "<li>Server mail configuration issues</li>";
            echo "<li>SMTP server not configured on the server</li>";
            echo "<li>Firewall blocking outbound email</li>";
            echo "<li>Invalid email headers</li>";
            echo "</ul>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>❌ Error Sending Email</h4>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='test_email.php'>← Back to Full Email Test</a> | <a href='dashboard.php'>← Back to Dashboard</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h2, h3, h4 { color: #333; }
ul { line-height: 1.8; }
input, button { font-size: 14px; }
</style>
