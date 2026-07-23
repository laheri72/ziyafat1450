<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

// Check if user has amali access
if (!has_amali_access()) {
    die("Unauthorized access.");
}

// Get parameters
$filter_category = isset($_GET['filter_category']) ? clean_input($_GET['filter_category']) : '';
$filter_classification = isset($_GET['filter_classification']) ? clean_input($_GET['filter_classification']) : '';
$threshold = isset($_GET['threshold']) ? floatval($_GET['threshold']) : 3.7;

if (!is_super_admin()) {
    $assigned_category = get_assigned_category();
    if ($assigned_category) {
        $filter_category = $assigned_category;
    }
}

// Build filters for SQL
$category_filter_sql = "";
$classification_filter_sql = "";
$params = [];

if ($filter_category) {
    $category_filter_sql = " AND u.category = ?";
    $params[] = $filter_category;
}
if ($filter_classification) {
    $classification_filter_sql = " AND u.classification = ?";
    $params[] = $filter_classification;
}

// Fetch user summary data (same logic as amali_reports.php)
$sql = "SELECT u.id as user_id, u.name, u.its_number, u.category, u.classification,
            COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) as completed_juz,
            FLOOR(COUNT(DISTINCT CASE WHEN qp.is_completed = 1 THEN CONCAT(qp.quran_number, '-', qp.juz_number) END) / 30) as completed_qurans,
            COUNT(DISTINCT CASE WHEN bt.status = 'completed' THEN bt.book_id END) as books_completed
        FROM users u
        LEFT JOIN quran_progress qp ON u.id = qp.user_id
        LEFT JOIN book_transcription bt ON u.id = bt.user_id
        WHERE (u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%'" 
        . $category_filter_sql . $classification_filter_sql . 
        " GROUP BY u.id, u.name, u.its_number, u.category, u.classification";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

// Get target counts for calculations
$sql_targets = "SELECT category, SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 GROUP BY category";
$targets_result = $conn->query($sql_targets);
$category_targets = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
while ($row = $targets_result->fetch_assoc()) {
    $category_targets[$row['category']] = $row['total_target'];
}

// Get user category data (Duas, Tasbeeh, Namaz)
$sql_user_categories = "SELECT u.id as user_id, dm.category, COALESCE(SUM(de.count_added), 0) as count
                        FROM users u CROSS JOIN duas_master dm
                        LEFT JOIN dua_entries de ON u.id = de.user_id AND dm.id = de.dua_id
                        WHERE dm.is_active = 1" . $category_filter_sql . $classification_filter_sql . "
                        GROUP BY u.id, dm.category";

$uc_stmt = $conn->prepare($sql_user_categories);
if (!empty($params)) {
    $uc_stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$uc_stmt->execute();
$uc_result = $uc_stmt->get_result();
$user_category_data = [];
while($row = $uc_result->fetch_assoc()) {
    $user_category_data[$row['user_id']][$row['category']] = $row['count'];
}

// Prepare report data
$report_users = [];
while ($user = $users_result->fetch_assoc()) {
    $user_id = $user['user_id'];
    $user_cats = isset($user_category_data[$user_id]) ? $user_category_data[$user_id] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
    
    $quran_pct = round(($user['completed_juz'] / 120) * 100, 2);
    $dua_pct = $category_targets['dua'] > 0 ? round(($user_cats['dua'] / $category_targets['dua']) * 100, 2) : 0;
    $tasbeeh_pct = $category_targets['tasbeeh'] > 0 ? round(($user_cats['tasbeeh'] / $category_targets['tasbeeh']) * 100, 2) : 0;
    $namaz_pct = $category_targets['namaz'] > 0 ? round(($user_cats['namaz'] / $category_targets['namaz']) * 100, 2) : 0;
    
    // Overall progress formula as per amali_reports.php
    $overall_progress = round(($quran_pct + $dua_pct + $tasbeeh_pct + $namaz_pct) / 4, 2);
    
    $user['overall_progress'] = $overall_progress;
    $user['dua_count'] = $user_cats['dua'];
    $user['tasbeeh_count'] = $user_cats['tasbeeh'];
    $user['namaz_count'] = $user_cats['namaz'];
    
    $report_users[] = $user;
}

// Sort by name
usort($report_users, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$filename = "Amali_Janib_Progress_Report_" . date('Ymd_His') . ".doc";

// Set headers for Word download
header("Content-Type: application/vnd.ms-word");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("content-disposition: attachment;filename=" . $filename);

// HTML content for Word
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset="utf-8">
    <title>Amali Janib Progress Report</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
        .header { font-size: 24px; font-weight: bold; color: #243b53; margin-bottom: 5px; }
        .sub-header { font-size: 14px; color: #64748b; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        
        .section-title { font-size: 18px; font-weight: bold; color: #243b53; margin: 20px 0 10px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #243b53; color: #ffffff; text-align: left; padding: 10px; font-size: 13px; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; vertical-align: top; }
        
        .summary-table td { border: none; padding: 5px 10px; }
        .summary-label { font-weight: bold; width: 150px; }
        .summary-value { color: #243b53; }
        
        .progress-flag { color: #dc2626; font-weight: bold; } /* Crimson Red */
        .text-muted { color: #64748b; font-size: 10px; }
        
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-primary { background-color: #6366f1; color: white; }
    </style>
</head>
<body>

    <div class="header">Amali Janib Progress Report</div>
    <div class="sub-header">User-wise Metrics Tracker — Exception Flagging enabled for Progress < <?php echo $threshold; ?>%</div>

    <div class="section-title">Summary Overview</div>
    <table class="summary-table">
        <tr>
            <td class="summary-label">Total Tracked Users</td>
            <td class="summary-value"><?php echo count($report_users); ?> Total Users</td>
            <td class="text-muted">As documented in system summary section.</td>
        </tr>
        <tr>
            <td class="summary-label">Flagged Status</td>
            <td class="summary-value">Exception Marked</td>
            <td class="text-muted">Users below <?php echo $threshold; ?>% progress highlighted in crimson red text.</td>
        </tr>
        <?php if($filter_category): ?>
        <tr>
            <td class="summary-label">Filtered By</td>
            <td class="summary-value"><?php echo htmlspecialchars($filter_category); ?> Jamea</td>
            <td class="text-muted">Report restricted to selected branch.</td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="section-title">Detailed User Records</div>
    <table>
        <thead>
            <tr>
                <th style="width: 25%;">Name</th>
                <th>ITSID</th>
                <th>City</th>
                <th>Quran</th>
                <th>Duas</th>
                <th>Tasbeeh</th>
                <th>Kutub</th>
                <th>Progress (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_users as $user): 
                $is_flagged = ($user['overall_progress'] < $threshold);
                $row_class = $is_flagged ? 'progress-flag' : '';
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td>
                        <strong style="<?php echo $is_flagged ? 'color: #dc2626;' : ''; ?>"><?php echo htmlspecialchars($user['name']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                    <td><?php echo htmlspecialchars($user['category'] ?: 'N/A'); ?></td>
                    <td><?php echo $user['completed_qurans']; ?> <span class="text-muted">(<?php echo $user['completed_juz']; ?> Juz)</span></td>
                    <td><?php echo $user['dua_count']; ?> <span class="text-muted">recited</span></td>
                    <td><?php echo $user['tasbeeh_count']; ?> <span class="text-muted">count</span></td>
                    <td><?php echo $user['books_completed']; ?> <span class="text-muted">done</span></td>
                    <td style="text-align: right;">
                        <strong style="<?php echo $is_flagged ? 'color: #dc2626;' : ''; ?>"><?php echo number_format($user['overall_progress'], 2); ?>%</strong>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; font-size: 10px; color: #94a3b8; text-align: center;">
        Generated on <?php echo date('F d, Y H:i:s'); ?> | System Automated Report
    </div>

</body>
</html>
