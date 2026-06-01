<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'User Dashboard';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get user data
$user = get_user_by_id($conn, $user_id);

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
        <p>Track your Amali Janib progress</p>
    </div>

    <!-- Quick Navigation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-compass"></i> Quick Navigation</h3>
        </div>
        <div style="padding: var(--spacing-xl);">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-bottom: 0;">
                <a href="quran_tracking.php" class="btn metallic-btn" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-quran" style="font-size: 1.5rem;"></i>
                    <span>Quran Tilawat</span>
                </a>
                <a href="dua_tracking.php" class="btn metallic-btn" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-hands-praying" style="font-size: 1.5rem;"></i>
                    <span>Dua Tracking</span>
                </a>
                <a href="book_transcription.php" class="btn metallic-btn" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-book" style="font-size: 1.5rem;"></i>
                    <span>Istinsakh Kutub</span>
                </a>
                <a href="ziyarat_portal.php" class="btn metallic-btn" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-kaaba" style="font-size: 1.5rem;"></i>
                    <span>Ziyarat</span>
                </a>
                <a href="overview.php" class="btn btn-info metallic-btn" style="flex-direction: column; padding: 1.5rem 1rem; gap: 10px;">
                    <i class="fas fa-chart-pie" style="font-size: 1.5rem;"></i>
                    <span>Overview</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
