<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Secure endpoint - require admin credentials
require_admin();

// Validate action type
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$valid_types = ['dua', 'tasbeeh', 'namaz', 'ziyarat'];

if (!in_array($type, $valid_types)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid stats category requested.']);
    exit;
}

// Determine coordinator and category/branch scope
$is_category_coordinator = is_category_amali_coordinator();
$assigned_category = get_assigned_category();

// Build database filters matching admin/index.php logic
$where_sql_users = " WHERE (role = 'user' OR role = 'admin') AND its_number NOT LIKE '000000%'";
$where_sql_data = " WHERE (u.role = 'user' OR u.role = 'admin' OR u.role = 'system' OR u.category = 'system')";

$params_users = [];
$params_data = [];
if ($is_category_coordinator && $assigned_category) {
    $where_sql_users .= " AND category = ?";
    $params_users[] = $assigned_category;
    
    $where_sql_data .= " AND u.category = ?";
    $params_data[] = $assigned_category;
}

// 1. Get total users in the active scope
$sql_total = "SELECT COUNT(*) as total FROM users" . $where_sql_users;
if (!empty($params_users)) {
    $stmt = $conn->prepare($sql_total);
    $stmt->bind_param("s", $params_users[0]);
    $stmt->execute();
    $total_users = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_users = (int)$conn->query($sql_total)->fetch_assoc()['total'];
}

$items = [];

// 2. Fetch details depending on type
if ($type === 'ziyarat') {
    // Ziyarat has no targets - just list Mazars and sum the visits
    $sql_master = "SELECT id, mazar_name FROM mazars_master WHERE is_active = 1 ORDER BY display_order ASC, mazar_name ASC";
    $master_res = $conn->query($sql_master);
    $master_items = [];
    while ($row = $master_res->fetch_assoc()) {
        $master_items[] = $row;
    }

    $ziyarat_map = [];
    if (!empty($params_data)) {
        // Branch coordinator
        $sql_ziyarat = "SELECT ze.mazar_id, COALESCE(SUM(ze.count_added), 0) as total_count 
                        FROM ziyarat_entries ze 
                        JOIN users u ON ze.user_id = u.id 
                        WHERE u.category = ? 
                        GROUP BY ze.mazar_id";
        $stmt = $conn->prepare($sql_ziyarat);
        $stmt->bind_param("s", $params_data[0]);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ziyarat_map[$row['mazar_id']] = (int)$row['total_count'];
        }
    } else {
        // Super admin (global scope)
        $sql_ziyarat = "SELECT ze.mazar_id, COALESCE(SUM(ze.count_added), 0) as total_count 
                        FROM ziyarat_entries ze 
                        LEFT JOIN users u ON ze.user_id = u.id 
                        WHERE (u.id IS NULL OR u.role = 'user' OR u.role = 'admin' OR u.role = 'system' OR u.category = 'system')
                        GROUP BY ze.mazar_id";
        $res = $conn->query($sql_ziyarat);
        while ($row = $res->fetch_assoc()) {
            $ziyarat_map[$row['mazar_id']] = (int)$row['total_count'];
        }
    }

    foreach ($master_items as $item) {
        $id = (int)$item['id'];
        $completed = isset($ziyarat_map[$id]) ? $ziyarat_map[$id] : 0;
        $items[] = [
            'id' => $id,
            'name' => $item['mazar_name'],
            'completed' => $completed
        ];
    }
} else {
    // Dua, Tasbeeh, or Namaz (with targets)
    $sql_master = "SELECT id, dua_name, dua_name_arabic, target_count 
                   FROM duas_master 
                   WHERE category = ? AND is_active = 1 
                   ORDER BY display_order ASC, dua_name ASC";
    $stmt = $conn->prepare($sql_master);
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $master_res = $stmt->get_result();
    $master_items = [];
    while ($row = $master_res->fetch_assoc()) {
        $master_items[] = $row;
    }

    $progress_map = [];
    if (!empty($params_data)) {
        // Branch coordinator
        $sql_progress = "SELECT de.dua_id, COALESCE(SUM(de.count_added), 0) as total_progress 
                         FROM dua_entries de 
                         JOIN users u ON de.user_id = u.id 
                         WHERE u.category = ? 
                         GROUP BY de.dua_id";
        $stmt = $conn->prepare($sql_progress);
        $stmt->bind_param("s", $params_data[0]);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $progress_map[$row['dua_id']] = (int)$row['total_progress'];
        }
    } else {
        // Super admin (global scope) - includes everyone and orphans
        $sql_progress = "SELECT de.dua_id, COALESCE(SUM(de.count_added), 0) as total_progress 
                         FROM dua_entries de 
                         LEFT JOIN users u ON de.user_id = u.id 
                         WHERE (u.id IS NULL OR u.role = 'user' OR u.role = 'admin' OR u.role = 'system' OR u.category = 'system')
                         GROUP BY de.dua_id";
        $res = $conn->query($sql_progress);
        while ($row = $res->fetch_assoc()) {
            $progress_map[$row['dua_id']] = (int)$row['total_progress'];
        }
    }

    foreach ($master_items as $item) {
        $id = (int)$item['id'];
        $target_single = (int)$item['target_count'];
        $target_total = $target_single * $total_users;
        $completed = isset($progress_map[$id]) ? $progress_map[$id] : 0;
        $percentage = $target_total > 0 ? round(($completed / $target_total) * 100, 2) : 0;
        
        $items[] = [
            'id' => $id,
            'name' => $item['dua_name'],
            'name_arabic' => $item['dua_name_arabic'],
            'target_single' => $target_single,
            'target_total' => $target_total,
            'completed' => $completed,
            'percentage' => $percentage
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'type' => $type,
    'total_users' => $total_users,
    'branch' => ($is_category_coordinator && $assigned_category) ? $assigned_category : 'Global',
    'items' => $items
]);
exit;
