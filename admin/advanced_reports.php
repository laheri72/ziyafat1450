<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Secure endpoint - require admin credentials
require_admin();

if (!has_amali_access()) {
    die("Unauthorized access.");
}

// 1. Get parameters and handle branch constraints
$is_super = is_super_admin();
$is_category_coordinator = !$is_super;
$assigned_category = get_assigned_category();

$selected_branch = isset($_GET['branch']) ? clean_input($_GET['branch']) : '';
if (!$is_super && $assigned_category) {
    // Branch coordinator is strictly locked to their branch
    $selected_branch = $assigned_category;
}

$report_type = isset($_GET['report_type']) ? clean_input($_GET['report_type']) : 'overall';
$threshold = isset($_GET['threshold']) ? floatval($_GET['threshold']) : 3.7;

// Safely retrieve selected item IDs
$selected_item_ids = isset($_GET['item_ids']) ? $_GET['item_ids'] : [];
if (!is_array($selected_item_ids)) {
    $selected_item_ids = [];
}
$selected_item_ids = array_map('intval', $selected_item_ids);
$selected_item_ids = array_filter($selected_item_ids);

// 2. Fetch master lists for filter dropdowns (preloaded for JS show/hide)
$duas_list = $conn->query("SELECT id, dua_name, target_count FROM duas_master WHERE category = 'dua' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC")->fetch_all(MYSQLI_ASSOC);
$tasbeeh_list = $conn->query("SELECT id, dua_name, target_count FROM duas_master WHERE category = 'tasbeeh' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC")->fetch_all(MYSQLI_ASSOC);
$namaz_list = $conn->query("SELECT id, dua_name, target_count FROM duas_master WHERE category = 'namaz' AND is_active = 1 ORDER BY display_order ASC, dua_name ASC")->fetch_all(MYSQLI_ASSOC);
$books_list = $conn->query("SELECT id, book_name, total_pages FROM books_master WHERE is_active = 1 ORDER BY display_order ASC, book_name ASC")->fetch_all(MYSQLI_ASSOC);
$mazars_list = $conn->query("SELECT id, mazar_name FROM mazars_master WHERE is_active = 1 ORDER BY display_order ASC, mazar_name ASC")->fetch_all(MYSQLI_ASSOC);

// 3. Build SQL filters for users
$category_filter_sql = "";
$user_params = [];
$user_param_types = "";

if ($selected_branch) {
    $category_filter_sql = " AND u.category = ?";
    $user_params[] = $selected_branch;
    $user_param_types .= "s";
}

// Fetch users in scope
$sql_users = "SELECT u.id as user_id, u.name, u.its_number, u.category, u.classification 
              FROM users u 
              WHERE (u.role = 'user' OR u.role = 'admin') AND u.its_number NOT LIKE '000000%'" 
              . $category_filter_sql . 
              " ORDER BY u.name ASC";

