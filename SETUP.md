# JJ Reporting Dashboard - Setup Instructions

## Prerequisites

1. **PHP 7.4+** with the following extensions:
   - PDO MySQL
   - cURL
   - JSON

2. **MySQL 5.7+** or **MariaDB 10.2+**

3. **Web Server** (Apache/Nginx)

4. **Meta Developer Account** and **Facebook App**

## Step 1: Meta App Setup

### 1.1 Create a Meta Developer Account
1. Go to [developers.facebook.com](https://developers.facebook.com)
2. Sign up or log in with your Facebook account
3. Complete the developer verification process

### 1.2 Create a Facebook App
1. Click "Create App" in the Meta Developer Console
2. Choose "Business" as the app type
3. Fill in the required information:
   - **App Name**: JJ Reporting Dashboard
   - **App Contact Email**: Your email
   - **Business Account**: Select or create one

### 1.3 Configure App Settings
1. Go to **App Settings** > **Basic**
2. Note down your **App ID** and **App Secret**
3. Add your domain to **App Domains**
4. Add your redirect URI to **Valid OAuth Redirect URIs**:
   ```
   http://localhost/jj-reports-dashboard-2/oauth_callback.php
   ```

### 1.4 Add Products
1. Go to **Products** and add:
   - **Facebook Login**
   - **Marketing API**

### 1.5 Configure Facebook Login
1. Go to **Facebook Login** > **Settings**
2. Add your domain to **Valid OAuth Redirect URIs**
3. Configure the following scopes:
   - `public_profile` (default)
   - `email` (optional)
   - `ads_read` (for reading ad account data)
   - `business_management` (for accessing business accounts)

### 1.6 Request Marketing API Permissions
1. Go to **App Review** > **Permissions and Features**
2. Request access to:
   - `ads_read` - Read ad account data
   - `business_management` - Access business accounts
3. Submit for review (may take several days for approval)

**Note**: The app will attempt to fetch real ad accounts. If permissions are not approved, it will show a demo account instead.

## Step 2: Database Setup

### 2.1 Create Database
```sql
CREATE DATABASE `report-database`;
USE `report-database`;
```

### 2.2 Create Tables
```sql
-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    image VARCHAR(255),
    user_type ENUM('admin', 'user') DEFAULT 'admin',
    user_permissions TEXT,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Connected accounts table
CREATE TABLE IF NOT EXISTS connected_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    account_id VARCHAR(255) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_status VARCHAR(50),
    currency VARCHAR(10),
    timezone VARCHAR(100),
    access_token TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO admins (name, username, email, password, user_type) VALUES 
('super admin', 'super_admin', 'super_admin@admin.com', '$2y$10$52Fg/oWccZ5QV/01Kww13u6l4dlHj/R0iuYGp1HDw16SSKHFWwbSO', 'admin');
```

## Step 3: Application Configuration

### 3.1 Update Configuration
Edit `config.php` and replace the placeholder values:

```php
// Meta App Credentials
define('META_APP_ID', 'YOUR_ACTUAL_APP_ID');
define('META_APP_SECRET', 'YOUR_ACTUAL_APP_SECRET');

// Update redirect URI if needed
define('META_REDIRECT_URI', 'http://your-domain.com/jj-reports-dashboard-2/oauth_callback.php');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'report-database');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

### 3.2 Set File Permissions
```bash
chmod 755 assets/
chmod 644 assets/css/*
chmod 644 assets/js/*
chmod 644 *.php
```

## Step 4: Testing the Setup

### 4.1 Access the Application
1. Navigate to your web server URL
2. You should see the login page

### 4.2 Login
- **Username**: `super_admin`
- **Password**: `admin123`

### 4.3 Test OAuth Connection
1. Click "Connect Meta Account" button
2. You should be redirected to Facebook OAuth
3. After authorization, you'll see simulated ad accounts
4. Select accounts and connect them

**Current Implementation Status**: 
- ✅ OAuth flow works with Facebook Login
- ✅ Account selection interface is fully functional
- ✅ Database storage for selected accounts
- ⚠️ Uses simulated ad account data (for demonstration)
- 🔄 Real Marketing API integration requires additional setup

## Step 5: Production Deployment

### 5.1 Security Considerations
1. **Change default credentials**:
   ```sql
   UPDATE admins SET password = '$2y$10$NEW_HASH' WHERE username = 'super_admin';
   ```

2. **Set secure file permissions**:
   ```bash
   chmod 600 config.php
   chmod 644 *.php
   ```

3. **Enable HTTPS** in production

4. **Set DEBUG_MODE to false** in `config.php`

### 5.2 Environment Variables (Optional)
For better security, you can use environment variables:

```php
// In config.php
define('META_APP_ID', $_ENV['META_APP_ID'] ?? 'YOUR_APP_ID');
define('META_APP_SECRET', $_ENV['META_APP_SECRET'] ?? 'YOUR_APP_SECRET');
```

## Troubleshooting

### Common Issues

1. **OAuth Error: "Invalid redirect_uri"**
   - Check that your redirect URI in Meta App matches exactly
   - Ensure the URL is accessible

2. **Database Connection Failed**
   - Verify database credentials in `config.php`
   - Check if MySQL service is running
   - Ensure database exists

3. **"No ad accounts found"**
   - Verify your Facebook account has ad accounts
   - Check that your app has the correct permissions
   - Ensure the account is not restricted

4. **Charts not displaying**
   - Check browser console for JavaScript errors
   - Verify Chart.js is loading correctly
   - Check network connectivity

### Debug Mode
Enable debug mode in `config.php` to see detailed error messages:
```php
define('DEBUG_MODE', true);
```

## Support

For issues and questions:
1. Check the browser console for JavaScript errors
2. Check PHP error logs
3. Verify all configuration values
4. Test with a simple Facebook account first

## Next Steps

After successful setup:
1. Connect your Meta ad accounts (currently simulated)
2. Explore the dashboard features
3. Set up alerts and reports
4. Configure scheduled exports

## Real Marketing API Integration (Advanced)

To implement real Marketing API access instead of simulated data:

### 1. Request Marketing API Access
1. Go to your Meta App > **App Review** > **Permissions and Features**
2. Request access to:
   - `ads_read`
   - `ads_management` 
   - `business_management`
3. Submit for review (may take several days)

### 2. Implement Extended Permissions Flow
1. After basic OAuth, request additional permissions
2. Use `FB.login()` with extended permissions
3. Implement proper error handling for permission denials

### 3. Update OAuth Callback
1. Replace simulated data with real API calls
2. Handle rate limiting and API errors
3. Implement proper token refresh logic

### 4. Test with Real Accounts
1. Use a Facebook account with ad accounts
2. Test with different permission levels
3. Verify data accuracy and real-time updates

**Note**: The current implementation provides a complete, functional demo that can be easily upgraded to real API access once permissions are approved.
