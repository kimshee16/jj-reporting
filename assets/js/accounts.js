document.addEventListener('DOMContentLoaded', function() {
    // Initialize accounts page
    initializeAccounts();
    
    // Add a small delay to ensure DOM is fully ready
    setTimeout(() => {
        loadAccountsData();
    }, 100);
    
    setupEventListeners();
    
    function initializeAccounts() {
        // Set up sidebar toggle for mobile
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
    
    
    
    
    function setupEventListeners() {
        // Connect Account Button
        const connectAccountBtn = document.getElementById('connectAccountBtn');
        
        if (connectAccountBtn) {
            connectAccountBtn.addEventListener('click', function() {
                window.location.href = 'oauth.php?action=connect';
            });
        }
        
        // Meta OAuth Button
        const metaOAuthBtn = document.getElementById('metaOAuthBtn');
        if (metaOAuthBtn) {
            metaOAuthBtn.addEventListener('click', function() {
                initiateMetaOAuth();
            });
        }
        
        // Refresh Data Button
        const refreshDataBtn = document.getElementById('refreshDataBtn');
        if (refreshDataBtn) {
            refreshDataBtn.addEventListener('click', function() {
                refreshAccountsData();
            });
        }
        
        // Account Filter
        const accountFilter = document.getElementById('accountFilter');
        if (accountFilter) {
            accountFilter.addEventListener('change', function() {
                filterAccounts(this.value);
            });
        }
        
        // Export Accounts Button
        const exportAccountsBtn = document.getElementById('exportAccountsBtn');
        if (exportAccountsBtn) {
            exportAccountsBtn.addEventListener('click', function() {
                exportAccountsData();
            });
        }
        
        // Search functionality
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                searchAccounts(this.value);
            });
        }
    }
    
    function initiateMetaOAuth() {
        // Simulate OAuth process
        const oauthBtn = document.getElementById('metaOAuthBtn');
        const originalText = oauthBtn.innerHTML;
        
        oauthBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
        oauthBtn.disabled = true;
        
        // Simulate OAuth flow
        setTimeout(() => {
            // Show account selection
            document.getElementById('accountSelection').style.display = 'block';
            
            // Simulate available accounts
            const availableAccounts = [
                { id: 'act_new_001', name: 'New Business Account', currency: 'USD', timezone: 'America/Chicago' },
                { id: 'act_new_002', name: 'Marketing Account', currency: 'USD', timezone: 'America/Denver' }
            ];
            
            const accountsContainer = document.getElementById('availableAccounts');
            accountsContainer.innerHTML = availableAccounts.map(account => `
                <div class="account-item">
                    <input type="checkbox" id="account_${account.id}" value="${account.id}">
                    <div class="account-info">
                        <h5>${account.name}</h5>
                        <p>ID: ${account.id} | ${account.currency} | ${account.timezone}</p>
                    </div>
                </div>
            `).join('');
            
            oauthBtn.innerHTML = '<i class="fas fa-check"></i> Connected Successfully';
            oauthBtn.style.background = '#27ae60';
            
            // Add connect accounts button
            const connectBtn = document.createElement('button');
            connectBtn.className = 'btn btn-primary';
            connectBtn.innerHTML = '<i class="fas fa-link"></i> Connect Selected Accounts';
            connectBtn.style.marginTop = '20px';
            connectBtn.addEventListener('click', function() {
                connectSelectedAccounts();
            });
            
            document.getElementById('accountSelection').appendChild(connectBtn);
        }, 2000);
    }
    
    function connectSelectedAccounts() {
        const selectedAccounts = document.querySelectorAll('#accountSelection input[type="checkbox"]:checked');
        
        if (selectedAccounts.length === 0) {
            alert('Please select at least one account to connect.');
            return;
        }
        
        // Simulate connecting accounts
        const connectBtn = document.querySelector('#accountSelection .btn');
        const originalText = connectBtn.innerHTML;
        
        connectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';
        connectBtn.disabled = true;
        
        setTimeout(() => {
            alert(`Successfully connected ${selectedAccounts.length} account(s)!`);
            document.getElementById('connectAccountModal').style.display = 'none';
            
            // Reset modal state
            document.getElementById('accountSelection').style.display = 'none';
            document.getElementById('metaOAuthBtn').innerHTML = '<i class="fab fa-facebook"></i> Connect with Facebook';
            document.getElementById('metaOAuthBtn').disabled = false;
            document.getElementById('metaOAuthBtn').style.background = '';
            
            // Refresh accounts data
            loadAccountsData();
            showNotification('New accounts connected successfully!', 'success');
        }, 1500);
    }
    
    function refreshAccountsData() {
        const refreshBtn = document.getElementById('refreshDataBtn');
        const originalText = refreshBtn.innerHTML;
        
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        setTimeout(() => {
            loadAccountsData();
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            
            showNotification('Accounts data refreshed successfully!', 'success');
        }, 1500);
    }
    
    function filterAccounts(filter) {
        console.log(`Filtering accounts by: ${filter}`);
        // Here you would typically filter the accounts based on the selected filter
        // For now, just show a notification
        showNotification(`Filtered accounts by: ${filter}`, 'info');
    }
    
    function searchAccounts(query) {
        if (query.length < 2) return;
        
        console.log(`Searching accounts for: ${query}`);
        // Here you would typically search through accounts
        // For now, just log the search query
    }
    
    function exportAccountsData() {
        const exportBtn = document.getElementById('exportAccountsBtn');
        const originalText = exportBtn.innerHTML;
        
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        exportBtn.disabled = true;
        
        setTimeout(() => {
            // Simulate export
            const dataStr = "data:text/csv;charset=utf-8," + encodeURIComponent(
                "Account ID,Name,Status,Currency,Timezone,Spend This Month,Active Campaigns,Last Sync\n" +
                "act_123456789,Main Business Account,active,USD,America/New_York,18500,12,2024-01-29 14:30:00\n" +
                "act_987654321,Secondary Account,active,USD,America/Los_Angeles,16800,8,2024-01-29 12:15:00\n" +
                "act_456789123,Test Account,inactive,USD,UTC,9930,4,2024-01-28 09:45:00"
            );
            
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "ad_accounts_export.csv");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
            
            showNotification('Accounts data exported successfully!', 'success');
        }, 1500);
    }
    
});

