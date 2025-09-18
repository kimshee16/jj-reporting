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

// Get filters from URL
$date_preset = $_GET['date_preset'] ?? 'this_quarter';
$custom_view = $_GET['custom_view'] ?? 'all';
$platform_filter = $_GET['platform'] ?? 'all';
$device_filter = $_GET['device'] ?? 'all';
$country_filter = $_GET['country'] ?? 'all';
$age_filter = $_GET['age'] ?? 'all';
$placement_filter = $_GET['placement'] ?? 'all';
$format_filter = $_GET['format'] ?? 'all';
$objective_filter = $_GET['objective'] ?? 'all';
$ctr_threshold = $_GET['ctr_threshold'] ?? '';
$roas_threshold = $_GET['roas_threshold'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'spend';

// Include the same data fetching function from the main file
function fetchCrossAccountDataFromDB($pdo, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by) {
    $all_ads = [];
    
    // Build the main query with joins across all tables
    $sql = "
        SELECT 
            a.id as ad_id,
            a.name as ad_name,
            a.status as ad_status,
            a.copy_text,
            a.headline,
            a.cta_button,
            a.format,
            a.creative_type,
            a.impressions,
            a.reach,
            a.clicks,
            a.ctr,
            a.cpc,
            a.spend,
            a.likes,
            a.shares,
            a.comments,
            a.reactions,
            a.cost_per_result,
            a.conversion_value,
            a.roas,
            a.results,
            a.created_time as ad_created_time,
            
            -- Ad Set Information
            adset.id as adset_id,
            adset.name as adset_name,
            adset.status as adset_status,
            adset.daily_budget,
            adset.lifetime_budget,
            adset.age_min,
            adset.age_max,
            adset.gender,
            adset.interests,
            adset.placement as adset_placement,
            adset.device_breakdown,
            
            -- Campaign Information
            camp.id as campaign_id,
            camp.name as campaign_name,
            camp.status as campaign_status,
            camp.objective,
            camp.total_spend as campaign_spend,
            camp.roas as campaign_roas,
            
            -- Account Information
            acc.act_id,
            acc.act_name
        FROM facebook_ads_accounts_ads a
        LEFT JOIN facebook_ads_accounts_adsets adset ON a.adset_id = adset.id
        LEFT JOIN facebook_ads_accounts_campaigns camp ON a.campaign_id = camp.id
        LEFT JOIN facebook_ads_accounts acc ON a.account_id = acc.act_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply custom view filters
    switch ($custom_view) {
        case 'top_performing_quarter':
            $sql .= " AND camp.total_spend > 0 AND camp.roas > 1.0";
            break;
        case 'pakistan_targeting':
            $sql .= " AND (adset.interests LIKE :pakistan OR adset.placement LIKE :pakistan2)";
            $params[':pakistan'] = '%Pakistan%';
            $params[':pakistan2'] = '%Pakistan%';
            break;
        case 'video_high_ctr':
            $sql .= " AND a.format = 'Video' AND a.ctr > 2.0";
            break;
        case 'high_roas':
            $sql .= " AND a.roas > 3.0";
            break;
        case 'low_performing':
            $sql .= " AND a.ctr < 1.0 AND a.spend > 100";
            break;
    }
    
    // Apply other filters (same as main file)
    if ($platform_filter !== 'all') {
        $sql .= " AND adset.placement LIKE :platform";
        $params[':platform'] = '%' . $platform_filter . '%';
    }
    
    if ($device_filter !== 'all') {
        $sql .= " AND adset.device_breakdown LIKE :device";
        $params[':device'] = '%' . $device_filter . '%';
    }
    
    if ($age_filter !== 'all') {
        switch ($age_filter) {
            case '18-24':
                $sql .= " AND adset.age_min <= 18 AND adset.age_max >= 24";
                break;
            case '25-34':
                $sql .= " AND adset.age_min <= 25 AND adset.age_max >= 34";
                break;
            case '35-44':
                $sql .= " AND adset.age_min <= 35 AND adset.age_max >= 44";
                break;
            case '45-54':
                $sql .= " AND adset.age_min <= 45 AND adset.age_max >= 54";
                break;
            case '55+':
                $sql .= " AND adset.age_min >= 55";
                break;
        }
    }
    
    if ($placement_filter !== 'all') {
        $sql .= " AND adset.placement LIKE :placement";
        $params[':placement'] = '%' . $placement_filter . '%';
    }
    
    if ($format_filter !== 'all') {
        $sql .= " AND a.format = :format";
        $params[':format'] = $format_filter;
    }
    
    if ($objective_filter !== 'all') {
        $sql .= " AND camp.objective = :objective";
        $params[':objective'] = $objective_filter;
    }
    
    if (!empty($ctr_threshold)) {
        $sql .= " AND a.ctr >= :ctr_threshold";
        $params[':ctr_threshold'] = floatval($ctr_threshold);
    }
    
    if (!empty($roas_threshold)) {
        $sql .= " AND a.roas >= :roas_threshold";
        $params[':roas_threshold'] = floatval($roas_threshold);
    }
    
    // Apply date filter
    $date_condition = getDateCondition($date_preset);
    if ($date_condition) {
        $sql .= " AND " . $date_condition;
    }
    
    // Apply sorting
    switch ($sort_by) {
        case 'spend':
            $sql .= " ORDER BY a.spend DESC";
            break;
        case 'roas':
            $sql .= " ORDER BY a.roas DESC";
            break;
        case 'ctr':
            $sql .= " ORDER BY a.ctr DESC";
            break;
        case 'impressions':
            $sql .= " ORDER BY a.impressions DESC";
            break;
        case 'clicks':
            $sql .= " ORDER BY a.clicks DESC";
            break;
        case 'name':
            $sql .= " ORDER BY a.name ASC";
            break;
        default:
            $sql .= " ORDER BY a.spend DESC";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching cross-account data: " . $e->getMessage());
        return [];
    }
    
    return $all_ads;
}

function getDateCondition($date_preset) {
    $now = new DateTime();
    
    switch ($date_preset) {
        case 'today':
            return "DATE(a.created_time) = CURDATE()";
        case 'yesterday':
            return "DATE(a.created_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        case 'this_week':
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
        case 'last_week':
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) AND a.created_time < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
        case 'this_month':
            return "MONTH(a.created_time) = MONTH(CURDATE()) AND YEAR(a.created_time) = YEAR(CURDATE())";
        case 'last_month':
            return "a.created_time >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), INTERVAL 1 MONTH) AND a.created_time < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY)";
        case 'this_quarter':
            $quarter = ceil($now->format('n') / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            return "MONTH(a.created_time) >= $start_month AND MONTH(a.created_time) < " . ($start_month + 3) . " AND YEAR(a.created_time) = " . $now->format('Y');
        case 'last_quarter':
            $quarter = ceil($now->format('n') / 3) - 1;
            if ($quarter == 0) {
                $quarter = 4;
                $year = $now->format('Y') - 1;
            } else {
                $year = $now->format('Y');
            }
            $start_month = ($quarter - 1) * 3 + 1;
            return "MONTH(a.created_time) >= $start_month AND MONTH(a.created_time) < " . ($start_month + 3) . " AND YEAR(a.created_time) = $year";
        case 'this_year':
            return "YEAR(a.created_time) = " . $now->format('Y');
        case 'last_year':
            return "YEAR(a.created_time) = " . ($now->format('Y') - 1);
        default:
            return "a.created_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
}

// Fetch data
$cross_account_data = fetchCrossAccountDataFromDB($pdo, $date_preset, $custom_view, $platform_filter, $device_filter, $country_filter, $age_filter, $placement_filter, $format_filter, $objective_filter, $ctr_threshold, $roas_threshold, $sort_by);

// Generate CSV
$filename = 'cross_account_reports_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
fputcsv($output, [
    'Account Name',
    'Campaign Name',
    'Ad Set Name',
    'Ad Name',
    'Ad Status',
    'Format',
    'Objective',
    'Targeting (Age)',
    'Targeting (Gender)',
    'Spend',
    'Impressions',
    'Reach',
    'Clicks',
    'CTR',
    'CPC',
    'ROAS',
    'Results',
    'Likes',
    'Shares',
    'Comments',
    'Reactions',
    'Cost Per Result',
    'Conversion Value',
    'Created Time'
]);

// CSV Data
foreach ($cross_account_data as $ad) {
    fputcsv($output, [
        $ad['act_name'],
        $ad['campaign_name'],
        $ad['adset_name'],
        $ad['ad_name'],
        $ad['ad_status'],
        $ad['format'] ?? 'N/A',
        $ad['objective'],
        ($ad['age_min'] && $ad['age_max']) ? $ad['age_min'] . '-' . $ad['age_max'] : 'N/A',
        $ad['gender'] ?? 'N/A',
        number_format($ad['spend'] ?? 0, 2),
        number_format($ad['impressions'] ?? 0),
        number_format($ad['reach'] ?? 0),
        number_format($ad['clicks'] ?? 0),
        number_format($ad['ctr'] ?? 0, 2),
        number_format($ad['cpc'] ?? 0, 2),
        number_format($ad['roas'] ?? 0, 2),
        number_format($ad['results'] ?? 0),
        number_format($ad['likes'] ?? 0),
        number_format($ad['shares'] ?? 0),
        number_format($ad['comments'] ?? 0),
        number_format($ad['reactions'] ?? 0),
        number_format($ad['cost_per_result'] ?? 0, 2),
        number_format($ad['conversion_value'] ?? 0, 2),
        $ad['ad_created_time']
    ]);
}

fclose($output);
exit;
?>
