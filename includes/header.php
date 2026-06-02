<?php
require_once __DIR__ . '/functions.php';
init_session();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Ziyafat us Shukr</title>
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '../assets/css/'; ?>style.css">
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '../assets/css/'; ?>scholastic.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Check sidebar state before page renders to prevent flicker
        (function() {
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed' && window.innerWidth > 1024) {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        })();
    </script>
    <style>
        /* Admin Notification Styles */
        .topbar-notif {
            position: relative;
            margin-right: 1.5rem;
        }
        .notif-toggle {
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.25rem;
            cursor: pointer;
            position: relative;
            padding: 5px;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notif-toggle:hover {
            color: var(--primary-600);
        }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 10px;
            border: 2px solid white;
            min-width: 18px;
            text-align: center;
        }
        .notif-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
            margin-top: 10px;
            display: none;
            z-index: 1000;
            overflow: hidden;
            animation: slideDown 0.2s ease-out;
        }
        .notif-dropdown.show {
            display: block;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }
        .notif-header h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1e293b;
        }
        .notif-body {
            max-height: 350px;
            overflow-y: auto;
        }
        .notif-item {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: #475569;
            transition: background 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }
        .notif-item:hover {
            background: #f1f5f9;
        }
        .notif-icon {
            width: 36px;
            height: 36px;
            background: #dcfce7;
            color: #166534;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-content p {
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .notif-content small {
            color: #94a3b8;
            font-size: 0.75rem;
            margin-top: 4px;
            display: block;
        }
        .notif-empty {
            padding: 30px 20px;
            text-align: center;
            color: #94a3b8;
        }
        .notif-empty i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #cbd5e1;
        }
        .notif-footer {
            padding: 10px;
            text-align: center;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .notif-footer a {
            font-size: 0.85rem;
            color: var(--primary-600);
            text-decoration: none;
            font-weight: 600;
        }
        .notif-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .notif-dropdown {
                position: fixed;
                top: 70px;
                right: 10px;
                left: 10px;
                width: auto;
            }
            .topbar-notif {
                margin-right: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php if (is_logged_in()): ?>
        <div class="app-layout">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <script>
                    if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 1024) {
                        document.getElementById('sidebar').classList.add('collapsed');
                    }
                </script>
                <div class="sidebar-header">
                 
                    <div class="sidebar-brand">
                        <div class="sidebar-brand-logo" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; background-color: BLUE; color: white; border-radius: 8px;">
                            11
                        </div>

                        <div class="sidebar-brand-text">
                            <h2>Ziyafat us Shukr</h2>
                            <p>Management System</p>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <?php if (is_admin()): ?>
                        <!-- Admin Navigation -->
                        <div class="nav-section">
                            <div class="nav-section-title">Main</div>
                            <div class="nav-item">
                                <a href="../admin/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i>
                                    <span>Dashboard</span>
                                </a>
                            </div>

                            <!-- User Portal Access for Admin -->
                            <div class="nav-item">
                                <a href="../user/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'user/index.php') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-user-circle"></i>
                                    <span>My Portal</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </div>
                        </div>

                        <div class="nav-section">
                            <div class="nav-section-title">User Management</div>
                            <div class="nav-item">
                                <a href="../admin/view_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_users.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-users"></i>
                                    <span>Users</span>
                                </a>
                            </div>
                            <?php if (is_super_admin() || is_amali_coordinator()): ?>
                            <div class="nav-item">
                                <a href="../admin/add_user.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_user.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add User</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (has_amali_access()): ?>
                            <div class="nav-section">
                                <div class="nav-section-title">Amali Janib</div>
                                <?php if (can_manage_amali_masters()): ?>
                                <div class="nav-item">
                                    <a href="../admin/manage_duas.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_duas.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-hands-praying"></i>
                                        <span>Manage Duas</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../admin/manage_books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_books.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-book"></i>
                                        <span>Manage Kutub</span>
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="nav-item">
                                    <a href="../admin/amali_reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'amali_reports.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-chart-bar"></i>
                                        <span>Amali Reports</span>
                                    </a>
                                </div>
                                <div class="nav-item">
                                    <a href="../user/ziyarat_portal.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ziyarat_portal.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-kaaba"></i>
                                        <span>Ziyarat Portal</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- User Navigation -->
                        <div class="nav-section">
                            <div class="nav-section-title">Main</div>
                            <div class="nav-item">
                                <a href="../user/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-home"></i>
                                    <span>Dashboard</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/overview.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'overview.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-pie"></i>
                                    <span>Overview</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </div>
                        </div>

                        <div class="nav-section">
                            <div class="nav-section-title">Amali Janib</div>
                            <div class="nav-item">
                                <a href="../user/quran_tracking.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quran_tracking.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-quran"></i>
                                    <span>Quran Tracking</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/dua_tracking.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dua_tracking.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-hands-praying"></i>
                                    <span>Dua Tracking</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/book_transcription.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'book_transcription.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-book-open"></i>
                                    <span>Istinsakh Ul Kutub</span>
                                </a>
                            </div>
                            <div class="nav-item">
                                <a href="../user/ziyarat_portal.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ziyarat_portal.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-kaaba"></i>
                                    <span>Ziyarat Portal</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="nav-section">
                        <div class="nav-section-title">Account</div>
                        <div class="nav-item">
                            <a href="../auth/logout.php" class="nav-link">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </nav>
            </aside>

            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Toast Container -->
            <div id="toast-container" class="toast-container"></div>

            <!-- Main Wrapper -->
            <div class="main-wrapper">
                <!-- Topbar -->
                <header class="topbar">
                    <div class="topbar-left">
                        <button class="menu-toggle" id="menuToggle">
                            <i class="fas fa-bars"></i>
                        </button>

                        <div class="topbar-search">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search...">
                        </div>
                    </div>

                    <div class="topbar-right">
                        <?php if (can_manage_amali_masters()): ?>
                            <!-- Admin Notifications -->
                            <?php
                            $notif_requests = get_pending_book_requests($GLOBALS['conn']);
                            $notif_count = $notif_requests->num_rows;
                            $notif_glance = get_pending_book_requests_glance($GLOBALS['conn'], 5);
                            ?>
                            <div class="topbar-notif" id="adminNotifDropdown">
                                <button class="notif-toggle" id="notifToggle" title="View Requests">
                                    <i class="fas fa-bell"></i>
                                    <?php if ($notif_count > 0): ?>
                                        <span class="notif-badge"><?php echo $notif_count; ?></span>
                                    <?php endif; ?>
                                </button>
                                <div class="notif-dropdown" id="notifMenu">
                                    <div class="notif-header">
                                        <h4>Pending Requests</h4>
                                        <?php if ($notif_count > 0): ?>
                                            <span style="background:#ef4444; color:white; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700;"><?php echo $notif_count; ?> NEW</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-body">
                                        <?php if ($notif_count > 0): ?>
                                            <?php while ($req = $notif_glance->fetch_assoc()): ?>
                                                <a href="../admin/manage_books.php#pending-requests" class="notif-item">
                                                    <div class="notif-icon">
                                                        <i class="fas fa-book-reader"></i>
                                                    </div>
                                                    <div class="notif-content">
                                                        <p><strong><?php echo htmlspecialchars($req['user_name']); ?></strong> requested <strong><?php echo htmlspecialchars($req['book_name']); ?></strong></p>
                                                        <small><i class="far fa-clock"></i> <?php echo date('M d, H:i', strtotime($req['requested_at'])); ?></small>
                                                    </div>
                                                </a>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="notif-empty">
                                                <i class="fas fa-check-circle"></i>
                                                <p>All caught up!</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-footer">
                                        <a href="../admin/manage_books.php#pending-requests">Manage All Requests <i class="fas fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="topbar-user">
                            <div class="user-avatar">
                                <?php
                                $logged_in_user = get_user_by_id($GLOBALS['conn'], $_SESSION['user_id']);
                                $words = array_values(array_filter(explode(' ', trim($logged_in_user['name']))));
                                $initials = '';
                                if (!empty($words)) {
                                    $first_idx = 0;
                                    // If first word is "Mulla" and there are more words, skip it for the first initial
                                    if (strtolower($words[0]) === 'mulla' && count($words) > 1) {
                                        $first_idx = 1;
                                    }
                                    
                                    $first_init = strtoupper(substr($words[$first_idx], 0, 1));
                                    
                                    if (count($words) > ($first_idx + 1)) {
                                        $last_idx = count($words) - 1;
                                        // If last word is "wala" and there is a word before it (not the one used for first_init)
                                        if (strtolower($words[$last_idx]) === 'wala' && $last_idx > ($first_idx + 1)) {
                                            $second_init = strtoupper(substr($words[$last_idx - 1], 0, 1));
                                        } else {
                                            $second_init = strtoupper(substr($words[$last_idx], 0, 1));
                                        }
                                        $initials = $first_init . $second_init;
                                    } else {
                                        // If only one meaningful word remains
                                        $initials = strtoupper(substr($words[$first_idx], 0, 2));
                                    }
                                }
                                echo $initials;
                                ?>
                            </div>
                            <div class="user-info">
                                <h4><?php echo htmlspecialchars($logged_in_user['name']); ?></h4>
                                <p><?php echo is_admin() ? 'Administrator' : 'User'; ?></p>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="main-content">
                <script>
                    /**
                     * Global Toast Notification System
                     * @param {string} message 
                     * @param {string} type - 'success', 'error', 'warning', 'info'
                     */
                    function showToast(message, type = 'info') {
                        const container = document.getElementById('toast-container');
                        if (!container) return;
                        
                        const toast = document.createElement('div');
                        toast.className = `toast-item ${type}`;
                        
                        let icon = 'info-circle';
                        if (type === 'success') icon = 'check-circle';
                        if (type === 'error') icon = 'exclamation-circle';
                        if (type === 'warning') icon = 'exclamation-triangle';

                        toast.innerHTML = `
                            <i class="fas fa-${icon}"></i>
                            <div class="toast-message">${message}</div>
                        `;
                        
                        container.appendChild(toast);
                        
                        // Auto remove after 4 seconds
                        setTimeout(() => {
                            toast.style.opacity = '0';
                            toast.style.transform = 'translateX(100%)';
                            toast.style.transition = 'all 0.5s ease';
                            setTimeout(() => toast.remove(), 500);
                        }, 4000);
                    }

                    // Admin Notification Toggle
                    document.addEventListener('DOMContentLoaded', function() {
                        const notifToggle = document.getElementById('notifToggle');
                        const notifMenu = document.getElementById('notifMenu');

                        if (notifToggle && notifMenu) {
                            notifToggle.addEventListener('click', function(e) {
                                e.stopPropagation();
                                notifMenu.classList.toggle('show');
                            });

                            document.addEventListener('click', function(e) {
                                if (!notifMenu.contains(e.target) && !notifToggle.contains(e.target)) {
                                    notifMenu.classList.remove('show');
                                }
                            });
                        }
                    });
                </script>
    <?php else: ?>
        <!-- Login page layout (no sidebar) -->
    <?php endif; ?>
