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
$entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;

if ($entry_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Ziyarat entry']);
    exit();
}

try {
    $conn->begin_transaction();

    $entry_sql = "SELECT ze.id, ze.mazar_id, ze.count_added, mm.mazar_name
                  FROM ziyarat_entries ze
                  JOIN mazars_master mm ON ze.mazar_id = mm.id
                  WHERE ze.id = ? AND ze.user_id = ?
                  LIMIT 1";
    $entry_stmt = $conn->prepare($entry_sql);
    $entry_stmt->bind_param("ii", $entry_id, $user_id);
    $entry_stmt->execute();
    $entry = $entry_stmt->get_result()->fetch_assoc();

    if (!$entry) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Entry not found or already deleted']);
        exit();
    }

    $delete_sql = "DELETE FROM ziyarat_entries WHERE id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $entry_id, $user_id);

    if (!$delete_stmt->execute()) {
        throw new Exception('Failed to delete Ziyarat entry');
    }

    $total = get_ziyarat_total($conn, $user_id);

    $breakdown_sql = "SELECT
                          COALESCE(SUM(count_added), 0) as mazar_total,
                          MAX(entry_date) as last_entry_date
                      FROM ziyarat_entries
                      WHERE user_id = ? AND mazar_id = ?";
    $breakdown_stmt = $conn->prepare($breakdown_sql);
    $breakdown_stmt->bind_param("ii", $user_id, $entry['mazar_id']);
    $breakdown_stmt->execute();
    $breakdown = $breakdown_stmt->get_result()->fetch_assoc();

    $recent_sql = "SELECT COUNT(*) as remaining_entries FROM ziyarat_entries WHERE user_id = ?";
    $recent_stmt = $conn->prepare($recent_sql);
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent = $recent_stmt->get_result()->fetch_assoc();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ziyarat entry deleted successfully.',
        'data' => [
            'entry_id' => $entry_id,
            'total_count' => $total,
            'mazar_id' => (int)$entry['mazar_id'],
            'mazar_name' => $entry['mazar_name'],
            'mazar_total' => (int)$breakdown['mazar_total'],
            'last_entry_label' => $breakdown['last_entry_date'] ? date('M d, Y', strtotime($breakdown['last_entry_date'])) : '-',
            'remaining_entries' => (int)$recent['remaining_entries']
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
