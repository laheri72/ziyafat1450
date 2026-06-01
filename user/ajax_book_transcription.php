<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
init_session();
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
if (isset($_POST['target_user_id']) && has_amali_access()) {
    $user_id = intval($_POST['target_user_id']);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'select' || $action === 'request_book') {
        $book_id = intval($_POST['book_id']);

        if (user_has_active_book_assignment($conn, $user_id)) {
            echo json_encode(['success' => false, 'message' => 'You already have an assigned book. Please complete or ask admin before requesting another.']);
            exit();
        }

        $book_sql = "SELECT bm.id
                     FROM books_master bm
                     LEFT JOIN book_transcription bt
                         ON bt.book_id = bm.id AND bt.status IN ('selected', 'completed')
                     WHERE bm.id = ? AND bm.is_active = 1 AND bt.id IS NULL";
        $book_stmt = $conn->prepare($book_sql);
        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();

        if ($book_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'This book is already assigned or is not available for request.']);
            exit();
        }

        $request_sql = "SELECT id, request_status FROM book_transcription_requests WHERE user_id = ? AND book_id = ?";
        $request_stmt = $conn->prepare($request_sql);
        $request_stmt->bind_param("ii", $user_id, $book_id);
        $request_stmt->execute();
        $request_result = $request_stmt->get_result();
        
        if ($request_result->num_rows > 0) {
            $existing_request = $request_result->fetch_assoc();

            if ($existing_request['request_status'] === 'pending') {
                echo json_encode(['success' => false, 'message' => 'You already have a pending request for this book.']);
                exit();
            }

            $update_sql = "UPDATE book_transcription_requests
                           SET request_status = 'pending', requested_at = NOW(), reviewed_at = NULL, reviewed_by = NULL, review_notes = NULL
                           WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $existing_request['id']);

            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Request submitted again. Admin will review it.']);
            } else {
                throw new Exception('Failed to submit request');
            }
        } else {
            $sql = "INSERT INTO book_transcription_requests (user_id, book_id, request_status, requested_at)
                    VALUES (?, ?, 'pending', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $book_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Request submitted successfully!']);
            } else {
                throw new Exception('Failed to submit request');
            }
        }
    } elseif ($action === 'update_progress') {
        $book_id = intval($_POST['book_id']);
        $pages_completed = intval($_POST['pages_completed']);
        
        // Get total pages for validation
        $check_sql = "SELECT bm.total_pages 
                      FROM books_master bm 
                      JOIN book_transcription bt ON bm.id = bt.book_id 
                      WHERE bt.user_id = ? AND bt.book_id = ? AND bt.status IN ('selected', 'completed')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $book_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $total_pages = $result->fetch_assoc()['total_pages'];
            
            if ($pages_completed < 0 || $pages_completed > $total_pages) {
                echo json_encode(['success' => false, 'message' => "Invalid page count (0-$total_pages)"]);
            } else {
                $sql = "UPDATE book_transcription SET pages_completed = ? WHERE user_id = ? AND book_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $pages_completed, $user_id, $book_id);
                
                if ($stmt->execute()) {
                    $pct = round(($pages_completed / $total_pages) * 100, 2);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Progress updated!',
                        'data' => [
                            'pages_completed' => $pages_completed,
                            'total_pages' => $total_pages,
                            'percentage' => $pct
                        ]
                    ]);
                } else {
                    throw new Exception('Failed to update progress');
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
        }
    } elseif ($action === 'complete') {
        $book_id = intval($_POST['book_id']);
        $notes = clean_input($_POST['notes'] ?? '');
        
        $check_sql = "SELECT total_pages FROM books_master WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $book_id);
        $check_stmt->execute();
        $total_pages = $check_stmt->get_result()->fetch_assoc()['total_pages'];
        
        $sql = "UPDATE book_transcription 
                SET status = 'completed', completed_date = CURDATE(), notes = ?, pages_completed = ?
            WHERE user_id = ? AND book_id = ? AND status IN ('selected', 'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $notes, $total_pages, $user_id, $book_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book marked as completed!']);
        } else {
            throw new Exception('Failed to update status');
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>