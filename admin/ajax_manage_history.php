<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
init_session();
if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'get_history') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    if ($user_id <= 0 || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $history = [];

    if ($category === 'quran') {
        $sql = "SELECT id, quran_number, juz_number, completed_date, created_at 
                FROM quran_progress 
                WHERE user_id = ? AND is_completed = 1 
                ORDER BY completed_date DESC, created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'label' => "Quran " . $row['quran_number'] . " - Juz " . $row['juz_number'],
                'date' => date('M d, Y', strtotime($row['completed_date'])),
                'recorded_on' => date('M d, Y H:i', strtotime($row['created_at']))
            ];
        }
    } elseif (in_array($category, ['dua', 'tasbeeh', 'namaz'])) {
        $sql = "SELECT de.id, dm.dua_name, de.count_added, de.entry_date, de.created_at 
                FROM dua_entries de
                JOIN duas_master dm ON de.dua_id = dm.id
                WHERE de.user_id = ? AND dm.category = ?
                ORDER BY de.entry_date DESC, de.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $category);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'label' => $row['dua_name'],
                'count' => $row['count_added'],
                'date' => date('M d, Y', strtotime($row['entry_date'])),
                'recorded_on' => date('M d, Y H:i', strtotime($row['created_at']))
            ];
        }
    } elseif ($category === 'ziyarat') {
        $sql = "SELECT ze.id, mm.mazar_name, ze.count_added, ze.entry_date, ze.created_at 
                FROM ziyarat_entries ze
                JOIN mazars_master mm ON ze.mazar_id = mm.id
                WHERE ze.user_id = ?
                ORDER BY ze.entry_date DESC, ze.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'label' => $row['mazar_name'],
                'count' => $row['count_added'],
                'date' => date('M d, Y', strtotime($row['entry_date'])),
                'recorded_on' => date('M d, Y H:i', strtotime($row['created_at']))
            ];
        }
    }

    echo json_encode(['success' => true, 'history' => $history]);
    exit();
}

if ($action === 'delete_entry') {
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $category = isset($_POST['category']) ? $_POST['category'] : '';

    if ($entry_id <= 0 || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $table = '';
    if ($category === 'quran') {
        $table = 'quran_progress';
    } elseif (in_array($category, ['dua', 'tasbeeh', 'namaz'])) {
        $table = 'dua_entries';
    } elseif ($category === 'ziyarat') {
        $table = 'ziyarat_entries';
    }

    if ($table) {
        $sql = "DELETE FROM $table WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $entry_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Entry deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete entry']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid category for deletion']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
