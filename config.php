<?php
// Meta API Configuration
// Replace these with your actual Meta App credentials

// Meta App Credentials
define('META_APP_ID', '1937296900380988');
define('META_APP_SECRET', 'c6de144182b4bae47636f8e0067aa9f3');

// OAuth Configuration
// define('META_REDIRECT_URI', 'http://localhost/jj-reports-dashboard-2/oauth_callback.php');
define('META_REDIRECT_URI', 'https://326e01eb5e77.ngrok-free.app/jj-reports-dashboard-2/oauth_callback.php');
define('META_SCOPE', 'public_profile,email,ads_read,business_management');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'report-database');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('APP_NAME', 'JJ Reporting Dashboard');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_LIFETIME', 1800); // 30 minutes

// Meta API URLs
define('META_OAUTH_URL', 'https://www.facebook.com/v18.0/dialog/oauth');
define('META_TOKEN_URL', 'https://graph.facebook.com/v18.0/oauth/access_token');
define('META_GRAPH_URL', 'https://graph.facebook.com/v18.0');

// Error Reporting (set to false in production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
