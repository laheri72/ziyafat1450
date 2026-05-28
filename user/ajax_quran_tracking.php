<?php
ob_start();

function send_json_response($payload, $status_code = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

register_shutdown_function(function () {
    $error = error_get_last();
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if ($error && in_array($error['type'], $fatal_types, true)) {
        send_json_response([
            'success' => false,
            'message' => 'Server error: ' . $error['message']
        ], 500);
    }
});

require_once '../config/database.php';
require_once '../includes/functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Check if user is logged in
init_session();
if (!is_logged_in()) {
    send_json_response(['success' => false, 'message' => 'Not authenticated'], 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(['success' => false, 'message' => 'Invalid JSON input'], 400);
}

$user_id = $_SESSION['user_id'];
// Allow amali admins to submit on behalf of users
if (isset($data['target_user_id']) && has_amali_access()) {
    $user_id = intval($data['target_user_id']);
}

if (!$data || !isset($data['selections']) || !is_array($data['selections'])) {
    send_json_response(['success' => false, 'message' => 'Invalid input data'], 400);
}

$action = isset($data['action']) ? strtolower(trim($data['action'])) : 'complete';
if (!in_array($action, ['complete', 'delete'], true)) {
    send_json_response(['success' => false, 'message' => 'Invalid action requested'], 400);
}

$selections = $data['selections'];
$success_count = 0;
$errors = [];
$transaction_started = false;

try {
    // Start transaction
    $conn->begin_transaction();
    $transaction_started = true;
    
    foreach ($selections as $selection) {
        $quran_number = intval($selection['quran_number']);
        $juz_number = intval($selection['juz_number']);
        
        if ($quran_number < 1 || $quran_number > 4 || $juz_number < 1 || $juz_number > 30) {
            continue;
        }

        if ($action === 'complete') {
            // Check if already completed
            $check_sql = "SELECT id FROM quran_progress WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                // Insert new completion
                $sql = "INSERT INTO quran_progress (user_id, quran_number, juz_number, is_completed, completed_date) 
                        VALUES (?, ?, ?, 1, CURDATE())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $user_id, $quran_number, $juz_number);

                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to update progress for Quran $quran_number Juz $juz_number";
                }
            }
        } else {
            $delete_sql = "SELECT id FROM quran_progress 
                           WHERE user_id = ? AND quran_number = ? AND juz_number = ? AND is_completed = 1 
                           ORDER BY completed_date DESC, created_at DESC, id DESC 
                           LIMIT 1";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("iii", $user_id, $quran_number, $juz_number);
            $delete_stmt->execute();
            $delete_result = $delete_stmt->get_result();

            if ($delete_result->num_rows > 0) {
                $row = $delete_result->fetch_assoc();
                $remove_sql = "DELETE FROM quran_progress WHERE id = ?";
                $remove_stmt = $conn->prepare($remove_sql);
                $remove_stmt->bind_param("i", $row['id']);

                if ($remove_stmt->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to delete progress for Quran $quran_number Juz $juz_number";
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    $transaction_started = false;
    
    // Get updated progress for response
    $quran_progress = get_quran_progress($conn, $user_id);
    
    // Get per-quran counts
    $quran_counts = [];
    for ($q = 1; $q <= 4; $q++) {
        $q_sql = "SELECT COUNT(*) as count FROM quran_progress WHERE user_id = ? AND quran_number = ? AND is_completed = 1";
        $q_stmt = $conn->prepare($q_sql);
        $q_stmt->bind_param("ii", $user_id, $q);
        $q_stmt->execute();
        $quran_counts[$q] = $q_stmt->get_result()->fetch_assoc()['count'];
    }
    
    send_json_response([
        'success' => true,
        'message' => $action === 'complete'
            ? ($success_count > 0 ? "$success_count Juz marked as completed!" : "No new progress to update.")
            : ($success_count > 0 ? "$success_count completed log(s) deleted successfully!" : "No matching completed progress found to delete."),
        'overall_progress' => $quran_progress,
        'quran_counts' => $quran_counts,
        'action' => $action,
        'errors' => $errors
    ]);
    
} catch (Throwable $e) {
    // Rollback on error
    if ($transaction_started) {
        $conn->rollback();
    }

    send_json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>
