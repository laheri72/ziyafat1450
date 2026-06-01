<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

init_session();
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
if (isset($_POST['target_user_id']) && has_amali_access()) {
    $user_id = intval($_POST['target_user_id']);
}

$mazar_id = isset($_POST['mazar_id']) ? intval($_POST['mazar_id']) : 0;
$count_to_add = isset($_POST['count_to_add']) ? intval($_POST['count_to_add']) : 0;
$entry_date = date('Y-m-d');

if ($mazar_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid Mazar']);
    exit();
}

if ($count_to_add <= 0) {
    echo json_encode(['success' => false, 'message' => 'Count must be greater than 0']);
    exit();
}

try {
    $check_sql = "SELECT id, mazar_name FROM mazars_master WHERE id = ? AND is_active = 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $mazar_id);
    $check_stmt->execute();
    $mazar = $check_stmt->get_result()->fetch_assoc();

    if (!$mazar) {
        echo json_encode(['success' => false, 'message' => 'Selected Mazar is not available']);
        exit();
    }

    $sql = "INSERT INTO ziyarat_entries (user_id, mazar_id, count_added, entry_date)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $user_id, $mazar_id, $count_to_add, $entry_date);

    if (!$stmt->execute()) {
        throw new Exception('Failed to add Ziyarat entry');
    }

    $entry_id = $conn->insert_id;
    $recorded_at_label = date('M d, Y H:i');

    $total = get_ziyarat_total($conn, $user_id);

    $breakdown_sql = "SELECT COALESCE(SUM(count_added), 0) as mazar_total
                      FROM ziyarat_entries
                      WHERE user_id = ? AND mazar_id = ?";
    $breakdown_stmt = $conn->prepare($breakdown_sql);
    $breakdown_stmt->bind_param("ii", $user_id, $mazar_id);
    $breakdown_stmt->execute();
    $breakdown = $breakdown_stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'message' => 'Ziyarat entry added successfully!',
        'data' => [
            'entry_id' => $entry_id,
            'total_count' => $total,
            'mazar_id' => $mazar_id,
            'mazar_name' => $mazar['mazar_name'],
            'count_added' => $count_to_add,
            'mazar_total' => (int)$breakdown['mazar_total'],
            'entry_date_label' => date('M d, Y', strtotime($entry_date)),
            'recorded_at_label' => $recorded_at_label
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
