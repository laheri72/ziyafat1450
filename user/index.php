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

<style>
    /* Smart UX Fix: Compact Dashboard for Mobile */
    @media (max-width: 768px) {
        .main-content {
            padding: var(--spacing-sm) !important;
        }
        .page-header {
            margin-bottom: var(--spacing-md) !important;
            padding-bottom: var(--spacing-xs) !important;
            border-bottom: 1px solid var(--primary-100) !important;
        }
        .page-header h1 {
            font-size: 1.2rem !important;
            margin-bottom: 0 !important;
        }
        .page-header p {
            display: none; /* Hide subtitle to save vertical space */
        }
        .card {
            margin-bottom: var(--spacing-sm) !important;
        }
        .card-header {
            padding: var(--spacing-sm) var(--spacing-md) !important;
        }
        .card-header h3 {
            font-size: 0.9rem !important;
        }
        .card-body-compact {
            padding: var(--spacing-sm) !important;
        }
        .stats-grid-compact {
            grid-template-columns: repeat(auto-fit, minmax(95px, 1fr)) !important;
            gap: 8px !important;
        }
        .metallic-btn {
            padding: 1rem 0.5rem !important;
            gap: 6px !important;
            min-height: 85px;
        }
        .metallic-btn i {
            font-size: 1.25rem !important;
        }
        .metallic-btn span {
            font-size: 0.7rem !important;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
        }
        .footer {
            padding: var(--spacing-sm) !important;
            font-size: 0.7rem !important;
        }
    }
</style>

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
        <div class="card-body-compact">
            <div class="stats-grid stats-grid-compact" style="margin-bottom: 0;">
                <a href="quran_tracking.php" class="btn metallic-btn" style="flex-direction: column;">
                    <i class="fas fa-quran"></i>
                    <span>Quran Tilawat</span>
                </a>
                <a href="dua_tracking.php" class="btn metallic-btn" style="flex-direction: column;">
                    <i class="fas fa-hands-praying"></i>
                    <span>Dua Tracking</span>
                </a>
                <a href="book_transcription.php" class="btn metallic-btn" style="flex-direction: column;">
                    <i class="fas fa-book"></i>
                    <span>Istinsakh Kutub</span>
                </a>
                <a href="ziyarat_portal.php" class="btn metallic-btn" style="flex-direction: column;">
                    <i class="fas fa-kaaba"></i>
                    <span>Ziyarat</span>
                </a>
                <a href="overview.php" class="btn btn-info metallic-btn" style="flex-direction: column;">
                    <i class="fas fa-chart-pie"></i>
                    <span>Overview</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
