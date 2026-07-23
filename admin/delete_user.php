<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0 || $user_id === $_SESSION['user_id']) {
    header('Location: view_users.php');
    exit();
}

$sql = "SELECT role, category FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$target_user = $stmt->get_result()->fetch_assoc();

if (!$target_user) {
    header('Location: view_users.php?error=User not found');
    exit();
}

if (!is_super_admin()) {
    $assigned_category = get_assigned_category();
    if ($assigned_category && $target_user['category'] !== $assigned_category) {
        header('Location: view_users.php?error=Unauthorized action');
        exit();
    }
}

// Delete user
$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    header('Location: view_users.php?success=User deleted successfully');
} else {
    header('Location: view_users.php?error=Failed to delete user');
}
exit();
?>