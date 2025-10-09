<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$admin_id = $_SESSION['admin_id'];
$report_id = $_GET['report_id'] ?? '';

// Get report details
$report = null;
if (!empty($report_id)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM saved_reports WHERE id = ? AND (created_by = ? OR is_public = 1 OR id IN (SELECT report_id FROM report_sharing WHERE shared_with_admin_id = ?))");
        $stmt->execute([$report_id, $admin_id, $admin_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_message = "Error fetching report: " . $e->getMessage();
    }
}

// Handle form submission
if ($_POST['action'] ?? '' === 'create_schedule') {
    $frequency = $_POST['frequency'] ?? '';
    $day_of_week = $_POST['day_of_week'] ?? null;
    $day_of_month = $_POST['day_of_month'] ?? null;
    $time_of_day = $_POST['time_of_day'] ?? '09:00:00';
    $email_recipients = $_POST['email_recipients'] ?? '';
    $email_subject = $_POST['email_subject'] ?? '';
    $email_message = $_POST['email_message'] ?? '';
    
    if (!empty($frequency) && !empty($email_recipients)) {
        // Parse email recipients
        $recipients = array_filter(array_map('trim', explode(',', $email_recipients)));
        
        // Calculate next run time
        $next_run = calculateNextRun($frequency, $day_of_week, $day_of_month, $time_of_day);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO report_schedules (report_id, frequency, day_of_week, day_of_month, time_of_day, email_recipients, email_subject, email_message, next_run) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $report_id,
                $frequency,
                $day_of_week,
                $day_of_month,
                $time_of_day,
                json_encode($recipients),
                $email_subject,
                $email_message,
                $next_run
            ]);
            
            $success_message = "Schedule created successfully!";
            echo "<script>setTimeout(() => { window.close(); }, 2000);</script>";
        } catch(PDOException $e) {
            $error_message = "Error creating schedule: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

function calculateNextRun($frequency, $day_of_week, $day_of_month, $time_of_day) {
    $now = new DateTime();
    $next_run = clone $now;
    
    switch ($frequency) {
        case 'daily':
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            if ($next_run <= $now) {
                $next_run->add(new DateInterval('P1D'));
            }
            break;
            
        case 'weekly':
            $days = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7];
            $target_day = $day_of_week;
            $current_day = $now->format('N');
            
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            
            $days_until_target = ($target_day - $current_day + 7) % 7;
            if ($days_until_target === 0 && $next_run <= $now) {
                $days_until_target = 7;
            }
            
            $next_run->add(new DateInterval('P' . $days_until_target . 'D'));
            break;
            
        case 'monthly':
            $next_run->setTime(explode(':', $time_of_day)[0], explode(':', $time_of_day)[1]);
            $next_run->setDate($now->format('Y'), $now->format('m'), $day_of_month);
            
            if ($next_run <= $now) {
                $next_run->add(new DateInterval('P1M'));
            }
            break;
    }
    
    return $next_run->format('Y-m-d H:i:s');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Report - JJ Reporting Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
        }
        .schedule-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .schedule-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .schedule-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1877f2;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: #1877f2;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .success-message, .error-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="schedule-container">
        <div class="schedule-header">
            <h2>Schedule Report</h2>
            <?php if ($report): ?>
                <p><?php echo htmlspecialchars($report['name']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="schedule-body">
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($report): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_schedule">
                    
                    <div class="form-group">
                        <label for="frequency">Frequency *</label>
                        <select name="frequency" id="frequency" required onchange="toggleFrequencyOptions()">
                            <option value="">Select frequency</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" id="dayOfWeekGroup" style="display: none;">
                            <label for="day_of_week">Day of Week</label>
                            <select name="day_of_week" id="day_of_week">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="dayOfMonthGroup" style="display: none;">
                            <label for="day_of_month">Day of Month</label>
                            <select name="day_of_month" id="day_of_month">
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_of_day">Time *</label>
                        <input type="time" name="time_of_day" id="time_of_day" value="09:00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_recipients">Email Recipients *</label>
                        <input type="text" name="email_recipients" id="email_recipients" placeholder="email1@example.com, email2@example.com" required>
                        <small>Separate multiple emails with commas</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_subject">Email Subject</label>
                        <input type="text" name="email_subject" id="email_subject" value="Scheduled Report: <?php echo htmlspecialchars($report['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email_message">Email Message</label>
                        <textarea name="email_message" id="email_message" rows="3" placeholder="Optional message to include in the email..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.close()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Schedule</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    Report not found or you don't have permission to schedule it.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleFrequencyOptions() {
            const frequency = document.getElementById('frequency').value;
            const dayOfWeekGroup = document.getElementById('dayOfWeekGroup');
            const dayOfMonthGroup = document.getElementById('dayOfMonthGroup');
            
            // Hide all options first
            dayOfWeekGroup.style.display = 'none';
            dayOfMonthGroup.style.display = 'none';
            
            // Show relevant options
            if (frequency === 'weekly') {
                dayOfWeekGroup.style.display = 'block';
            } else if (frequency === 'monthly') {
                dayOfMonthGroup.style.display = 'block';
            }
        }
    </script>
</body>
</html>
