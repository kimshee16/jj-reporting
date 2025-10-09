<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get parameters from URL
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';
$export_format = $_GET['export'] ?? 'csv';

if (empty($campaign_id) || empty($account_id)) {
    die("Missing required parameters: campaign_id and account_id");
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get admin access token
$stmt = $pdo->prepare("SELECT access_token FROM facebook_access_tokens WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$token_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token_data || empty($token_data['access_token'])) {
    die("Access token not found. Please update your access token first.");
}

$access_token = $token_data['access_token'];

// Get campaign name and account name for filename
$campaign_name = 'Unknown Campaign';
$account_name = 'Unknown Account';

try {
    // Get account name
    $stmt = $pdo->prepare("SELECT act_name FROM facebook_ads_accounts WHERE act_id = ?");
    $stmt->execute([$account_id]);
    $account_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account_data && !empty($account_data['act_name'])) {
        $account_name = $account_data['act_name'];
    }
    
    // Get campaign name from Facebook API
    $campaign_api_url = "https://graph.facebook.com/v21.0/{$campaign_id}?fields=name&access_token={$access_token}";
    $campaign_response = file_get_contents($campaign_api_url);
    $campaign_data = json_decode($campaign_response, true);
    
    if (isset($campaign_data['name']) && !empty($campaign_data['name'])) {
        $campaign_name = $campaign_data['name'];
    }
} catch(Exception $e) {
    error_log("Error fetching campaign/account names: " . $e->getMessage());
}

// Fetch ad sets data from Facebook Graph API
$adsets = [];
$error_message = '';

try {
    // Build API URL
    $api_url = "https://graph.facebook.com/v21.0/{$campaign_id}/adsets?";
    $api_url .= "fields=name,daily_budget,lifetime_budget,status,targeting,start_time,end_time,insights{impressions,reach,clicks,spend,cpc,ctr,cpm,actions}";
    $api_url .= "&date_preset=this_month";
    $api_url .= "&access_token={$access_token}";
    
    // Fetch data
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    
    if (isset($data['data']) && is_array($data['data'])) {
        $adsets = $data['data'];
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ad sets data';
    }
} catch(Exception $e) {
    $error_message = "Error fetching ad sets: " . $e->getMessage();
}

if (!empty($error_message)) {
    die("Export failed: " . $error_message);
}

// Helper functions
function formatCurrency($value) {
    if (empty($value) || $value === 0) return '0.00';
    return number_format($value, 2);
}

function formatPercentage($value) {
    if (empty($value) || $value === 0) return '0.00';
    return number_format($value, 2);
}

function formatNumber($value) {
    if (empty($value) || $value === 0) return '0';
    return number_format($value);
}

function formatDate($value) {
    if (empty($value)) return '';
    try {
        return date('Y-m-d H:i:s', strtotime($value));
    } catch(Exception $e) {
        return '';
    }
}

function getTargetingSummary($targeting) {
    if (empty($targeting)) return '';
    
    $summary = [];
    
    // Age
    if (isset($targeting['age_min']) && isset($targeting['age_max'])) {
        $summary[] = "Age: {$targeting['age_min']}-{$targeting['age_max']}";
    }
    
    // Gender
    if (isset($targeting['genders'])) {
        $genders = $targeting['genders'];
        if (is_array($genders)) {
            $gender_labels = [];
            foreach ($genders as $gender) {
                switch ($gender) {
                    case 1:
                        $gender_labels[] = 'Male';
                        break;
                    case 2:
                        $gender_labels[] = 'Female';
                        break;
                    default:
                        $gender_labels[] = $gender;
                }
            }
            $summary[] = "Gender: " . implode(', ', $gender_labels);
        } else {
            switch ($genders) {
                case 1:
                    $summary[] = "Gender: Male";
                    break;
                case 2:
                    $summary[] = "Gender: Female";
                    break;
                default:
                    $summary[] = "Gender: {$genders}";
            }
        }
    }
    
    // Interests
    if (isset($targeting['interests']) && is_array($targeting['interests'])) {
        $interest_count = count($targeting['interests']);
        $summary[] = "Interests: {$interest_count} selected";
    }
    
    // Custom Audiences
    if (isset($targeting['custom_audiences']) && is_array($targeting['custom_audiences'])) {
        $ca_count = count($targeting['custom_audiences']);
        $summary[] = "Custom Audiences: {$ca_count}";
    }
    
    // Lookalike Audiences
    if (isset($targeting['lookalike_audiences']) && is_array($targeting['lookalike_audiences'])) {
        $la_count = count($targeting['lookalike_audiences']);
        $summary[] = "Lookalike Audiences: {$la_count}";
    }
    
    return implode(' | ', $summary);
}

