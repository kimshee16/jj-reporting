<?php
// Email Configuration
// This file contains email settings for the JJ Reporting Dashboard

// SMTP Configuration (recommended for production)
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Change to your email
define('SMTP_PASSWORD', 'your-app-password'); // Change to your app password
define('SMTP_ENCRYPTION', 'tls'); // or 'ssl'

// Fallback to PHP mail() if SMTP fails
define('FALLBACK_TO_MAIL', true);

// Email Settings
define('FROM_EMAIL', 'noreply@yourdomain.com');
define('FROM_NAME', 'JJ Reporting Dashboard');
define('REPLY_TO_EMAIL', 'support@yourdomain.com');

// Email Templates
define('EMAIL_TEMPLATE_PATH', __DIR__ . '/../templates/email/');

// Rate Limiting
define('MAX_EMAILS_PER_HOUR', 100);
define('MAX_EMAILS_PER_DAY', 1000);

// Email Queue (for high volume)
define('USE_EMAIL_QUEUE', false);
define('EMAIL_QUEUE_TABLE', 'email_queue');
?>
