<?php
// Email Configuration
// This file contains email settings for the JJ Reporting Dashboard

// SMTP Configuration (recommended for production)
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'ssl://smtp.gmail.com'); // Change to your SMTP server
define('SMTP_PORT', 465);
define('SMTP_USERNAME', '<user_email>'); // Change to your email
define('SMTP_PASSWORD', '<app_password>'); // Change to your app password
define('SMTP_ENCRYPTION', 'ssl'); // or 'ssl'

// Fallback to PHP mail() if SMTP fails
define('FALLBACK_TO_MAIL', true);

// Email Settings
define('FROM_EMAIL', '<from_email>');
define('FROM_NAME', '<from_name>');
define('REPLY_TO_EMAIL', '<to_email>');

// Email Templates
define('EMAIL_TEMPLATE_PATH', __DIR__ . '/../templates/email/');

// Rate Limiting
define('MAX_EMAILS_PER_HOUR', 100);
define('MAX_EMAILS_PER_DAY', 1000);

// Email Queue (for high volume)
define('USE_EMAIL_QUEUE', false);
define('EMAIL_QUEUE_TABLE', 'email_queue');
?>
