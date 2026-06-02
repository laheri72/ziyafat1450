<?php
// Helper Functions

// Start session if not already started
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // Security Headers to prevent phishing/clickjacking
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;");
}

// Check if user is logged in
function is_logged_in() {
    init_session();
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is amali coordinator
function is_amali_coordinator() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin' 
           && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'amali_coordinator';
}

// Check if user is category-specific amali coordinator
function is_category_amali_coordinator() {
    init_session();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['admin_type'])) {
        return false;
    }
    
    $category_coordinators = [
        'surat_amali_coordinator',
        'marol_amali_coordinator',
        'karachi_amali_coordinator',
        'nairobi_amali_coordinator',
        'muntasib_amali_coordinator'
    ];
    
    return in_array($_SESSION['admin_type'], $category_coordinators);
}

// Check if user is super admin
function is_super_admin() {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin' 
           && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'super_admin';
}

// Check if user has access to amali data
function has_amali_access() {
    return is_super_admin() || is_amali_coordinator() || is_category_amali_coordinator();
}



// Check if user can manage amali masters (duas/books)
function can_manage_amali_masters() {
    return is_super_admin() || (has_amali_access() && !is_category_amali_coordinator());
}

// Get assigned category for category-specific amali coordinator
function get_assigned_category() {
    init_session();
    if (!isset($_SESSION['admin_type'])) {
        return null;
    }
    
    // Extract category from admin_type (e.g., 'surat_amali_coordinator' -> 'Surat')
    $admin_type = $_SESSION['admin_type'];
    $category_map = [
        'surat_amali_coordinator' => 'Surat',
        'marol_amali_coordinator' => 'Marol',
        'karachi_amali_coordinator' => 'Karachi',
        'nairobi_amali_coordinator' => 'Nairobi',
        'muntasib_amali_coordinator' => 'Muntasib'
    ];
    
    return isset($category_map[$admin_type]) ? $category_map[$admin_type] : null;
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Redirect if not admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: ../user/index.php');
        exit();
    }
}

// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Calculate percentage
function calculate_percentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