// Global notification function
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 3000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    notification.style.background = colors[type] || colors.info;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Global function to load accounts data
function loadAccountsData() {
    // Show loading state
    const container = document.getElementById('accountsTableBody');
    
    if (!container) {
        console.error('accountsTableBody element not found');
        console.log('Available elements:', document.querySelectorAll('[id*="account"]'));
        return;
    }
    
    console.log('Loading accounts data...');
    container.innerHTML = '<tr><td colspan="3" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading accounts...</td></tr>';
    
    // Fetch real data from API
    fetch('accounts_api.php?action=get_accounts')
        .then(response => {
            console.log('Response received:', response);
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.success) {
                renderAccountsTable(data.accounts);
                updateOverviewStats(data.stats);
            } else {
                container.innerHTML = '<tr><td colspan="3" class="error-state"><i class="fas fa-exclamation-triangle"></i> Error loading accounts: ' + data.error + '</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading accounts:', error);
            container.innerHTML = '<tr><td colspan="3" class="error-state"><i class="fas fa-exclamation-triangle"></i> Failed to load accounts. Please try again.</td></tr>';
        });
}

// Global function to update overview stats
function updateOverviewStats(stats) {
    // Update stat cards if they exist
    const totalAccountsEl = document.querySelector('.stat-card:nth-child(1) .stat-value');
    const totalSpendEl = document.querySelector('.stat-card:nth-child(2) .stat-value');
    const totalCampaignsEl = document.querySelector('.stat-card:nth-child(3) .stat-value');
    const avgROASEl = document.querySelector('.stat-card:nth-child(4) .stat-value');
    
    if (totalAccountsEl) totalAccountsEl.textContent = stats.total_accounts;
    if (totalSpendEl) totalSpendEl.textContent = stats.total_spend;
    if (totalCampaignsEl) totalCampaignsEl.textContent = stats.active_campaigns;
    if (avgROASEl) avgROASEl.textContent = stats.avg_roas;
}

