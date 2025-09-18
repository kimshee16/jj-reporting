<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JJ Reporting Dashboard - Admin Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
                                <div class="login-header">
                        <div class="logo">
                            <i class="fas fa-chart-line"></i>
                            <span>JJ Reporting Dashboard</span>
                        </div>
                        <p>Admin Login</p>
                    </div>
            
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
            
                                <div class="login-footer">
                        <p>&copy; 2024 JJ Reporting Dashboard. All rights reserved.</p>
                    </div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>
