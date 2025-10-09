<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once 'config.php';

// Get parameters from URL
$adset_id = $_GET['adset_id'] ?? '';
$campaign_id = $_GET['campaign_id'] ?? '';
$account_id = $_GET['account_id'] ?? '';
$export_format = $_GET['export'] ?? 'csv';

if (empty($adset_id) || empty($campaign_id) || empty($account_id)) {
    die("Missing required parameters: adset_id, campaign_id, and account_id");
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

// Get names for filename
$account_name = 'Unknown Account';
$campaign_name = 'Unknown Campaign';
$adset_name = 'Unknown Ad Set';

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
    
    // Get adset name from Facebook API
    $adset_api_url = "https://graph.facebook.com/v21.0/{$adset_id}?fields=name&access_token={$access_token}";
    $adset_response = file_get_contents($adset_api_url);
    $adset_data = json_decode($adset_response, true);
    
    if (isset($adset_data['name']) && !empty($adset_data['name'])) {
        $adset_name = $adset_data['name'];
    }
} catch(Exception $e) {
    error_log("Error fetching names: " . $e->getMessage());
}

// Fetch ads data from Facebook Graph API
$ads = [];
$error_message = '';

try {
    // Build API URL
    $api_url = "https://graph.facebook.com/v21.0/{$adset_id}/ads?";
    $api_url .= "fields=id,name,creative{object_story_spec},status,ad_review_feedback,insights{impressions,reach,clicks,ctr,cpc,spend,actions,purchase_roas}";
    $api_url .= "&date_preset=this_month";
    $api_url .= "&access_token={$access_token}";
    
    // Fetch data
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    
    if (isset($data['data']) && is_array($data['data'])) {
        $ads = $data['data'];
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to fetch ads data';
    }
} catch(Exception $e) {
    $error_message = "Error fetching ads: " . $e->getMessage();
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

function getCreativeSummary($creative) {
    if (empty($creative) || !isset($creative['object_story_spec'])) {
        return '';
    }
    
    $spec = $creative['object_story_spec'];
    $summary = [];
    
    // Get page info
    if (isset($spec['page_id'])) {
        $summary[] = "Page ID: " . $spec['page_id'];
    }
    
    // Get link data
    if (isset($spec['link_data'])) {
        $link_data = $spec['link_data'];
        if (isset($link_data['name'])) {
            $summary[] = "Link: " . $link_data['name'];
        }
        if (isset($link_data['message'])) {
            $summary[] = "Message: " . substr($link_data['message'], 0, 50) . "...";
        }
    }
    
    // Get video data
    if (isset($spec['video_data'])) {
        $video_data = $spec['video_data'];
        if (isset($video_data['video_id'])) {
            $summary[] = "Video ID: " . $video_data['video_id'];
        }
    }
    
    // Get photo data
    if (isset($spec['photo_data'])) {
        $photo_data = $spec['photo_data'];
        if (isset($photo_data['url'])) {
            $summary[] = "Photo: " . basename($photo_data['url']);
        }
    }
    
    return implode(' | ', $summary);
}

function getActionsSummary($actions) {
    if (empty($actions) || !is_array($actions)) {
        return '';
    }
    
    $summary = [];
    foreach ($actions as $action) {
        $action_type = $action['action_type'] ?? '';
        $value = $action['value'] ?? '0';
        $summary[] = "{$action_type}: {$value}";
    }
    
    return implode(', ', $summary);
}

// Prepare export data
$export_data = [];

foreach ($ads as $ad) {
    $insights = $ad['insights']['data'][0] ?? [];
    
    $export_data[] = [
        'Ad ID' => $ad['id'] ?? '',
        'Ad Name' => $ad['name'] ?? '',
        'Campaign Name' => $campaign_name,
        'Ad Set Name' => $adset_name,
        'Account Name' => $account_name,
        'Status' => $ad['status'] ?? '',
        'Creative Summary' => getCreativeSummary($ad['creative'] ?? []),
        'Ad Review Feedback' => $ad['ad_review_feedback'] ?? '',
        'Impressions' => formatNumber($insights['impressions'] ?? 0),
        'Reach' => formatNumber($insights['reach'] ?? 0),
        'Clicks' => formatNumber($insights['clicks'] ?? 0),
        'Spend' => formatCurrency($insights['spend'] ?? 0),
        'CPC' => formatCurrency($insights['cpc'] ?? 0),
        'CTR' => formatPercentage($insights['ctr'] ?? 0),
        'ROAS' => formatNumber($insights['purchase_roas'] ?? 0),
        'Actions' => getActionsSummary($insights['actions'] ?? [])
    ];
}

// Generate filename
$timestamp = date('Y-m-d_H-i-s');
$safe_adset_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $adset_name);
$filename = "ads_{$safe_adset_name}_{$timestamp}.{$export_format}";

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
