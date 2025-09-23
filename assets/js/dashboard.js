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
    
    let performanceChart = null;
    let platformChart = null;

    function initializeCharts() {
        // Load chart data from API
        loadChartData();
    }
    
    async function loadChartData(period = 30) {
        try {
            const response = await fetch(`dashboard_charts_api.php?period=${period}`);
            const result = await response.json();
            
            if (result.success) {
                initializePerformanceChart(result.data.performance);
                initializePlatformChart(result.data.platforms);
                
                // Update last updated timestamp
                updateLastUpdatedTime();
            } else {
                console.error('Failed to load chart data:', result.error);
                // Fallback to static data
                initializePerformanceChart();
                initializePlatformChart();
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
            // Fallback to static data
            initializePerformanceChart();
            initializePlatformChart();
        }
    }
    
    function updateLastUpdatedTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        
        // Add or update last updated indicator
        let lastUpdatedElement = document.getElementById('lastUpdated');
        if (!lastUpdatedElement) {
            lastUpdatedElement = document.createElement('div');
            lastUpdatedElement.id = 'lastUpdated';
            lastUpdatedElement.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 12px;
                color: #666;
                background: rgba(255, 255, 255, 0.8);
                padding: 5px 10px;
                border-radius: 4px;
                z-index: 5;
            `;
            
            const chartsSection = document.querySelector('.charts-section');
            if (chartsSection) {
                chartsSection.style.position = 'relative';
                chartsSection.appendChild(lastUpdatedElement);
            }
        }
        
        lastUpdatedElement.innerHTML = `<i class="fas fa-clock"></i> Last updated: ${timeString}`;
    }
    
    function initializePerformanceChart(performanceData = null) {
        const performanceCtx = document.getElementById('performanceChart');
        if (!performanceCtx) return;
        
        // Destroy existing chart if it exists
        if (performanceChart) {
            performanceChart.destroy();
        }
        
        let labels, spendData, conversionsData;
        
        if (performanceData && performanceData.length > 0) {
            // Use real data
            labels = performanceData.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            spendData = performanceData.map(item => parseFloat(item.daily_spend) || 0);
            conversionsData = performanceData.map(item => parseFloat(item.daily_conversions) || 0);
        } else {
            // No data: show empty chart with message
            labels = [];
            spendData = [];
            conversionsData = [];
        }

        performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ad Spend',
                    data: spendData,
                    borderColor: '#1877f2',
                    backgroundColor: 'rgba(24, 119, 242, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Conversions',
                    data: conversionsData,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        enabled: true
                    },
                    title: {
                        display: labels.length === 0,
                        text: labels.length === 0 ? 'No data available' : ''
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Ad Spend ($)'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Conversions'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
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
    
    function initializePlatformChart(platformData = null) {
        const platformCtx = document.getElementById('platformChart');
        if (!platformCtx) {
            console.error('Platform chart canvas not found!');
            return;
        }
        
        // Destroy existing chart if it exists
        if (platformChart) {
            platformChart.destroy();
        }
        
        let labels, data;
        
        if (platformData && platformData.length > 0) {
            // Use real data
            labels = platformData.map(item => item.platform);
            data = platformData.map(item => parseFloat(item.platform_spend) || 0);
        } else {
            // Use sample data
            const sampleData = { 'Facebook': 65, 'Instagram': 25, 'Audience Network': 10 };
            labels = Object.keys(sampleData);
            data = Object.values(sampleData);
        }
        
        platformChart = new Chart(platformCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: $${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
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
    }
    
    async function loadDashboardData() {
        // Fetch dynamic metrics for Performance Overview
        try {
            const response = await fetch('dashboard_api.php');
            const result = await response.json();
            if (result.success && result.data) {
                updateDashboardMetrics(result.data);
            }
        } catch (error) {
            console.error('Error loading dashboard metrics:', error);
        }
        // Initialize charts with any dynamic data
        updateCharts();
        // Set up auto-refresh for charts every 5 minutes
        setInterval(() => {
            const currentPeriod = document.getElementById('chartPeriod')?.value || 30;
            loadChartData(currentPeriod);
        }, 300000); // 5 minutes
    }
    
    function updateDashboardMetrics(data) {
        // Update metric values with real data from API
        if (!data) return;
        if (data.totalSpend !== undefined) {
            document.getElementById('totalSpend').textContent = data.totalSpend.toLocaleString();
        }
        if (data.totalConversions !== undefined) {
            document.getElementById('totalConversions').textContent = data.totalConversions.toLocaleString();
        }
        if (data.avgROAS !== undefined) {
            document.getElementById('avgROAS').textContent = data.avgROAS;
        }
        if (data.totalImpressions !== undefined) {
            document.getElementById('totalImpressions').textContent = data.totalImpressions.toLocaleString();
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
    

    
    async function refreshDashboardData() {
        const refreshBtn = document.getElementById('refreshDataBtn');
        const originalText = refreshBtn.innerHTML;
        const currentPeriod = document.getElementById('chartPeriod')?.value || 30;
        
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        try {
            // Refresh dashboard data and charts
            await loadDashboardData();
            await loadChartData(currentPeriod);
            
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            
            // Show success message
            showNotification('Data refreshed successfully!', 'success');
        } catch (error) {
            console.error('Error refreshing data:', error);
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
            showNotification('Failed to refresh data', 'error');
        }
    }
    
    function updateChartPeriod(period) {
        // Update chart data based on period
        console.log(`Updating chart for ${period} days`);
        
        // Show loading indicator
        const chartContainer = document.querySelector('.charts-section');
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'chart-loading';
        loadingOverlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading chart data...';
        loadingOverlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            font-size: 16px;
            color: #666;
        `;
        
        chartContainer.style.position = 'relative';
        chartContainer.appendChild(loadingOverlay);
        
        // Load new chart data
        loadChartData(period).then(() => {
            // Remove loading indicator
            chartContainer.removeChild(loadingOverlay);
            showNotification(`Chart updated for last ${period} days`, 'success');
        }).catch((error) => {
            // Remove loading indicator
            chartContainer.removeChild(loadingOverlay);
            console.error('Error updating chart:', error);
            showNotification('Failed to update chart data', 'error');
        });
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
