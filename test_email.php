<?php
/**
 * Email Test Script
 * Tests the email sending functionality with your configured settings
 */

require_once 'config.php';
require_once 'config/email_config.php';

// Check if PHPMailer is available
$phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');

echo "<h2>Email Configuration Test</h2>";

echo "<h3>Configuration Check:</h3>";
echo "<ul>";
echo "<li><strong>SMTP Enabled:</strong> " . (SMTP_ENABLED ? 'Yes' : 'No') . "</li>";
echo "<li><strong>SMTP Host:</strong> " . htmlspecialchars(SMTP_HOST) . "</li>";
echo "<li><strong>SMTP Port:</strong> " . SMTP_PORT . "</li>";
echo "<li><strong>SMTP Username:</strong> " . htmlspecialchars(SMTP_USERNAME) . "</li>";
echo "<li><strong>From Email:</strong> " . htmlspecialchars(FROM_EMAIL) . "</li>";
echo "<li><strong>From Name:</strong> " . htmlspecialchars(FROM_NAME) . "</li>";
echo "<li><strong>PHPMailer Available:</strong> " . ($phpmailer_available ? 'Yes' : 'No') . "</li>";
echo "<li><strong>Fallback to PHP mail():</strong> " . (FALLBACK_TO_MAIL ? 'Yes' : 'No') . "</li>";
echo "</ul>";

if (!$phpmailer_available && SMTP_ENABLED) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>⚠️ PHPMailer Not Installed</h4>";
    echo "<p>PHPMailer is required for SMTP functionality. You can either:</p>";
    echo "<ol>";
    echo "<li><strong>Install PHPMailer:</strong> Run <code>composer require phpmailer/phpmailer</code></li>";
    echo "<li><strong>Use PHP mail() fallback:</strong> Set SMTP_ENABLED to false in email_config.php</li>";
    echo "</ol>";
    echo "</div>";
}

// Email test form
echo "<h3>Send Test Email</h3>";
echo "<form method='POST' style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0;'>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='to_email'><strong>To Email:</strong></label><br>";
echo "<input type='email' id='to_email' name='to_email' value='" . htmlspecialchars(FROM_EMAIL) . "' style='width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;' required>";
echo "</div>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='subject'><strong>Subject:</strong></label><br>";
echo "<input type='text' id='subject' name='subject' value='JJ Reporting Dashboard - Email Test' style='width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 3px;'>";
echo "</div>";
echo "<div style='margin-bottom: 15px;'>";
echo "<label for='test_type'><strong>Test Type:</strong></label><br>";
echo "<select id='test_type' name='test_type' style='padding: 8px; border: 1px solid #ddd; border-radius: 3px;'>";
echo "<option value='simple'>Simple HTML Email</option>";
echo "<option value='template'>Report Template Email</option>";
echo "<option value='attachment'>Email with CSV Attachment</option>";
echo "</select>";
echo "</div>";
echo "<button type='submit' name='send_test' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>Send Test Email</button>";
echo "</form>";

