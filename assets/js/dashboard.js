document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard
    initializeDashboard();
    initializeCharts();
    loadDashboardData();
    setupEventListeners();
    
    function initializeDashboard() {
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
    
    function initializeCharts() {
        // Performance Overview Chart
        const performanceCtx = document.getElementById('performanceChart');
        if (performanceCtx) {
            const performanceChart = new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['Jan 1', 'Jan 8', 'Jan 15', 'Jan 22', 'Jan 29'],
                    datasets: [{
                        label: 'Ad Spend',
                        data: [1200, 1900, 3000, 5000, 2000],
                        borderColor: '#1877f2',
                        backgroundColor: 'rgba(24, 119, 242, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Conversions',
                        data: [450, 520, 780, 1200, 850],
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    },
                    elements: {
                        line: {
                            borderWidth: 3
                        },
                        point: {
                            radius: 4,
                            hoverRadius: 6
                        }
                    }
                }
            });
        }
        
        // Platform Chart
        const platformCtx = document.getElementById('platformChart');
        if (platformCtx) {
            console.log('Initializing platform chart...');
            const platformChart = new Chart(platformCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Facebook', 'Instagram', 'Audience Network'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: [
                            '#1877f2',
                            '#e4405f',
                            '#f39c12'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    },
                    layout: {
                        padding: 20
                    }
                }
            });
            
            // Force chart resize after initialization
            setTimeout(() => {
                platformChart.resize();
            }, 100);
        } else {
            console.error('Platform chart canvas not found!');
        }
    }
    
    function loadDashboardData() {
        // Dashboard data is now loaded directly in PHP
        console.log('Dashboard data loaded via PHP');
        
        // Initialize charts with any dynamic data
        // The PHP already handles displaying the metrics
        updateCharts();
    }
    
    function updateDashboardMetrics(data) {
        // Update metric values with real data
        if (data.summary) {
            // Update the metric values that are displayed in PHP
            // The PHP already handles the display, this is for any JS-only updates
            console.log('Dashboard metrics updated with real data');
        }
    }
    
    function updateCharts() {
        // Charts are initialized with static data for now
        // In the future, we can add dynamic chart updates here
        console.log('Charts initialized');
    }
    
    function updateMetrics() {
        // Simulate API data
        const metrics = {
            totalSpend: '45,230',
            totalConversions: '1,247',
            avgROAS: '3.2',
            totalImpressions: '2.8'
        };
        
        document.getElementById('totalSpend').textContent = metrics.totalSpend;
        document.getElementById('totalConversions').textContent = metrics.totalConversions;
        document.getElementById('avgROAS').textContent = metrics.avgROAS;
        document.getElementById('totalImpressions').textContent = metrics.totalImpressions;
    }
    
    function loadTopCampaigns() {
        const topCampaigns = [
            { name: 'Summer Sale 2024', roas: '4.8x', spend: '$8,450', conversions: '156' },
            { name: 'Brand Awareness Q1', roas: '3.2x', spend: '$5,200', conversions: '89' },
            { name: 'Product Launch', roas: '2.9x', spend: '$12,300', conversions: '234' }
        ];
        
        const container = document.getElementById('topCampaigns');
        container.innerHTML = topCampaigns.map(campaign => `
            <div class="performance-item">
                <div class="performance-info">
                    <h4>${campaign.name}</h4>
                    <p>ROAS: ${campaign.roas}</p>
                </div>
                <div class="performance-stats">
                    <div class="stat-value">${campaign.spend}</div>
                    <div class="stat-label">${campaign.conversions} conversions</div>
                </div>
            </div>
        `).join('');
    }
    
    function loadBottomCampaigns() {
        const bottomCampaigns = [
            { name: 'Test Campaign A', roas: '0.8x', spend: '$2,100', conversions: '12' },
            { name: 'Old Product Line', roas: '1.1x', spend: '$3,400', conversions: '28' },
            { name: 'Generic Awareness', roas: '1.3x', spend: '$1,800', conversions: '15' }
        ];
        
        const container = document.getElementById('bottomCampaigns');
        container.innerHTML = bottomCampaigns.map(campaign => `
            <div class="performance-item">
                <div class="performance-info">
                    <h4>${campaign.name}</h4>
                    <p>ROAS: ${campaign.roas}</p>
                </div>
                <div class="performance-stats">
                    <div class="stat-value">${campaign.spend}</div>
                    <div class="stat-label">${campaign.conversions} conversions</div>
                </div>
            </div>
        `).join('');
    }
    
    function loadRecentAlerts() {
        const alerts = [
            { type: 'warning', title: 'High CPA Alert', message: 'Campaign "Test Campaign A" CPA exceeded $50 threshold', time: '2 hours ago' },
            { type: 'danger', title: 'ROAS Drop Alert', message: 'Campaign "Old Product Line" ROAS dropped below 1.0', time: '5 hours ago' },
            { type: 'info', title: 'Budget Limit Reached', message: 'Campaign "Brand Awareness Q1" reached 80% of daily budget', time: '1 day ago' }
        ];
        
        const container = document.getElementById('recentAlerts');
        container.innerHTML = alerts.map(alert => `
            <div class="alert-item">
                <div class="alert-icon ${alert.type}">
                    <i class="fas fa-${getAlertIcon(alert.type)}"></i>
                </div>
                <div class="alert-content">
                    <h4>${alert.title}</h4>
                    <p>${alert.message}</p>
                    <span class="alert-time">${alert.time}</span>
                </div>
            </div>
        `).join('');
    }
    
    function getAlertIcon(type) {
        const icons = {
            warning: 'exclamation-triangle',
            danger: 'times-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    function setupEventListeners() {
        // Connect Account Button
        const connectAccountBtn = document.getElementById('connectAccountBtn');
        const connectAccountModal = document.getElementById('connectAccountModal');
        
        if (connectAccountBtn && connectAccountModal) {
            connectAccountBtn.addEventListener('click', function() {
                connectAccountModal.style.display = 'block';
            });
            
            // Close modal
            const closeBtn = connectAccountModal.querySelector('.close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    connectAccountModal.style.display = 'none';
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === connectAccountModal) {
                    connectAccountModal.style.display = 'none';
                }
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
                refreshDashboardData();
            });
        }
        
        // Chart Period Selector
        const chartPeriod = document.getElementById('chartPeriod');
        if (chartPeriod) {
            chartPeriod.addEventListener('change', function() {
                updateChartPeriod(this.value);
            });
        }
        
        // Search functionality
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                performSearch(this.value);
            });
        }
    }
    
    function initiateMetaOAuth() {
        // Redirect to OAuth handler
        window.location.href = 'oauth.php?action=connect';
    }
    

    
    function refreshDashboardData() {
        const refreshBtn = document.getElementById('refreshDataBtn');
        const originalText = refreshBtn.innerHTML;
        
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        setTimeout(() => {
            loadDashboardData();
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            
            // Show success message
            showNotification('Data refreshed successfully!', 'success');
        }, 1500);
    }
    
    function updateChartPeriod(period) {
        // Update chart data based on period
        console.log(`Updating chart for ${period} days`);
        // Here you would typically make an API call to get new data
        showNotification(`Chart updated for last ${period} days`, 'info');
    }
    
    function performSearch(query) {
        if (query.length < 2) return;
        
        console.log(`Searching for: ${query}`);
        // Here you would typically make an API call to search
        // For now, just log the search query
    }
    
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
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
    }
});
