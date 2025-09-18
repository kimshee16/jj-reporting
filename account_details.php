<?php
// Config
require_once 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ad_account_id = $_GET['id'];  
// $accountId = $_GET['account_id']

$stmt = $pdo->prepare("
    SELECT access_token_id, act_id 
    FROM facebook_ads_accounts 
    WHERE id = ? OR act_id = ?
");
$stmt->execute([$ad_account_id, $ad_account_id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

$tokenStmt = $pdo->prepare("
    SELECT access_token 
    FROM facebook_access_tokens 
    WHERE id = ?
");
$tokenStmt->execute([$account['access_token_id']]);
$tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);

$access_token = $tokenData['access_token'];
  // Replace with your ad account ID

// Function to make Graph API calls
function fbApiRequest($endpoint, $params = []) {
    global $access_token;

    $url = "https://graph.facebook.com/v19.0" . $endpoint;
    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$access_token}"
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($response, true);
}

// 1. Basic Account Info
$account = fbApiRequest("/{$ad_account_id}", [
    "fields" => "name,account_status,currency,timezone_name"
]);

// 2. Spend This Month
$spend = fbApiRequest("/{$ad_account_id}/insights", [
    "fields" => "spend",
    "time_range" => [
        "since" => date("Y-m-01"),
        "until" => date("Y-m-t")
    ]
]);
$totalSpend = $spend['data'][0]['spend'] ?? 0;

// 3. Active Campaigns
$campaigns = fbApiRequest("/{$ad_account_id}/campaigns", [
    "fields" => "id,status",
    "limit"  => 100
]);
$activeCampaigns = 0;
if (!empty($campaigns['data'])) {
    foreach ($campaigns['data'] as $c) {
        if ($c['status'] === "ACTIVE") {
            $activeCampaigns++;
        }
    }
}

// 4. Last Sync Date
$lastSync = date("Y-m-d H:i:s");

// ✅ Build Result
$response = [
    "name" => $account['name'] ?? "N/A",
    "account_id" => $account['id'] ?? $ad_account_id,
    "status" => ($account['account_status'] == 1) ? "Active" : "Inactive",
    "currency" => $account['currency'] ?? "N/A",
    "timezone" => $account['timezone_name'] ?? "N/A",
    "totalspendthismonth" => $totalSpend,
    "noofactivecampaigns" => $activeCampaigns,
    "lastsyncdate" => $lastSync
];

// // Output as JSON for API or Array for PHP
// header('Content-Type: application/json');
// echo json_encode($result, JSON_PRETTY_PRINT);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Ad Account Overview</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f7fa;
      color: #333;
      margin: 0;
      padding: 40px;
    }
    .container {
      max-width: 800px;
      margin: auto;
    }
    .card {
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    h2 {
      margin-top: 0;
      font-size: 24px;
      color: #2c3e50;
    }
    .details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px 30px;
      margin-top: 20px;
    }
    .details div {
      padding: 8px 0;
    }
    .label {
      font-weight: bold;
      color: #555;
    }
    .value {
      color: #000;
    }
    .status-active {
      color: green;
      font-weight: bold;
    }
    .status-inactive {
      color: red;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h2>Ad Account Overview</h2>
      <div class="details">
        <div><span class="label">Name:</span> <span class="value"><?= $response["name"] ?></span></div>
        <div><span class="label">Account ID:</span> <span class="value"><?= $response["account_id"] ?></span></div>
        <div>
          <span class="label">Status:</span> 
          <span class="value <?= strtolower($response["status"]) === 'active' ? 'status-active' : 'status-inactive' ?>">
            <?= $response["status"] ?>
          </span>
        </div>
        <div><span class="label">Currency:</span> <span class="value"><?= $response["currency"] ?></span></div>
        <div><span class="label">Timezone:</span> <span class="value"><?= $response["timezone"] ?></span></div>
        <div><span class="label">Total Spend This Month:</span> <span class="value"><?= $response["totalspendthismonth"] ?></span></div>
        <div><span class="label">No. of Active Campaigns:</span> <span class="value"><?= $response["noofactivecampaigns"] ?></span></div>
        <div><span class="label">Last Sync Date:</span> <span class="value"><?= $response["lastsyncdate"] ?></span></div>
      </div>
    </div>
  </div>
</body>
</html>