// Handle email sending
if (isset($_POST['send_test']) && !empty($_POST['to_email'])) {
    $to_email = $_POST['to_email'];
    $subject = $_POST['subject'] ?? 'JJ Reporting Dashboard - Email Test';
    $test_type = $_POST['test_type'] ?? 'simple';
    
    echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>🧪 Testing Email Sending...</h4>";
    
    try {
        require_once __DIR__ . '/classes/EmailService.php';
        $emailService = new EmailService();
        
        // Validate email
        if (!$emailService->validateEmail($to_email)) {
            throw new Exception("Invalid email address: " . htmlspecialchars($to_email));
        }
        
        $success = false;
        $method_used = '';
        
        switch ($test_type) {
            case 'simple':
                $html_body = "
                    <html>
                    <head><title>Email Test</title></head>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #007bff;'>✅ Email Test Successful!</h2>
                            <p>This is a test email from the <strong>JJ Reporting Dashboard</strong>.</p>
                            <p><strong>Test Details:</strong></p>
                            <ul>
                                <li><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</li>
                                <li><strong>From:</strong> " . htmlspecialchars(FROM_NAME) . " &lt;" . htmlspecialchars(FROM_EMAIL) . "&gt;</li>
                                <li><strong>To:</strong> " . htmlspecialchars($to_email) . "</li>
                                <li><strong>Method:</strong> " . (SMTP_ENABLED && $phpmailer_available ? 'SMTP' : 'PHP mail()') . "</li>
                            </ul>
                            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='color: #666; font-size: 14px;'>This is an automated test email. If you received this, your email configuration is working correctly!</p>
                        </div>
                    </body>
                    </html>
                ";
                
                $text_body = "Email Test Successful!\n\nThis is a test email from the JJ Reporting Dashboard.\n\nSent at: " . date('Y-m-d H:i:s') . "\nFrom: " . FROM_NAME . " <" . FROM_EMAIL . ">\nTo: " . $to_email;
                
                $success = $emailService->sendEmail($to_email, $subject, $html_body, $text_body);
                $method_used = SMTP_ENABLED && $phpmailer_available ? 'SMTP' : 'PHP mail()';
                break;
                
            case 'template':
                // Create sample report data
                $report_data = [
                    'name' => 'Campaign Performance Report',
                    'description' => 'Weekly performance summary',
                    'generated_at' => date('Y-m-d H:i:s'),
                    'total_spend' => '$2,450.75',
                    'total_conversions' => 156,
                    'avg_roas' => '3.2x'
                ];
                
                $schedule_data = [
                    'frequency' => 'weekly',
                    'next_run' => date('Y-m-d H:i:s', strtotime('+1 week'))
                ];
                
                $success = $emailService->sendReportEmail($report_data, $to_email, $schedule_data);
                $method_used = 'Report Template';
                break;
                
            case 'attachment':
                // Create a sample CSV file
                $csv_content = "Campaign Name,Spend,Conversions,ROAS\n";
                $csv_content .= "Summer Sale Campaign,1250.50,45,2.85\n";
                $csv_content .= "Holiday Campaign,2100.75,120,1.92\n";
                $csv_content .= "Brand Awareness,650.25,0,0.00\n";
                
                $csv_file = tempnam(sys_get_temp_dir(), 'test_report_') . '.csv';
                file_put_contents($csv_file, $csv_content);
                
                $report_data = [
                    'name' => 'Campaign Performance Export',
                    'description' => 'CSV export of campaign data'
                ];
                
                $success = $emailService->sendReportWithAttachment($report_data, $to_email, $csv_file);
                $method_used = 'Email with Attachment';
                
                // Clean up temp file
                unlink($csv_file);
                break;
        }
        
        if ($success) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>✅ Email Sent Successfully!</h4>";
            echo "<p><strong>Method Used:</strong> " . htmlspecialchars($method_used) . "</p>";
            echo "<p><strong>Recipient:</strong> " . htmlspecialchars($to_email) . "</p>";
            echo "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
            echo "<p><strong>Sent At:</strong> " . date('Y-m-d H:i:s') . "</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>❌ Email Failed to Send</h4>";
            echo "<p>Please check your email configuration and try again.</p>";
            echo "<p>Check the error logs for more details.</p>";
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

// SMTP Connection Test (if PHPMailer is available)
if ($phpmailer_available && SMTP_ENABLED) {
    echo "<h3>SMTP Connection Test</h3>";
    echo "<button onclick='testSMTP()' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>Test SMTP Connection</button>";
    echo "<div id='smtp-result' style='margin-top: 15px;'></div>";
    
    echo "<script>
    function testSMTP() {
        document.getElementById('smtp-result').innerHTML = '<div style=\"background: #fff3cd; padding: 15px; border-radius: 5px;\"><h4>🔄 Testing SMTP Connection...</h4><p>This may take a few seconds...</p></div>';
        
        fetch('test_smtp_connection.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('smtp-result').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('smtp-result').innerHTML = '<div style=\"background: #f8d7da; padding: 15px; border-radius: 5px;\"><h4>❌ Connection Test Failed</h4><p>Error: ' + error + '</p></div>';
            });
    }
    </script>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h2, h3, h4 { color: #333; }
ul { line-height: 1.8; }
code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
input, select, button { font-size: 14px; }
</style>