function getPlacementSummary($targeting) {
    if (empty($targeting) || !isset($targeting['publisher_platforms'])) {
        return '';
    }
    
    $platforms = $targeting['publisher_platforms'];
    if (is_array($platforms)) {
        return implode(', ', $platforms);
    }
    
    return $platforms;
}

// Prepare export data
$export_data = [];

foreach ($adsets as $adset) {
    $insights = $adset['insights']['data'][0] ?? [];
    
    $export_data[] = [
        'Ad Set ID' => $adset['id'] ?? '',
        'Ad Set Name' => $adset['name'] ?? '',
        'Campaign Name' => $campaign_name,
        'Account Name' => $account_name,
        'Status' => $adset['status'] ?? '',
        'Daily Budget' => formatCurrency($adset['daily_budget'] ?? 0),
        'Lifetime Budget' => formatCurrency($adset['lifetime_budget'] ?? 0),
        'Start Date' => formatDate($adset['start_time'] ?? ''),
        'End Date' => formatDate($adset['end_time'] ?? ''),
        'Targeting Summary' => getTargetingSummary($adset['targeting'] ?? []),
        'Placement' => getPlacementSummary($adset['targeting'] ?? []),
        'Impressions' => formatNumber($insights['impressions'] ?? 0),
        'Reach' => formatNumber($insights['reach'] ?? 0),
        'Clicks' => formatNumber($insights['clicks'] ?? 0),
        'Spend' => formatCurrency($insights['spend'] ?? 0),
        'CPC' => formatCurrency($insights['cpc'] ?? 0),
        'CTR' => formatPercentage($insights['ctr'] ?? 0),
        'CPM' => formatCurrency($insights['cpm'] ?? 0),
        'Actions' => isset($insights['actions']) ? json_encode($insights['actions']) : ''
    ];
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$safe_campaign_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $campaign_name);
$filename = "adsets_{$safe_campaign_name}_{$timestamp}.{$export_format}";

// Create exports directory if it doesn't exist
$export_dir = __DIR__ . '/exports/';
if (!is_dir($export_dir)) {
    mkdir($export_dir, 0755, true);
}

$filepath = $export_dir . $filename;

// Generate file based on format
if ($export_format === 'csv') {
    // Generate CSV
    $file = fopen($filepath, 'w');
    
    if (!empty($export_data)) {
        // Write headers
        fputcsv($file, array_keys($export_data[0]));
        
        // Write data
        foreach ($export_data as $row) {
            fputcsv($file, $row);
        }
    }
    
    fclose($file);
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    
    // Output file
    readfile($filepath);
    
} elseif ($export_format === 'excel' || $export_format === 'xlsx') {
    // Generate Excel file using PhpSpreadsheet
    require_once __DIR__ . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    if (!empty($export_data)) {
        // Write headers
        $headers = array_keys($export_data[0]);
        foreach ($headers as $colIdx => $header) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($columnLetter . '1', $header);
        }
        
        // Write data rows
        $rowNum = 2;
        foreach ($export_data as $row) {
            foreach ($headers as $colIdx => $header) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($columnLetter . $rowNum, isset($row[$header]) ? $row[$header] : '');
            }
            $rowNum++;
        }
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filepath);
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    
    // Output file
    readfile($filepath);
    
} else {
    die("Unsupported export format: " . $export_format);
}

// Clean up temporary file
unlink($filepath);

exit();
?>