// Global function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));
    
    if (diffInHours < 1) {
        return 'Just now';
    } else if (diffInHours < 24) {
        return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
    } else {
        const diffInDays = Math.floor(diffInHours / 24);
        return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
    }
}

// Global function to render accounts table
function renderAccountsTable(accounts) {
    const container = document.getElementById('accountsTableBody');
    
    if (!container) {
        console.error('accountsTableBody element not found');
        return;
    }
    
    if (accounts.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="3" class="empty-state">
                    <i class="fas fa-building"></i>
                    <h3>No Ad Accounts Connected</h3>
                    <p>Connect your first Meta ad account to get started</p>
                    <button class="btn btn-primary" onclick="window.location.href='oauth.php?action=connect'">
                        <i class="fas fa-plus"></i> Connect Account
                    </button>
                </td>
            </tr>
        `;
        return;
    }
    console.log(accounts);
    container.innerHTML = accounts.map(account => `
        <tr data-account-id="${account.id}">
            <td>
                <div class="account-info">
                    <div class="account-name">${account.act_name || 'N/A'}</div>
                    <div class="account-id">ID: ${account.act_id || 'N/A'}</div>
                </div>
            </td>
            <td>
                <div class="last-sync">${formatDate(account.updated_at)}</div>
            </td>
            <td>
                <div class="table-actions">
                    <a href="#" class="action-link view-details" onclick="viewAccountDetails('${account.act_id}')">View Details</a>
                    <span class="action-separator">|</span>
                    <a href="#" class="action-link view-campaigns" onclick="viewCampaigns('${account.act_id}')">View Campaigns</a>
                    <span class="action-separator">|</span>
                    <a href="#" class="action-link sync-account" onclick="syncAccount('${account.act_id}')">Sync</a>
                </div>
            </td>
        </tr>
    `).join('');
}

// Global functions for account actions
function viewAccountDetails(accountId) {
    window.location.href = `account_details.php?id=${accountId}`;
}

function viewCampaigns(accountId) {
    window.location.href = `campaigns.php?account_id=${accountId}`;
}

function syncAccount(accountId) {
    console.log(`Syncing account: ${accountId}`);
    
    // Find the sync button for this account
    const syncButton = document.querySelector(`[onclick="syncAccount('${accountId}')"]`);
    if (syncButton) {
        const originalText = syncButton.innerHTML;
        syncButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
        syncButton.style.pointerEvents = 'none';
        syncButton.style.opacity = '0.6';
    }
    
    // Call the sync API
    fetch(`accounts_api.php?action=sync_account&account_id=${accountId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Account synced successfully!', 'success');
                // Refresh the accounts data to show updated information
                loadAccountsData();
            } else {
                console.log(data.error);
                showNotification(`Sync failed: ${data.error}`, 'error');
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            showNotification('Failed to sync account. Please try again.', 'error');
        })
        .finally(() => {
            // Reset button state
            if (syncButton) {
                syncButton.innerHTML = 'Sync';
                syncButton.style.pointerEvents = 'auto';
                syncButton.style.opacity = '1';
            }
        });
}

function disconnectAccount(accountId) {
    if (confirm('Are you sure you want to disconnect this account? This action cannot be undone.')) {
        console.log(`Disconnecting account: ${accountId}`);
        // Here you would disconnect the account
        showNotification(`Account ${accountId} disconnected successfully`, 'success');
        
        // Reload accounts data
        setTimeout(() => {
            loadAccountsData();
        }, 1000);
    }
}
