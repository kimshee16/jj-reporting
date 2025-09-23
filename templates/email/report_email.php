<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report['name']); ?> - Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0 0;
        }
        .report-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .report-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-weight: bold;
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            color: #212529;
            font-size: 14px;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }
        .filters-section {
            margin: 30px 0;
        }
        .filters-section h3 {
            color: #495057;
            margin-bottom: 15px;
        }
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-tag {
            background-color: #e9ecef;
            color: #495057;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }
        .attachment-notice {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .attachment-notice h4 {
            margin: 0 0 10px 0;
            color: #0c5460;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        .schedule-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .schedule-info h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .summary-stats {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1><?php echo htmlspecialchars($report['name']); ?></h1>
            <p>Generated on <?php echo $generated_at; ?></p>
        </div>

        <div class="report-info">
            <h3>Report Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Report ID</div>
                    <div class="info-value">#<?php echo $report['id']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Total Records</div>
                    <div class="info-value"><?php echo number_format($report['total_records']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Custom View</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $report['custom_view'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Generated At</div>
                    <div class="info-value"><?php echo $generated_at; ?></div>
                </div>
            </div>
            
            <?php if (!empty($report['description'])): ?>
            <div style="margin-top: 15px;">
                <div class="info-label">Description</div>
                <div class="info-value"><?php echo htmlspecialchars($report['description']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($schedule): ?>
        <div class="schedule-info">
            <h4>📅 Scheduled Report</h4>
            <p>This is a <?php echo ucfirst($schedule['frequency']); ?> report that was automatically generated and sent to your email.</p>
        </div>
        <?php endif; ?>

        <?php if (isset($has_attachment) && $has_attachment): ?>
        <div class="attachment-notice">
            <h4>📎 Report Data Attached</h4>
            <p>Complete data has been exported to <strong><?php echo htmlspecialchars($attachment_name); ?></strong> and attached to this email.</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['filters'])): ?>
        <div class="filters-section">
            <h3>Applied Filters</h3>
            <div class="filter-tags">
                <?php foreach ($report['filters'] as $key => $value): ?>
                    <?php if (is_array($value)): ?>
                        <span class="filter-tag"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>: <?php echo implode(', ', $value); ?></span>
                    <?php else: ?>
                        <span class="filter-tag"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>: <?php echo htmlspecialchars($value); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['data']) && count($report['data']) > 0): ?>
        <div class="summary-stats">
            <?php
            $data = $report['data'];
            $total_spend = array_sum(array_column($data, 'spend'));
            $total_impressions = array_sum(array_column($data, 'impressions'));
            $total_clicks = array_sum(array_column($data, 'clicks'));
            $avg_ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) * 100 : 0;
            $avg_roas = count($data) > 0 ? array_sum(array_column($data, 'roas')) / count($data) : 0;
            ?>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($total_spend, 2); ?></div>
                <div class="stat-label">Total Spend</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_impressions); ?></div>
                <div class="stat-label">Impressions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_clicks); ?></div>
                <div class="stat-label">Clicks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($avg_ctr, 2); ?>%</div>
                <div class="stat-label">Avg CTR</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($avg_roas, 2); ?></div>
                <div class="stat-label">Avg ROAS</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($data); ?></div>
                <div class="stat-label">Records</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>This report was generated by the JJ Reporting Dashboard.</p>
            <p><a href="<?php echo $dashboard_url; ?>">View Dashboard</a> | <a href="mailto:support@yourdomain.com">Contact Support</a></p>
            <p>© <?php echo date('Y'); ?> JJ Reporting Dashboard. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