$stmt = $conn->prepare($sql_users);
if (!empty($user_params)) {
    $stmt->bind_param($user_param_types, ...$user_params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Gather report data based on report_type
$report_columns = [];
$report_matrix = [];
$report_users = [];

if ($report_type === 'overall') {
    // Get targets for calculations
    $targets_res = $conn->query("SELECT category, SUM(target_count) as total_target FROM duas_master WHERE is_active = 1 GROUP BY category");
    $category_targets = ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
    while ($row = $targets_res->fetch_assoc()) {
        $category_targets[$row['category']] = (int)$row['total_target'];
    }

    // Fetch spiritual logs (Duas, Tasbeeh, Namaz) for all users
    $sql_overall_logs = "SELECT de.user_id, dm.category, COALESCE(SUM(de.count_added), 0) as total_count 
                         FROM dua_entries de 
                         JOIN duas_master dm ON de.dua_id = dm.id 
                         GROUP BY de.user_id, dm.category";
    $overall_logs_res = $conn->query($sql_overall_logs);
    $user_spiritual_data = [];
    while ($row = $overall_logs_res->fetch_assoc()) {
        $user_spiritual_data[$row['user_id']][$row['category']] = (int)$row['total_count'];
    }

    // Fetch Quran completed juz
    $sql_quran = "SELECT user_id, COUNT(DISTINCT CONCAT(quran_number, '-', juz_number)) as completed_juz 
                  FROM quran_progress 
                  WHERE is_completed = 1 
                  GROUP BY user_id";
    $quran_res = $conn->query($sql_quran);
    $user_quran_data = [];
    while ($row = $quran_res->fetch_assoc()) {
        $user_quran_data[$row['user_id']] = (int)$row['completed_juz'];
    }

    // Fetch Books completed
    $sql_books_done = "SELECT user_id, COUNT(DISTINCT book_id) as completed_books 
                       FROM book_transcription 
                       WHERE status = 'completed' 
                       GROUP BY user_id";
    $books_res = $conn->query($sql_books_done);
    $user_books_data = [];
    while ($row = $books_res->fetch_assoc()) {
        $user_books_data[$row['user_id']] = (int)$row['completed_books'];
    }

    // Fetch Ziyarat visits
    $sql_ziyarat_done = "SELECT user_id, SUM(count_added) as total_visits 
                         FROM ziyarat_entries 
                         GROUP BY user_id";
    $ziyarat_res = $conn->query($sql_ziyarat_done);
    $user_ziyarat_data = [];
    while ($row = $ziyarat_res->fetch_assoc()) {
        $user_ziyarat_data[$row['user_id']] = (int)$row['total_visits'];
    }

    // Compile overall progress
    foreach ($users as $user) {
        $u_id = $user['user_id'];
        $quran_juz = isset($user_quran_data[$u_id]) ? $user_quran_data[$u_id] : 0;
        $books = isset($user_books_data[$u_id]) ? $user_books_data[$u_id] : 0;
        $visits = isset($user_ziyarat_data[$u_id]) ? $user_ziyarat_data[$u_id] : 0;
        
        $sp = isset($user_spiritual_data[$u_id]) ? $user_spiritual_data[$u_id] : ['dua' => 0, 'tasbeeh' => 0, 'namaz' => 0];
        $dua_count = isset($sp['dua']) ? $sp['dua'] : 0;
        $tasbeeh_count = isset($sp['tasbeeh']) ? $sp['tasbeeh'] : 0;
        $namaz_count = isset($sp['namaz']) ? $sp['namaz'] : 0;

        $quran_pct = round(($quran_juz / 120) * 100, 2);
        $dua_pct = $category_targets['dua'] > 0 ? round(($dua_count / $category_targets['dua']) * 100, 2) : 0;
        $tasbeeh_pct = $category_targets['tasbeeh'] > 0 ? round(($tasbeeh_count / $category_targets['tasbeeh']) * 100, 2) : 0;
        $namaz_pct = $category_targets['namaz'] > 0 ? round(($namaz_count / $category_targets['namaz']) * 100, 2) : 0;
        
        $overall_progress = round(($quran_pct + $dua_pct + $tasbeeh_pct + $namaz_pct) / 4, 2);

        $user['completed_juz'] = $quran_juz;
        $user['books_completed'] = $books;
        $user['ziyarat_visits'] = $visits;
        $user['dua_count'] = $dua_count;
        $user['tasbeeh_count'] = $tasbeeh_count;
        $user['namaz_count'] = $namaz_count;
        $user['overall_progress'] = $overall_progress;

        $report_users[] = $user;
    }
} else if (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])) {
    // Specific Duas/Tasbeehs/Namaz report
    $item_filter_sql = "";
    $master_params = [$report_type];
    $master_types = "s";
    
    if (!empty($selected_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
        $item_filter_sql = " AND id IN ($placeholders)";
        foreach ($selected_item_ids as $id) {
            $master_params[] = $id;
            $master_types .= "i";
        }
    }
    
    $sql_items = "SELECT id, dua_name, dua_name_arabic, target_count 
                   FROM duas_master 
                   WHERE category = ? AND is_active = 1" . $item_filter_sql . " 
                   ORDER BY display_order ASC, dua_name ASC";
    
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param($master_types, ...$master_params);
    $stmt_items->execute();
    $report_columns = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch user recitation details for these items
    $sql_entries = "SELECT de.user_id, de.dua_id, SUM(de.count_added) as total_recited 
                    FROM dua_entries de 
                    JOIN users u ON de.user_id = u.id 
                    WHERE de.dua_id IN (
                        SELECT id FROM duas_master WHERE category = ? AND is_active = 1" . $item_filter_sql . "
                    )";
    if ($selected_branch) {
        $sql_entries .= " AND u.category = ?";
    }
    $sql_entries .= " GROUP BY de.user_id, de.dua_id";

    $entries_params = [$report_type];
    $entries_types = "s";
    if (!empty($selected_item_ids)) {
        foreach ($selected_item_ids as $id) {
            $entries_params[] = $id;
            $entries_types .= "i";
        }
    }
    if ($selected_branch) {
        $entries_params[] = $selected_branch;
        $entries_types .= "s";
    }

    $stmt_entries = $conn->prepare($sql_entries);
    $stmt_entries->bind_param($entries_types, ...$entries_params);
    $stmt_entries->execute();
    $entries_res = $stmt_entries->get_result();

    while ($row = $entries_res->fetch_assoc()) {
        $report_matrix[$row['user_id']][$row['dua_id']] = (int)$row['total_recited'];
    }

    // Process progress for each user
    foreach ($users as $user) {
        $u_id = $user['user_id'];
        $total_recited = 0;
        $total_target = 0;

        foreach ($report_columns as $col) {
            $col_id = $col['id'];
            $target = (int)$col['target_count'];
            $recited = isset($report_matrix[$u_id][$col_id]) ? $report_matrix[$u_id][$col_id] : 0;
            
            $total_recited += $recited;
            $total_target += $target;
        }

        $overall_progress = $total_target > 0 ? round(($total_recited / $total_target) * 100, 2) : 0;
        $user['total_recited'] = $total_recited;
        $user['total_target'] = $total_target;
        $user['overall_progress'] = $overall_progress;

        $report_users[] = $user;
    }
} else if ($report_type === 'quran') {
    // Quran detailed progress
    $sql_quran = "SELECT user_id, COUNT(DISTINCT CONCAT(quran_number, '-', juz_number)) as completed_juz 
                  FROM quran_progress 
                  WHERE is_completed = 1 
                  GROUP BY user_id";
    $quran_res = $conn->query($sql_quran);
    $user_quran_data = [];
    while ($row = $quran_res->fetch_assoc()) {
        $user_quran_data[$row['user_id']] = (int)$row['completed_juz'];
    }

    foreach ($users as $user) {
        $u_id = $user['user_id'];
        $completed_juz = isset($user_quran_data[$u_id]) ? $user_quran_data[$u_id] : 0;
        $completed_qurans = floor($completed_juz / 30);
        $overall_progress = round(($completed_juz / 120) * 100, 2);

        $user['completed_juz'] = $completed_juz;
        $user['completed_qurans'] = $completed_qurans;
        $user['overall_progress'] = $overall_progress;

        $report_users[] = $user;
    }
} else if ($report_type === 'book') {
    // Book transcription details
    $book_filter_sql = "";
    $book_params = [];
    $book_types = "";
    
    if (!empty($selected_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
        $book_filter_sql = " WHERE id IN ($placeholders)";
        foreach ($selected_item_ids as $id) {
            $book_params[] = $id;
            $book_types .= "i";
        }
    }
    
    $sql_books = "SELECT id, book_name, total_pages FROM books_master" . $book_filter_sql . " ORDER BY display_order ASC, book_name ASC";
    $stmt_books = $conn->prepare($sql_books);
    if (!empty($book_params)) {
        $stmt_books->bind_param($book_types, ...$book_params);
    }
    $stmt_books->execute();
    $report_columns = $stmt_books->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch progress
    $sql_progress = "SELECT bt.user_id, bt.book_id, bt.pages_completed, bt.status 
                     FROM book_transcription bt 
                     JOIN users u ON bt.user_id = u.id";
    $progress_where = [];
    $progress_params = [];
    $progress_types = "";
    
    if (!empty($selected_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
        $progress_where[] = "bt.book_id IN ($placeholders)";
        foreach ($selected_item_ids as $id) {
            $progress_params[] = $id;
            $progress_types .= "i";
        }
    }
    if ($selected_branch) {
        $progress_where[] = "u.category = ?";
        $progress_params[] = $selected_branch;
        $progress_types .= "s";
    }
    
    if (!empty($progress_where)) {
        $sql_progress .= " WHERE " . implode(" AND ", $progress_where);
    }
    
    $stmt_prog = $conn->prepare($sql_progress);
    if (!empty($progress_params)) {
        $stmt_prog->bind_param($progress_types, ...$progress_params);
    }
    $stmt_prog->execute();
    $progress_res = $stmt_prog->get_result();
    
    while ($row = $progress_res->fetch_assoc()) {
        $report_matrix[$row['user_id']][$row['book_id']] = [
            'pages' => (int)$row['pages_completed'],
            'status' => $row['status']
        ];
    }

    foreach ($users as $user) {
        $u_id = $user['user_id'];
        $completed_books = 0;
        $total_books_tracked = count($report_columns);

        foreach ($report_columns as $col) {
            $col_id = $col['id'];
            if (isset($report_matrix[$u_id][$col_id]) && $report_matrix[$u_id][$col_id]['status'] === 'completed') {
                $completed_books++;
            }
        }

        $overall_progress = $total_books_tracked > 0 ? round(($completed_books / $total_books_tracked) * 100, 2) : 0;
        $user['completed_books'] = $completed_books;
        $user['overall_progress'] = $overall_progress;

        $report_users[] = $user;
    }
} else if ($report_type === 'ziyarat') {
    // Ziyarat details
    $mazar_filter_sql = "";
    $mazar_params = [];
    $mazar_types = "";
    
    if (!empty($selected_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
        $mazar_filter_sql = " WHERE id IN ($placeholders)";
        foreach ($selected_item_ids as $id) {
            $mazar_params[] = $id;
            $mazar_types .= "i";
        }
    }
    
    $sql_mazars = "SELECT id, mazar_name FROM mazars_master" . $mazar_filter_sql . " ORDER BY display_order ASC, mazar_name ASC";
    $stmt_mazars = $conn->prepare($sql_mazars);
    if (!empty($mazar_params)) {
        $stmt_mazars->bind_param($mazar_types, ...$mazar_params);
    }
    $stmt_mazars->execute();
    $report_columns = $stmt_mazars->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch visits
    $sql_visits = "SELECT ze.user_id, ze.mazar_id, SUM(ze.count_added) as total_visits 
                   FROM ziyarat_entries ze 
                   JOIN users u ON ze.user_id = u.id";
    $visits_where = [];
    $visits_params = [];
    $visits_types = "";
    
    if (!empty($selected_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
        $visits_where[] = "ze.mazar_id IN ($placeholders)";
        foreach ($selected_item_ids as $id) {
            $visits_params[] = $id;
            $visits_types .= "i";
        }
    }
    if ($selected_branch) {
        $visits_where[] = "u.category = ?";
        $visits_params[] = $selected_branch;
        $visits_types .= "s";
    }
    
    if (!empty($visits_where)) {
        $sql_visits .= " WHERE " . implode(" AND ", $visits_where);
    }
    $sql_visits .= " GROUP BY ze.user_id, ze.mazar_id";
    
    $stmt_visits = $conn->prepare($sql_visits);
    if (!empty($visits_params)) {
        $stmt_visits->bind_param($visits_types, ...$visits_params);
    }
    $stmt_visits->execute();
    $visits_res = $stmt_visits->get_result();
    
    while ($row = $visits_res->fetch_assoc()) {
        $report_matrix[$row['user_id']][$row['mazar_id']] = (int)$row['total_visits'];
    }

    foreach ($users as $user) {
        $u_id = $user['user_id'];
        $total_visits = 0;

        foreach ($report_columns as $col) {
            $col_id = $col['id'];
            $visits = isset($report_matrix[$u_id][$col_id]) ? $report_matrix[$u_id][$col_id] : 0;
            $total_visits += $visits;
        }

        $user['total_visits'] = $total_visits;
        $user['overall_progress'] = $total_visits; // For ziyarat, we flag by total counts

        $report_users[] = $user;
    }
}

// 5. Handle Excel Export Request
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Amali_Advanced_Report_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=" . $filename);
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Output simplified spreadsheet HTML
    ?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset="utf-8">
        <style>
            table { border-collapse: collapse; }
            th { background-color: #243b53; color: #ffffff; border: 1px solid #cbd5e1; font-weight: bold; }
            td { border: 1px solid #cbd5e1; }
            .flagged { color: #dc2626; font-weight: bold; background-color: #fee2e2; }
        </style>
    </head>
    <body>
        <h2>Amali Janib Advanced Report (<?php echo ucfirst($report_type); ?>)</h2>
        <p><strong>Scope:</strong> <?php echo $selected_branch ?: 'All Branches'; ?> | <strong>Threshold:</strong> <?php echo $threshold; ?>%</p>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ITS ID</th>
                    <th>Branch</th>
                    <th>Classification</th>
                    <?php if ($report_type === 'overall'): ?>
                        <th>Quran (Juz)</th>
                        <th>Duas</th>
                        <th>Tasbeeh</th>
                        <th>Namaz</th>
                        <th>Kutub Completed</th>
                        <th>Ziyarat Visits</th>
                        <th>Overall Progress</th>
                    <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                        <?php foreach ($report_columns as $col): ?>
                            <th><?php echo htmlspecialchars($col['dua_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Recited</th>
                        <th>Progress</th>
                    <?php elseif ($report_type === 'quran'): ?>
                        <th>Qurans Completed</th>
                        <th>Juz Completed</th>
                        <th>Quran Progress</th>
                    <?php elseif ($report_type === 'book'): ?>
                        <?php foreach ($report_columns as $col): ?>
                            <th><?php echo htmlspecialchars($col['book_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Books Completed</th>
                        <th>Progress</th>
                    <?php elseif ($report_type === 'ziyarat'): ?>
                        <?php foreach ($report_columns as $col): ?>
                            <th><?php echo htmlspecialchars($col['mazar_name']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Visits</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_users as $user): 
                    $is_flagged = ($user['overall_progress'] < $threshold);
                    $cell_style = $is_flagged ? 'class="flagged"' : '';
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['its_number']); ?></td>
                        <td><?php echo htmlspecialchars($user['category'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['classification'] ?: 'N/A'); ?></td>
                        
                        <?php if ($report_type === 'overall'): ?>
                            <td><?php echo $user['completed_juz']; ?> Juz</td>
                            <td><?php echo $user['dua_count']; ?></td>
                            <td><?php echo $user['tasbeeh_count']; ?></td>
                            <td><?php echo $user['namaz_count']; ?></td>
                            <td><?php echo $user['books_completed']; ?></td>
                            <td><?php echo $user['ziyarat_visits']; ?></td>
                            <td <?php echo $cell_style; ?>><?php echo number_format($user['overall_progress'], 2); ?>%</td>
                        
                        <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                            <?php foreach ($report_columns as $col): 
                                $rec = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']] : 0;
                            ?>
                                <td><?php echo $rec; ?> / <?php echo $col['target_count']; ?></td>
                            <?php endforeach; ?>
                            <td><?php echo $user['total_recited']; ?> / <?php echo $user['total_target']; ?></td>
                            <td <?php echo $cell_style; ?>><?php echo number_format($user['overall_progress'], 2); ?>%</td>
                        
                        <?php elseif ($report_type === 'quran'): ?>
                            <td><?php echo $user['completed_qurans']; ?></td>
                            <td><?php echo $user['completed_juz']; ?> Juz</td>
                            <td <?php echo $cell_style; ?>><?php echo number_format($user['overall_progress'], 2); ?>%</td>
                        
                        <?php elseif ($report_type === 'book'): ?>
                            <?php foreach ($report_columns as $col): 
                                $stat = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']]['pages'] : 0;
                            ?>
                                <td><?php echo $stat; ?> / <?php echo $col['total_pages']; ?></td>
                            <?php endforeach; ?>
                            <td><?php echo $user['completed_books']; ?></td>
                            <td <?php echo $cell_style; ?>><?php echo number_format($user['overall_progress'], 2); ?>%</td>
                        
                        <?php elseif ($report_type === 'ziyarat'): ?>
                            <?php foreach ($report_columns as $col): 
                                $vis = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']] : 0;
                            ?>
                                <td><?php echo $vis; ?></td>
                            <?php endforeach; ?>
                            <td <?php echo $cell_style; ?>><?php echo $user['total_visits']; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// 6. Normal Page Loading
$page_title = 'Advanced Reports';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

require_once '../includes/header.php';
?>

<!-- Load jQuery and Select2 for premium multi-select dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Premium Select2 Styling Overrides */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--border-color) !important;
        background-color: white !important;
        border-radius: var(--radius-md) !important;
        min-height: 42px !important;
        padding: 4px 10px !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--primary-500) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: var(--primary-50) !important;
        border: 1px solid var(--primary-200) !important;
        color: var(--primary-800) !important;
        border-radius: var(--radius-sm) !important;
        font-weight: 600 !important;
        padding: 2px 8px !important;
        font-size: 0.85rem !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--primary-600) !important;
        margin-right: 6px !important;
        border-right: 1px solid var(--primary-200) !important;
        padding-right: 4px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: var(--danger) !important;
        background: none !important;
    }

    /* Table & Exception Row Highlight styling */
    .table-container {
        overflow-x: auto;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        background: white;
        margin-top: 20px;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .report-table th {
        background-color: var(--primary-500);
        color: white;
        padding: var(--spacing-md);
        font-weight: 600;
        font-size: 0.85rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        vertical-align: middle;
    }

    .report-table td {
        padding: var(--spacing-md);
        border-bottom: 1px solid rgba(218, 165, 32, 0.15);
        font-size: 0.875rem;
        color: var(--text-primary);
        vertical-align: middle;
    }

    .report-table tbody tr:hover {
        background-color: var(--bg-primary) !important;
    }

    .flagged-row {
        background-color: #fee2e2 !important; /* Soft Red */
    }

    .flagged-row td {
        color: #991b1b !important;
        border-bottom-color: #fca5a5 !important;
    }

    .flagged-row strong {
        color: #b91c1c !important;
    }

    .badge-flagged {
        background-color: #ef4444 !important;
        color: white !important;
        font-weight: 700;
    }

    /* Print Formatting */
    @media print {
        .sidebar, .topbar, .footer, .card-filters, .action-buttons-container {
            display: none !important;
        }
        .main-wrapper {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        .main-content {
            padding: 0 !important;
        }
        .table-container {
            border: none !important;
        }
        .report-table th {
            background-color: #243b53 !important;
            color: white !important;
            border: 1px solid #cbd5e1 !important;
        }
        .report-table td {
            border: 1px solid #cbd5e1 !important;
        }
        .container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
        }
    }
</style>

<div class="container">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1><i class="fas fa-file-invoice"></i> Enterprise Advanced Reports</h1>
            <p>Generate, filter, and analyze highly specific Amali Janib reports directly on page.</p>
        </div>
        <div class="action-buttons-container" style="display: flex; gap: 0.5rem;">
            <a href="amali_reports.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Amali Reports
            </a>
        </div>
    </div>

    <!-- Filter Console -->
    <div class="card card-filters">
        <div class="card-header" style="background-color: var(--bg-tertiary);">
            <h3><i class="fas fa-sliders"></i> Report Filters</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <form method="GET" action="advanced_reports.php" id="reportFilterForm">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">
                    
                    <!-- Branch Selector -->
                    <div class="form-group">
                        <label style="font-weight: 600; color: var(--text-secondary);">
                            <i class="fas fa-building-user"></i> Select Branch (Category)
                        </label>
                        <select name="branch" class="form-control" <?php echo $is_category_coordinator ? 'disabled' : ''; ?>>
                            <?php if (!$is_category_coordinator): ?>
                                <option value="">All Branches</option>
                            <?php endif; ?>
                            <option value="Surat" <?php echo $selected_branch === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                            <option value="Marol" <?php echo $selected_branch === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                            <option value="Karachi" <?php echo $selected_branch === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                            <option value="Nairobi" <?php echo $selected_branch === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                            <option value="Muntasib" <?php echo $selected_branch === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                        </select>
                        <?php if ($is_category_coordinator): ?>
                            <!-- Pass disabled value via hidden input so it is submitted -->
                            <input type="hidden" name="branch" value="<?php echo htmlspecialchars($selected_branch); ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Report Type Selector -->
                    <div class="form-group">
                        <label style="font-weight: 600; color: var(--text-secondary);">
                            <i class="fas fa-chart-simple"></i> Report Type
                        </label>
                        <select name="report_type" id="report_type" class="form-control" onchange="handleReportTypeChange()">
                            <option value="overall" <?php echo $report_type === 'overall' ? 'selected' : ''; ?>>Overall Amali Progress</option>
                            <option value="dua" <?php echo $report_type === 'dua' ? 'selected' : ''; ?>>Dua Recitations</option>
                            <option value="tasbeeh" <?php echo $report_type === 'tasbeeh' ? 'selected' : ''; ?>>Tasbeeh Count</option>
                            <option value="namaz" <?php echo $report_type === 'namaz' ? 'selected' : ''; ?>>Namaz Count</option>
                            <option value="quran" <?php echo $report_type === 'quran' ? 'selected' : ''; ?>>Quran Progress</option>
                            <option value="book" <?php echo $report_type === 'book' ? 'selected' : ''; ?>>Book Transcription</option>
                            <option value="ziyarat" <?php echo $report_type === 'ziyarat' ? 'selected' : ''; ?>>Ziyarat Visits</option>
                        </select>
                    </div>

                    <!-- Exception Threshold -->
                    <div class="form-group">
                        <label style="font-weight: 600; color: var(--text-secondary);">
                            <i class="fas fa-circle-exclamation"></i> Flag Progress Below (%)
                        </label>
                        <input type="number" name="threshold" class="form-control" value="<?php echo htmlspecialchars($threshold); ?>" step="0.1" min="0" max="100">
                        <small style="color: #64748b;">Highlights users below this target limit.</small>
                    </div>
                </div>

                <!-- Dynamic Multi-Select Groups (Named item_ids[] but selectively enabled) -->
                
                <!-- 1. Dua Items Group -->
                <div class="form-group" id="dua_items_group" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; color: var(--text-secondary);">
                        <i class="fas fa-hands-praying"></i> Choose Specific Duas (Optional)
                    </label>
                    <select name="item_ids[]" multiple class="form-control select2" style="width: 100%;" placeholder="Search and choose Duas...">
                        <?php foreach ($duas_list as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (in_array($item['id'], $selected_item_ids) && $report_type === 'dua') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['dua_name']); ?> (Target: <?php echo $item['target_count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Tasbeeh Items Group -->
                <div class="form-group" id="tasbeeh_items_group" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; color: var(--text-secondary);">
                        <i class="fas fa-dharmachakra"></i> Choose Specific Tasbeehs (Optional)
                    </label>
                    <select name="item_ids[]" multiple class="form-control select2" style="width: 100%;" placeholder="Search and choose Tasbeehs...">
                        <?php foreach ($tasbeeh_list as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (in_array($item['id'], $selected_item_ids) && $report_type === 'tasbeeh') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['dua_name']); ?> (Target: <?php echo $item['target_count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Namaz Items Group -->
                <div class="form-group" id="namaz_items_group" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; color: var(--text-secondary);">
                        <i class="fas fa-mosque"></i> Choose Specific Namaz (Optional)
                    </label>
                    <select name="item_ids[]" multiple class="form-control select2" style="width: 100%;" placeholder="Search and choose Namaz...">
                        <?php foreach ($namaz_list as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (in_array($item['id'], $selected_item_ids) && $report_type === 'namaz') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['dua_name']); ?> (Target: <?php echo $item['target_count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Book Items Group -->
                <div class="form-group" id="book_items_group" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; color: var(--text-secondary);">
                        <i class="fas fa-book"></i> Choose Specific Books (Optional)
                    </label>
                    <select name="item_ids[]" multiple class="form-control select2" style="width: 100%;" placeholder="Search and choose Books...">
                        <?php foreach ($books_list as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (in_array($item['id'], $selected_item_ids) && $report_type === 'book') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['book_name']); ?> (Total Pages: <?php echo $item['total_pages']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. Ziyarat Items Group -->
                <div class="form-group" id="ziyarat_items_group" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; color: var(--text-secondary);">
                        <i class="fas fa-kaaba"></i> Choose Specific Mazars (Optional)
                    </label>
                    <select name="item_ids[]" multiple class="form-control select2" style="width: 100%;" placeholder="Search and choose Mazars...">
                        <?php foreach ($mazars_list as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (in_array($item['id'], $selected_item_ids) && $report_type === 'ziyarat') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['mazar_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="background-color: var(--primary-500); border-color: var(--primary-500);">
                        <i class="fas fa-sync"></i> Generate Report
                    </button>
                    <a href="advanced_reports.php" class="btn btn-secondary">
                        <i class="fas fa-trash-can"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Generated Report Output -->
    <div class="card" style="margin-top: 25px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="margin: 0; color: var(--primary-600);">
                    <i class="fas fa-file-lines"></i> Generated Results: 
                    <?php 
                        $type_labels = [
                            'overall' => 'Overall Amali Progress',
                            'dua' => 'Dua Recitation Progress Matrix',
                            'tasbeeh' => 'Tasbeeh Count Progress Matrix',
                            'namaz' => 'Namaz Count Progress Matrix',
                            'quran' => 'Quran Progress Overview',
                            'book' => 'Book Transcription Tracker',
                            'ziyarat' => 'Ziyarat Visit Matrix'
                        ];
                        echo isset($type_labels[$report_type]) ? $type_labels[$report_type] : 'Report';
                    ?>
                </h3>
                <small style="color: var(--text-secondary);">
                    Branch: <strong><?php echo $selected_branch ?: 'All Branches'; ?></strong> | 
                    Exception Limit: <strong><?php echo $threshold; ?>%</strong>
                </small>
            </div>
            
            <div class="action-buttons-container" style="display: flex; gap: 0.5rem;">
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success" style="background-color: #16a34a; border-color: #16a34a;">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>

        <div style="padding: 1rem;">
            <?php if (empty($report_users)): ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 3rem 0;">No active users found matching selected filters.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>ITS ID</th>
                                <th>Branch</th>
                                <th>Classification</th>
                                
                                <?php if ($report_type === 'overall'): ?>
                                    <th>Quran (Juz)</th>
                                    <th>Duas</th>
                                    <th>Tasbeeh</th>
                                    <th>Namaz</th>
                                    <th>Kutub Completed</th>
                                    <th>Ziyarat Visits</th>
                                    <th>Overall Progress</th>
                                
                                <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                                    <?php foreach ($report_columns as $col): ?>
                                        <th style="font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($col['dua_name']); ?><br>
                                            <small style="color: rgba(255,255,255,0.8);">(Target: <?php echo $col['target_count']; ?>)</small>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>Total Recited</th>
                                    <th>Progress</th>
                                
                                <?php elseif ($report_type === 'quran'): ?>
                                    <th>Qurans Completed</th>
                                    <th>Juz Completed</th>
                                    <th>Quran Progress</th>
                                
                                <?php elseif ($report_type === 'book'): ?>
                                    <?php foreach ($report_columns as $col): ?>
                                        <th style="font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($col['book_name']); ?><br>
                                            <small style="color: rgba(255,255,255,0.8);">(Pages: <?php echo $col['total_pages']; ?>)</small>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>Books Completed</th>
                                    <th>Progress</th>
                                
                                <?php elseif ($report_type === 'ziyarat'): ?>
                                    <?php foreach ($report_columns as $col): ?>
                                        <th style="font-size: 0.8rem;"><?php echo htmlspecialchars($col['mazar_name']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Total Visits</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_users as $user): 
                                $is_flagged = ($user['overall_progress'] < $threshold);
                                $row_class = $is_flagged ? 'flagged-row' : '';
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($user['its_number']); ?></code></td>
                                    <td><?php echo htmlspecialchars($user['category'] ?: 'N/A'); ?></td>
                                    <td><span class="badge" style="background-color: var(--primary-50); color: var(--primary-700); font-weight: 600;"><?php echo htmlspecialchars($user['classification'] ?: 'N/A'); ?></span></td>
                                    
                                    <?php if ($report_type === 'overall'): ?>
                                        <td><?php echo $user['completed_juz']; ?> / 120 Juz</td>
                                        <td><?php echo number_format($user['dua_count']); ?></td>
                                        <td><?php echo number_format($user['tasbeeh_count']); ?></td>
                                        <td><?php echo number_format($user['namaz_count']); ?></td>
                                        <td><?php echo $user['books_completed']; ?></td>
                                        <td><?php echo number_format($user['ziyarat_visits']); ?></td>
                                        <td style="text-align: right; font-weight: 700;">
                                            <span class="badge <?php echo $is_flagged ? 'badge-flagged' : ''; ?>" style="font-size: 0.875rem; padding: 4px 8px; border-radius: var(--radius-sm);">
                                                <?php echo number_format($user['overall_progress'], 2); ?>%
                                            </span>
                                        </td>
                                    
                                    <?php elseif (in_array($report_type, ['dua', 'tasbeeh', 'namaz'])): ?>
                                        <?php foreach ($report_columns as $col): 
                                            $rec = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']] : 0;
                                            $pct = $col['target_count'] > 0 ? round(($rec / $col['target_count']) * 100, 1) : 0;
                                        ?>
                                            <td>
                                                <strong><?php echo $rec; ?></strong><br>
                                                <small style="color: #64748b;"><?php echo $pct; ?>%</small>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><strong><?php echo number_format($user['total_recited']); ?></strong> / <?php echo number_format($user['total_target']); ?></td>
                                        <td style="text-align: right; font-weight: 700;">
                                            <span class="badge <?php echo $is_flagged ? 'badge-flagged' : ''; ?>" style="font-size: 0.875rem; padding: 4px 8px; border-radius: var(--radius-sm);">
                                                <?php echo number_format($user['overall_progress'], 2); ?>%
                                            </span>
                                        </td>
                                    
                                    <?php elseif ($report_type === 'quran'): ?>
                                        <td><?php echo $user['completed_qurans']; ?> Qurans</td>
                                        <td><?php echo $user['completed_juz']; ?> Juz</td>
                                        <td style="text-align: right; font-weight: 700;">
                                            <span class="badge <?php echo $is_flagged ? 'badge-flagged' : ''; ?>" style="font-size: 0.875rem; padding: 4px 8px; border-radius: var(--radius-sm);">
                                                <?php echo number_format($user['overall_progress'], 2); ?>%
                                            </span>
                                        </td>
                                    
                                    <?php elseif ($report_type === 'book'): ?>
                                        <?php foreach ($report_columns as $col): 
                                            $pages = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']]['pages'] : 0;
                                            $status = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']]['status'] : 'not selected';
                                            $badge_type = $status === 'completed' ? 'success' : ($status === 'selected' ? 'warning' : 'secondary');
                                        ?>
                                            <td>
                                                <strong><?php echo $pages; ?></strong> / <?php echo $col['total_pages']; ?><br>
                                                <span class="badge badge-<?php echo $badge_type; ?>" style="font-size: 0.65rem; padding: 2px 4px;"><?php echo ucfirst($status); ?></span>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?php echo $user['completed_books']; ?> / <?php echo $total_books_tracked; ?></td>
                                        <td style="text-align: right; font-weight: 700;">
                                            <span class="badge <?php echo $is_flagged ? 'badge-flagged' : ''; ?>" style="font-size: 0.875rem; padding: 4px 8px; border-radius: var(--radius-sm);">
                                                <?php echo number_format($user['overall_progress'], 2); ?>%
                                            </span>
                                        </td>
                                    
                                    <?php elseif ($report_type === 'ziyarat'): ?>
                                        <?php foreach ($report_columns as $col): 
                                            $vis = isset($report_matrix[$user['user_id']][$col['id']]) ? $report_matrix[$user['user_id']][$col['id']] : 0;
                                        ?>
                                            <td><strong><?php echo number_format($vis); ?></strong></td>
                                        <?php endforeach; ?>
                                        <td style="text-align: right; font-weight: 700;">
                                            <span class="badge <?php echo $is_flagged ? 'badge-flagged' : ''; ?>" style="font-size: 0.875rem; padding: 4px 8px; border-radius: var(--radius-sm);">
                                                <?php echo number_format($user['total_visits']); ?> visits
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize premium searchable dropdowns
    $('.select2').select2({
        placeholder: "Search and choose specific items...",
        allowClear: true
    });

    // Handle form type showing/hiding
    handleReportTypeChange();
});

function handleReportTypeChange() {
    const reportType = document.getElementById('report_type').value;
    
    // Hide all dynamic item containers
    document.getElementById('dua_items_group').style.display = 'none';
    document.getElementById('tasbeeh_items_group').style.display = 'none';
    document.getElementById('namaz_items_group').style.display = 'none';
    document.getElementById('book_items_group').style.display = 'none';
    document.getElementById('ziyarat_items_group').style.display = 'none';
    
    // Disable hidden inputs so they are not submitted in GET request
    disableContainerInputs('dua_items_group', true);
    disableContainerInputs('tasbeeh_items_group', true);
    disableContainerInputs('namaz_items_group', true);
    disableContainerInputs('book_items_group', true);
    disableContainerInputs('ziyarat_items_group', true);
    
    // Enable and show active container
    if (reportType === 'dua') {
        document.getElementById('dua_items_group').style.display = 'block';
        disableContainerInputs('dua_items_group', false);
    } else if (reportType === 'tasbeeh') {
        document.getElementById('tasbeeh_items_group').style.display = 'block';
        disableContainerInputs('tasbeeh_items_group', false);
    } else if (reportType === 'namaz') {
        document.getElementById('namaz_items_group').style.display = 'block';
        disableContainerInputs('namaz_items_group', false);
    } else if (reportType === 'book') {
        document.getElementById('book_items_group').style.display = 'block';
        disableContainerInputs('book_items_group', false);
    } else if (reportType === 'ziyarat') {
        document.getElementById('ziyarat_items_group').style.display = 'block';
        disableContainerInputs('ziyarat_items_group', false);
    }
}

function disableContainerInputs(containerId, disable) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const selects = container.getElementsByTagName('select');
    for (let i = 0; i < selects.length; i++) {
        selects[i].disabled = disable;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
