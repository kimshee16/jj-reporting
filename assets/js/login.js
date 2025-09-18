document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        // Show loading state
        const submitBtn = loginForm.querySelector('.login-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        submitBtn.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);
        
        // Make AJAX request to auth.php
        fetch('auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Successful login
                showSuccess('Login successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 1000);
            } else {
                // Failed login
                showError(data.message || 'Login failed');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('An error occurred during login. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    function showError(message) {
        // Remove existing messages
        const existingError = document.querySelector('.error-message');
        const existingSuccess = document.querySelector('.success-message');
        if (existingError) existingError.remove();
        if (existingSuccess) existingSuccess.remove();
        
        // Create and show new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = `
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
            border: 1px solid #fcc;
        `;
        errorDiv.textContent = message;
        
        loginForm.appendChild(errorDiv);
        
        // Auto-remove error after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
    
    function showSuccess(message) {
        // Remove existing messages
        const existingError = document.querySelector('.error-message');
        const existingSuccess = document.querySelector('.success-message');
        if (existingError) existingError.remove();
        if (existingSuccess) existingSuccess.remove();
        
        // Create and show new success message
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.style.cssText = `
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
            border: 1px solid #cfc;
        `;
        successDiv.textContent = message;
        
        loginForm.appendChild(successDiv);
    }
    
    // Add input focus effects
    const inputs = document.querySelectorAll('.form-group input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
    
    // Demo login for testing (remove in production)
    console.log('Demo login credentials: super_admin / admin123');
});
