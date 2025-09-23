<?php
/**
 * EmailService Class
 * Handles email sending with SMTP and fallback to PHP mail()
 * Supports HTML templates and attachments
 */


// Ensure Composer autoload is loaded for PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once 'config.php';
require_once 'config/email_config.php';

class EmailService {
    private $smtp_enabled;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_encryption;
    private $from_email;
    private $from_name;
    private $reply_to_email;
    private $fallback_to_mail;
    
    public function __construct() {
        $this->smtp_enabled = SMTP_ENABLED;
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->smtp_encryption = SMTP_ENCRYPTION;
        $this->from_email = FROM_EMAIL;
        $this->from_name = FROM_NAME;
        $this->reply_to_email = REPLY_TO_EMAIL;
        $this->fallback_to_mail = FALLBACK_TO_MAIL;
    }
    
    /**
     * Send email with SMTP or fallback to PHP mail()
     */
    public function sendEmail($to, $subject, $html_body, $text_body = null, $attachments = []) {
        try {
            if ($this->smtp_enabled) {
                return $this->sendViaSMTP($to, $subject, $html_body, $text_body, $attachments);
            } else {
                return $this->sendViaMail($to, $subject, $html_body, $text_body, $attachments);
            }
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            
            // Try fallback if SMTP failed
            if ($this->smtp_enabled && $this->fallback_to_mail) {
                try {
                    return $this->sendViaMail($to, $subject, $html_body, $text_body, $attachments);
                } catch (Exception $fallback_e) {
                    error_log("Email fallback also failed: " . $fallback_e->getMessage());
                    return false;
                }
            }
            return false;
        }
    }
    
    /**
     * Send email via SMTP using PHPMailer
     */
    private function sendViaSMTP($to, $subject, $html_body, $text_body = null, $attachments = []) {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new Exception("PHPMailer not found. Please install via Composer: composer require phpmailer/phpmailer");
        }
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->smtp_encryption;
            $mail->Port = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addReplyTo($this->reply_to_email, $this->from_name);
            
            // Handle multiple recipients
            if (is_array($to)) {
                foreach ($to as $email) {
                    $mail->addAddress($email);
                }
            } else {
                $mail->addAddress($to);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_body;
            
            if ($text_body) {
                $mail->AltBody = $text_body;
            }
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
                }
            }
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            throw new Exception("SMTP Error: " . $mail->ErrorInfo);
        }
    }
    
    /**
     * Send email via PHP mail() function
     */
    private function sendViaMail($to, $subject, $html_body, $text_body = null, $attachments = []) {
        $boundary = md5(uniqid(time()));
        $headers = [];
        
        // Basic headers
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->reply_to_email}";
        $headers[] = "X-Mailer: JJ Reporting Dashboard";
        
        if (empty($attachments)) {
            // Simple HTML email
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $message = $html_body;
        } else {
            // Multipart email with attachments
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $html_body . "\r\n";
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $file_content = file_get_contents($attachment['path']);
                    $file_name = $attachment['name'] ?? basename($attachment['path']);
                    $file_type = mime_content_type($attachment['path']);
                    
                    $message .= "--{$boundary}\r\n";
                    $message .= "Content-Type: {$file_type}; name=\"{$file_name}\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"{$file_name}\"\r\n\r\n";
                    $message .= chunk_split(base64_encode($file_content)) . "\r\n";
                }
            }
            
            $message .= "--{$boundary}--\r\n";
        }
        
        $to_string = is_array($to) ? implode(', ', $to) : $to;
        
        return mail($to_string, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Load and render email template
     */
    public function renderTemplate($template_name, $data = []) {
        $template_path = EMAIL_TEMPLATE_PATH . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            throw new Exception("Email template not found: {$template_name}");
        }
        
        // Extract variables for template
        extract($data);
        
        // Start output buffering
        ob_start();
        include $template_path;
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Send report email with template
     */
    public function sendReportEmail($report_data, $recipients, $schedule_data = null) {
        $subject = "Report: " . $report_data['name'];
        
        if ($schedule_data) {
            $subject .= " - " . ucfirst($schedule_data['frequency']) . " Report";
        }
        
        // Prepare template data
        $template_data = [
            'report' => $report_data,
            'schedule' => $schedule_data,
            'generated_at' => date('Y-m-d H:i:s'),
            'dashboard_url' => 'https://your-domain.com/dashboard.php'
        ];
        
        try {
            $html_body = $this->renderTemplate('report_email', $template_data);
            $text_body = $this->renderTemplate('report_email_text', $template_data);
            
            return $this->sendEmail($recipients, $subject, $html_body, $text_body);
            
        } catch (Exception $e) {
            error_log("Failed to send report email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send report with CSV attachment
     */
    public function sendReportWithAttachment($report_data, $recipients, $csv_file_path, $schedule_data = null) {
        $subject = "Report: " . $report_data['name'] . " (CSV Export)";
        
        if ($schedule_data) {
            $subject .= " - " . ucfirst($schedule_data['frequency']) . " Report";
        }
        
        $template_data = [
            'report' => $report_data,
            'schedule' => $schedule_data,
            'generated_at' => date('Y-m-d H:i:s'),
            'has_attachment' => true,
            'attachment_name' => basename($csv_file_path)
        ];
        
        try {
            $html_body = $this->renderTemplate('report_email', $template_data);
            $text_body = $this->renderTemplate('report_email_text', $template_data);
            
            $attachments = [[
                'path' => $csv_file_path,
                'name' => basename($csv_file_path)
            ]];
            
            return $this->sendEmail($recipients, $subject, $html_body, $text_body, $attachments);
            
        } catch (Exception $e) {
            error_log("Failed to send report email with attachment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate email address
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate multiple email addresses
     */
    public function validateEmails($emails) {
        if (is_string($emails)) {
            $emails = json_decode($emails, true);
        }
        
        if (!is_array($emails)) {
            return false;
        }
        
        foreach ($emails as $email) {
            if (!$this->validateEmail($email)) {
                return false;
            }
        }
        
        return true;
    }
}