// Generate CSRF token
function generate_csrf_token() {
    init_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    init_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get user by ID
function get_user_by_id($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get Quran progress for a user
function get_quran_progress($conn, $user_id) {
    $sql = "SELECT 
                COUNT(*) as completed_juz,
                120 as total_juz,
                ROUND((COUNT(*) / 120) * 100, 2) as progress_percentage
            FROM quran_progress 
            WHERE user_id = ? AND is_completed = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get Dua progress for a user
function get_dua_progress($conn, $user_id, $category = null) {
    $sql = "SELECT 
                dm.id,
                dm.dua_name,
                dm.dua_name_arabic,
                dm.category,
                dm.target_count,
                COALESCE(SUM(de.count_added), 0) as completed_count,
                ROUND((COALESCE(SUM(de.count_added), 0) / dm.target_count) * 100, 2) as progress_percentage,
                MAX(de.entry_date) as last_updated
            FROM duas_master dm
            LEFT JOIN dua_entries de ON dm.id = de.dua_id AND de.user_id = ?
            WHERE dm.is_active = 1";
    
    if ($category) {
        $sql .= " AND dm.category = ?";
    }
    
    $sql .= " GROUP BY dm.id, dm.dua_name, dm.dua_name_arabic, dm.category, dm.target_count
              ORDER BY dm.display_order";
    
    $stmt = $conn->prepare($sql);
    if ($category) {
        $stmt->bind_param("is", $user_id, $category);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Get Dua entries history for a user
function get_dua_entries($conn, $user_id, $dua_id = null) {
    if ($dua_id) {
        $sql = "SELECT de.*, dm.dua_name 
                FROM dua_entries de
                JOIN duas_master dm ON de.dua_id = dm.id
                WHERE de.user_id = ? AND de.dua_id = ?
                ORDER BY de.entry_date DESC, de.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $dua_id);
    } else {
        $sql = "SELECT de.*, dm.dua_name 
                FROM dua_entries de
                JOIN duas_master dm ON de.dua_id = dm.id
                WHERE de.user_id = ?
                ORDER BY de.entry_date DESC, de.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Get active Mazars for Ziyarat entry forms
function get_active_mazars($conn) {
    $sql = "SELECT * FROM mazars_master WHERE is_active = 1 ORDER BY display_order, mazar_name";
    return $conn->query($sql);
}

// Get all Mazars for admin/report filters
function get_all_mazars($conn) {
    $sql = "SELECT * FROM mazars_master ORDER BY display_order, mazar_name";
    return $conn->query($sql);
}

// Get total Ziyarat count for a user
function get_ziyarat_total($conn, $user_id) {
    $sql = "SELECT COALESCE(SUM(count_added), 0) as total_count
            FROM ziyarat_entries
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['total_count'] : 0;
}

// Get user Ziyarat totals grouped by Mazar
function get_ziyarat_breakdown($conn, $user_id) {
    $sql = "SELECT
                mm.id,
                mm.mazar_name,
                mm.is_active,
                COALESCE(SUM(ze.count_added), 0) as total_count,
                MAX(ze.entry_date) as last_entry_date
            FROM mazars_master mm
            LEFT JOIN ziyarat_entries ze ON mm.id = ze.mazar_id AND ze.user_id = ?
            GROUP BY mm.id, mm.mazar_name, mm.is_active
            HAVING total_count > 0 OR mm.is_active = 1
            ORDER BY mm.display_order, mm.mazar_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get recent Ziyarat entries for a user
function get_recent_ziyarat_entries($conn, $user_id, $limit = 20) {
    $limit = max(1, min((int)$limit, 100));
    $sql = "SELECT
                ze.id,
                ze.mazar_id,
                ze.count_added,
                ze.entry_date,
                ze.created_at,
                mm.mazar_name
            FROM ziyarat_entries ze
            JOIN mazars_master mm ON ze.mazar_id = mm.id
            WHERE ze.user_id = ?
            ORDER BY ze.created_at DESC, ze.id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get Book transcription progress for a user (only active assignments)
function get_book_progress($conn, $user_id) {
    $sql = "SELECT 
                bm.id,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bt.status,
                bt.started_date,
                bt.completed_date,
                bt.notes
            FROM book_transcription bt
            JOIN books_master bm ON bt.book_id = bm.id
            WHERE bt.user_id = ? AND bm.is_active = 1 AND bt.status IN ('selected', 'completed')
            ORDER BY bt.status DESC, bt.started_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get Book transcription progress with page tracking for a user
// Shows books even if deactivated, as long as they're in progress or completed
function get_book_progress_with_pages($conn, $user_id) {
    $sql = "SELECT 
                bm.id,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bm.total_pages,
                bm.is_active,
                bt.status,
                bt.pages_completed,
                bt.started_date,
                bt.completed_date,
                bt.notes
            FROM book_transcription bt
            JOIN books_master bm ON bt.book_id = bm.id
            WHERE bt.user_id = ? AND bt.status IN ('selected', 'completed')
            ORDER BY bt.status DESC, bt.started_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get all available books for request/assignment (active and not currently assigned)
function get_available_books($conn) {
    $sql = "SELECT bm.*
            FROM books_master bm
            LEFT JOIN book_transcription bt
                ON bt.book_id = bm.id AND bt.status IN ('selected', 'completed')
            WHERE bm.is_active = 1 AND bt.id IS NULL
            ORDER BY bm.display_order, bm.id";
    return $conn->query($sql);
}

// Get all books with their current active assignment info for admin views
function get_books_with_assignments($conn) {
    $sql = "SELECT 
                bm.id,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bm.total_pages,
                bm.description,
                bm.is_active,
                bm.display_order,
                bt.user_id AS assigned_user_id,
                bt.status AS assignment_status,
                bt.pages_completed,
                bt.started_date,
                bt.completed_date,
                bt.notes,
                u.name AS assigned_user_name,
                u.its_number AS assigned_user_its
            FROM books_master bm
            LEFT JOIN book_transcription bt
                ON bt.book_id = bm.id AND bt.status IN ('selected', 'completed')
            LEFT JOIN users u ON u.id = bt.user_id
            ORDER BY bm.display_order, bm.id";

    return $conn->query($sql);
}

// Get pending book requests for admin review
function get_pending_book_requests($conn) {
    $sql = "SELECT 
                br.id,
                br.user_id,
                br.book_id,
                br.request_status,
                br.requested_at,
                br.reviewed_at,
                br.review_notes,
                u.name AS user_name,
                u.its_number AS user_its,
                bm.book_name,
                bm.book_name_arabic,
                bm.total_pages
            FROM book_transcription_requests br
            JOIN users u ON u.id = br.user_id
            JOIN books_master bm ON bm.id = br.book_id
            WHERE br.request_status = 'pending'
            ORDER BY br.requested_at ASC, br.id ASC";

    return $conn->query($sql);
}

// Check if a user already has an active kutub assignment
function user_has_active_book_assignment($conn, $user_id) {
    $sql = "SELECT COUNT(*) AS total
            FROM book_transcription
        WHERE user_id = ? AND status = 'selected'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['total'] ?? 0) > 0;
}

// Check whether a specific book is currently assigned
function book_has_active_assignment($conn, $book_id) {
    $sql = "SELECT COUNT(*) AS total
            FROM book_transcription
            WHERE book_id = ? AND status IN ('selected', 'completed')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['total'] ?? 0) > 0;
}

// Get overall Amali progress summary
function get_amali_summary($conn, $user_id) {
    // Get Quran progress
    $sql_quran = "SELECT 
                    COUNT(DISTINCT CASE WHEN is_completed = 1 THEN CONCAT(quran_number, '-', juz_number) END) as completed_juz,
                    FLOOR(COUNT(DISTINCT CASE WHEN is_completed = 1 THEN CONCAT(quran_number, '-', juz_number) END) / 30) as completed_qurans
                  FROM quran_progress 
                  WHERE user_id = ?";
    $stmt = $conn->prepare($sql_quran);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $quran_data = $stmt->get_result()->fetch_assoc();
    
    // Get Dua count - sum all dua entries
    $sql_dua = "SELECT COALESCE(SUM(count_added), 0) as total_dua_count
                FROM dua_entries 
                WHERE user_id = ?";
    $stmt = $conn->prepare($sql_dua);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $dua_data = $stmt->get_result()->fetch_assoc();

    // Get Ziyarat count
    $sql_ziyarat = "SELECT COALESCE(SUM(count_added), 0) as total_ziyarat_count
                    FROM ziyarat_entries
                    WHERE user_id = ?";
    $stmt = $conn->prepare($sql_ziyarat);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $ziyarat_data = $stmt->get_result()->fetch_assoc();
    
    // Get Book progress
    $sql_books = "SELECT 
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as books_completed,
                    COUNT(CASE WHEN status = 'selected' THEN 1 END) as books_in_progress
                  FROM book_transcription 
                  WHERE user_id = ?";
    $stmt = $conn->prepare($sql_books);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $book_data = $stmt->get_result()->fetch_assoc();
    
    // Combine all data
    return array_merge($quran_data, $dua_data, $ziyarat_data, $book_data);
}

// Get pending book requests for admin notification (limited to latest 5)
function get_pending_book_requests_glance($conn, $limit = 5) {
    $sql = "SELECT 
                br.id,
                u.name AS user_name,
                bm.book_name,
                br.requested_at
            FROM book_transcription_requests br
            JOIN users u ON u.id = br.user_id
            JOIN books_master bm ON bm.id = br.book_id
            WHERE br.request_status = 'pending'
            ORDER BY br.requested_at DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get pending book requests for a specific user
function get_user_pending_book_requests($conn, $user_id) {
    $sql = "SELECT 
                br.id,
                br.requested_at,
                bm.book_name,
                bm.book_name_arabic,
                bm.author,
                bm.total_pages
            FROM book_transcription_requests br
            JOIN books_master bm ON bm.id = br.book_id
            WHERE br.user_id = ? AND br.request_status = 'pending'
            ORDER BY br.requested_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}



?>
