<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'User Details';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id === 0) {
    header('Location: view_users.php');
    exit();
}

// Get user data
$user = get_user_by_id($conn, $user_id);

if (!$user) {
    header('Location: view_users.php');
    exit();
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user"></i> User Details</p>
    </div>

    <!-- User Info Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle"></i> User Information</h3>
            <div style="margin-left: auto;">
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-edit"></i> Edit User Details
                </a>
            </div>
        </div>
        <div class="user-info-grid">
            <div class="user-info-item">
                <label><i class="fas fa-id-card"></i> ITS Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['its_number']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-id-badge"></i> TR Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['tr_number']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-mosque"></i> Jamea</label>
                <div class="user-info-value">
                    <?php if ($user['category']): ?>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($user['category']); ?></span>
                    <?php else: ?>
                        <span style="color: #999;">Not specified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-user"></i> Full Name</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-envelope"></i> Email</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <div class="user-info-value"><?php echo htmlspecialchars($user['phone_number'] ?: 'Not provided'); ?></div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-shield-alt"></i> Role</label>
                <div class="user-info-value">
                    <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                </div>
            </div>
            <div class="user-info-item">
                <label><i class="fas fa-calendar-plus"></i> Joined Date</label>
                <div class="user-info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="view_users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>