<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

if (!is_super_admin()) {
    header('Location: view_users.php');
    exit();
}

$page_title = 'Add / Bulk Import Users';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';
$import_summary = null;
$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'bulk' ? 'bulk' : 'single';

$allowed_branches = ['Surat', 'Marol', 'Karachi', 'Nairobi'];
$allowed_classifications = ['Talabat', 'Taalebaat', 'Muntasebeen', 'Muntasebaat'];

// Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $form_action = clean_input($_POST['form_action'] ?? 'single_user');

        // ==========================================
        // 1. SINGLE USER REGISTRATION
        // ==========================================
        if ($form_action === 'single_user') {
            $active_tab = 'single';
            $its_number = clean_input($_POST['its_number'] ?? '');
            $tr_number = clean_input($_POST['tr_number'] ?? '');
            $category = clean_input($_POST['category'] ?? '');
            $classification = clean_input($_POST['classification'] ?? 'Talabat');
            $name = clean_input($_POST['name'] ?? '');
            $email = clean_input($_POST['email'] ?? '');
            $phone_number = clean_input($_POST['phone_number'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = clean_input($_POST['role'] ?? 'user');
            $admin_type = ($role === 'admin' && !empty($_POST['admin_type'])) ? clean_input($_POST['admin_type']) : null;

            if (empty($its_number) || empty($name) || empty($email) || empty($password)) {
                $error = 'Please fill in all mandatory fields (ITS Number, Name, Email, Password).';
            } elseif (!empty($category) && !in_array($category, $allowed_branches)) {
                $error = 'Please select a valid Jamea branch (Surat, Marol, Karachi, Nairobi).';
            } else {
                // Check if email or ITS number already exists
                $sql = "SELECT id, its_number, email, tr_number FROM users WHERE email = ? OR its_number = ?" . (!empty($tr_number) ? " OR (tr_number = ? AND tr_number != '')" : "");
                $stmt = $conn->prepare($sql);
                if (!empty($tr_number)) {
                    $stmt->bind_param("sss", $email, $its_number, $tr_number);
                } else {
                    $stmt->bind_param("ss", $email, $its_number);
                }
                $stmt->execute();
                $check_result = $stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $existing_user = $check_result->fetch_assoc();
                    if (strtolower($existing_user['its_number']) === strtolower($its_number)) {
                        $error = 'A user with ITS Number ' . htmlspecialchars($its_number) . ' already exists.';
                    } elseif (!empty($tr_number) && strtolower($existing_user['tr_number']) === strtolower($tr_number)) {
                        $error = 'A user with TR Number ' . htmlspecialchars($tr_number) . ' already exists.';
                    } else {
                        $error = 'A user with Email ' . htmlspecialchars($email) . ' already exists.';
                    }
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $final_category = !empty($category) ? $category : null;
                    $final_classification = in_array($classification, $allowed_classifications) ? $classification : 'Talabat';

                    $sql = "INSERT INTO users (its_number, tr_number, category, classification, name, email, phone_number, password, role, admin_type) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssssss", $its_number, $tr_number, $final_category, $final_classification, $name, $email, $phone_number, $hashed_password, $role, $admin_type);

                    if ($stmt->execute()) {
                        $success = 'User "' . htmlspecialchars($name) . '" (' . htmlspecialchars($its_number) . ') added successfully!';
                    } else {
                        $error = 'Database error: Failed to add user. Please check for duplicate entries.';
                    }
                }
            }
        }

        // ==========================================
        // 2. BULK IMPORT USERS EXECUTION (EXCEL ONLY)
        // ==========================================
        elseif ($form_action === 'bulk_import') {
            $active_tab = 'bulk';
            $bulk_branch = clean_input($_POST['bulk_branch'] ?? '');
            $bulk_role = clean_input($_POST['bulk_role'] ?? 'user');
            $bulk_admin_type = ($bulk_role === 'admin' && !empty($_POST['bulk_admin_type'])) ? clean_input($_POST['bulk_admin_type']) : null;
            $bulk_classification = clean_input($_POST['bulk_classification'] ?? '');
            $bulk_json_data = $_POST['bulk_json_data'] ?? '';

            if (empty($bulk_branch) || !in_array($bulk_branch, $allowed_branches)) {
                $error = 'Please select a valid Branch (Jamea) for the bulk import (Surat, Marol, Karachi, Nairobi).';
            } elseif (empty($bulk_classification) || !in_array($bulk_classification, $allowed_classifications)) {
                $error = 'Please select a valid Classification (Talabat, Taalebaat, Muntasebeen, Muntasebaat) from the pre-import settings.';
            } else {
                $students_to_import = [];

                // Process verified Excel spreadsheet JSON data passed from the client review table
                if (!empty($bulk_json_data)) {
                    $decoded = json_decode($bulk_json_data, true);
                    if (is_array($decoded)) {
                        $students_to_import = $decoded;
                    }
                }

                if (empty($students_to_import)) {
                    $error = 'No valid student records found to import. Please upload a valid Excel spreadsheet (.xlsx or .xls).';
                } else {
                    // Meticulous Server-Side Validation & Verification
                    $validation_errors = [];
                    $clean_batch = [];
                    $seen_tr = [];
                    $seen_its = [];
                    $seen_email = [];

                    $check_tr_list = [];
                    $check_its_list = [];
                    $check_email_list = [];

                    foreach ($students_to_import as $idx => $st) {
                        $row_num = $idx + 1;
                        $tr = trim((string)($st['tr_number'] ?? ''));
                        $its = trim((string)($st['its_number'] ?? ''));
                        $name = trim((string)($st['name'] ?? ''));
                        $email = trim((string)($st['email'] ?? ''));
                        $phone = trim((string)($st['phone_number'] ?? ''));

                        // Mandatory field checks
                        if ($tr === '') {
                            $validation_errors[] = "Row #{$row_num}: Missing TR Number.";
                        }
                        if ($its === '') {
                            $validation_errors[] = "Row #{$row_num}: Missing ITS Number.";
                        }
                        if ($name === '') {
                            $validation_errors[] = "Row #{$row_num}: Missing Full Name.";
                        }
                        if ($email === '') {
                            $validation_errors[] = "Row #{$row_num}: Missing Email.";
                        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $validation_errors[] = "Row #{$row_num}: Invalid Email format ({$email}).";
                        }

                        // In-file duplicate checking
                        $tr_low = strtolower($tr);
                        $its_low = strtolower($its);
                        $email_low = strtolower($email);

                        if ($tr !== '') {
                            if (isset($seen_tr[$tr_low])) {
                                $validation_errors[] = "Row #{$row_num}: Duplicate TR Number '{$tr}' in file (previously seen on Row #{$seen_tr[$tr_low]}).";
                            } else {
                                $seen_tr[$tr_low] = $row_num;
                                $check_tr_list[] = $tr;
                            }
                        }

                        if ($its !== '') {
                            if (isset($seen_its[$its_low])) {
                                $validation_errors[] = "Row #{$row_num}: Duplicate ITS Number '{$its}' in file (previously seen on Row #{$seen_its[$its_low]}).";
                            } else {
                                $seen_its[$its_low] = $row_num;
                                $check_its_list[] = $its;
                            }
                        }

                        if ($email !== '') {
                            if (isset($seen_email[$email_low])) {
                                $validation_errors[] = "Row #{$row_num}: Duplicate Email '{$email}' in file (previously seen on Row #{$seen_email[$email_low]}).";
                            } else {
                                $seen_email[$email_low] = $row_num;
                                $check_email_list[] = $email;
                            }
                        }

                        $clean_batch[] = [
                            'row_num' => $row_num,
                            'tr_number' => $tr,
                            'its_number' => $its,
                            'name' => $name,
                            'email' => $email,
                            'phone_number' => $phone,
                            'classification' => $bulk_classification,
                            'category' => $bulk_branch,
                            'role' => $bulk_role,
                            'admin_type' => $bulk_admin_type
                        ];
                    }

                    // Database Duplicate Checking
                    if (!empty($check_its_list)) {
                        $chunks = array_chunk($check_its_list, 500);
                        foreach ($chunks as $chunk) {
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $conn->prepare("SELECT its_number, name FROM users WHERE its_number IN ($placeholders)");
                            if ($stmt) {
                                $stmt->bind_param(str_repeat('s', count($chunk)), ...$chunk);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $validation_errors[] = "Database Conflict: ITS Number '{$row['its_number']}' already exists in system (Registered to: {$row['name']}).";
                                }
                            }
                        }
                    }

                    if (!empty($check_tr_list)) {
                        $chunks = array_chunk($check_tr_list, 500);
                        foreach ($chunks as $chunk) {
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $conn->prepare("SELECT tr_number, name, its_number FROM users WHERE tr_number IN ($placeholders) AND tr_number IS NOT NULL AND tr_number != ''");
                            if ($stmt) {
                                $stmt->bind_param(str_repeat('s', count($chunk)), ...$chunk);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $validation_errors[] = "Database Conflict: TR Number '{$row['tr_number']}' already exists in system (Registered to: {$row['name']}, ITS: {$row['its_number']}).";
                                }
                            }
                        }
                    }

                    if (!empty($check_email_list)) {
                        $chunks = array_chunk($check_email_list, 500);
                        foreach ($chunks as $chunk) {
                            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                            $stmt = $conn->prepare("SELECT email, name, its_number FROM users WHERE email IN ($placeholders) AND email IS NOT NULL AND email != ''");
                            if ($stmt) {
                                $stmt->bind_param(str_repeat('s', count($chunk)), ...$chunk);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $validation_errors[] = "Database Conflict: Email '{$row['email']}' already registered in system (User: {$row['name']}, ITS: {$row['its_number']}).";
                                }
                            }
                        }
                    }

                    // If any validation errors exist, abort transaction before writing to DB
                    if (!empty($validation_errors)) {
                        $error = '<strong>Validation Failed!</strong> ' . count($validation_errors) . ' issue(s) detected. No records were imported.<br><ul style="margin-top: 8px; margin-left: 20px; max-height: 200px; overflow-y: auto;">';
                        foreach (array_slice($validation_errors, 0, 15) as $err_msg) {
                            $error .= '<li>' . htmlspecialchars($err_msg) . '</li>';
                        }
                        if (count($validation_errors) > 15) {
                            $error .= '<li>...and ' . (count($validation_errors) - 15) . ' more error(s). Please review your data table.</li>';
                        }
                        $error .= '</ul>';
                    } else {
                        // Atomic Database Transaction Execution
                        $conn->begin_transaction();
                        try {
                            $insert_sql = "INSERT INTO users (its_number, tr_number, category, classification, name, email, phone_number, password, role, admin_type) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($insert_sql);

                            $imported_count = 0;
                            foreach ($clean_batch as $student) {
                                // Password is set to student's TR Number
                                $password_hash = password_hash($student['tr_number'], PASSWORD_DEFAULT);
                                $phone_val = !empty($student['phone_number']) ? $student['phone_number'] : null;

                                $stmt->bind_param(
                                    "ssssssssss",
                                    $student['its_number'],
                                    $student['tr_number'],
                                    $student['category'],
                                    $student['classification'],
                                    $student['name'],
                                    $student['email'],
                                    $phone_val,
                                    $password_hash,
                                    $student['role'],
                                    $student['admin_type']
                                );

                                if (!$stmt->execute()) {
                                    throw new Exception("Error inserting ITS {$student['its_number']} (TR: {$student['tr_number']}): " . $stmt->error);
                                }
                                $imported_count++;
                            }

                            $conn->commit();

                            $success = "Successfully imported <strong>{$imported_count}</strong> students into <strong>{$bulk_branch}</strong> branch (Classification: <strong>{$bulk_classification}</strong>)! All student passwords are initialized to their respective TR Numbers.";
                            $import_summary = [
                                'count' => $imported_count,
                                'branch' => $bulk_branch,
                                'classification' => $bulk_classification,
                                'role' => ucfirst($bulk_role),
                                'admin_type' => $bulk_admin_type ? ucfirst(str_replace('_', ' ', $bulk_admin_type)) : 'N/A'
                            ];
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = "Transaction Aborted! Database error occurred during bulk import: " . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

require_once '../includes/header.php';
?>

<!-- SheetJS (XLSX parser & generator) for Excel spreadsheet processing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    /* Global Container Fixes */
    .container-user-mgmt {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
        box-sizing: border-box;
    }

    .page-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .page-header-title h1 {
        margin: 0;
        font-size: 1.6rem;
        color: var(--primary-700, #064e3b);
    }
    .page-header-title p {
        color: #64748b;
        margin-top: 4px;
        font-size: 0.92rem;
    }
    .page-header-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Tabs Component - Responsive & Touch Scrollable */
    .import-tabs-nav {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
    }
    .import-tabs-nav::-webkit-scrollbar {
        display: none;
    }
    .import-tab-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        font-size: 0.95rem;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: all var(--transition-fast, 0.2s);
        border-radius: var(--radius-md, 8px) var(--radius-md, 8px) 0 0;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .import-tab-btn:hover {
        color: var(--primary-600, #065f46);
        background: rgba(6, 78, 59, 0.04);
    }
    .import-tab-btn.active {
        color: var(--primary-600, #065f46);
        border-bottom-color: var(--primary-600, #065f46);
        background: rgba(6, 78, 59, 0.08);
    }

    /* Step Cards & Grid */
    .step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary-600, #065f46);
        color: white;
        font-weight: 700;
        font-size: 0.85rem;
        margin-right: 8px;
        flex-shrink: 0;
    }
    .step-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-lg, 12px);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        box-sizing: border-box;
        width: 100%;
    }
    .step-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        width: 100%;
    }
    .step-card-header h3 {
        margin: 0;
        font-size: 1.15rem;
        display: flex;
        align-items: center;
        color: #1e293b;
        flex-wrap: wrap;
    }

    /* Form Grids */
    .form-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.25rem;
        width: 100%;
        box-sizing: border-box;
    }
    .form-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        width: 100%;
        box-sizing: border-box;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.35rem;
        color: #334155;
        font-size: 0.9rem;
    }
    .form-group .form-control {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0.6rem 0.85rem;
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-group .form-control:focus {
        border-color: var(--primary-500, #064e3b);
        outline: none;
        box-shadow: 0 0 0 3px rgba(6, 78, 59, 0.15);
    }

    /* Dropzone */
    .dropzone-box {
        border: 2px dashed #cbd5e1;
        border-radius: var(--radius-lg, 12px);
        padding: 2.25rem 1.25rem;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        width: 100%;
        box-sizing: border-box;
    }
    .dropzone-box:hover, .dropzone-box.dragover {
        border-color: var(--primary-500, #064e3b);
        background: rgba(6, 78, 59, 0.03);
    }
    .dropzone-icon {
        font-size: 2.5rem;
        color: var(--primary-500, #064e3b);
        margin-bottom: 0.75rem;
    }
    .dropzone-text {
        font-size: 1rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0.35rem;
        word-break: break-word;
    }
    .dropzone-subtext {
        font-size: 0.85rem;
        color: #64748b;
    }
    .dropzone-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    /* Status Stat Badges */
    .stats-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.25rem;
        width: 100%;
        box-sizing: border-box;
    }
    .stat-pill {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0.85rem 1rem;
        border-radius: var(--radius-md, 8px);
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        box-sizing: border-box;
    }
    .stat-pill-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .stat-pill.total .stat-pill-icon { background: #e0f2fe; color: #0369a1; }
    .stat-pill.valid .stat-pill-icon { background: #dcfce7; color: #15803d; }
    .stat-pill.error .stat-pill-icon { background: #fee2e2; color: #b91c1c; }
    .stat-pill-content h4 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .stat-pill-content span { font-size: 0.8rem; color: #64748b; font-weight: 500; }

    /* Review Table & Mobile Scroll Container */
    .review-table-container {
        width: 100%;
        max-width: 100%;
        max-height: 440px;
        overflow-x: auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-md, 8px);
        box-sizing: border-box;
        background: white;
    }
    .review-table-container table {
        width: 100%;
        min-width: 680px; /* Guarantees columns never crush together */
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    .review-table-container th {
        background: #f1f5f9;
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 10px 12px;
        text-align: left;
        color: #334155;
        font-weight: 600;
        border-bottom: 2px solid #cbd5e1;
        white-space: nowrap;
    }
    .review-table-container td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .table-badge.badge-valid { background: #dcfce7; color: #166534; }
    .table-badge.badge-error { background: #fee2e2; color: #991b1b; }

    .error-list-tooltip {
        color: #dc2626;
        font-size: 0.8rem;
        margin-top: 4px;
        line-height: 1.3;
        word-break: break-word;
    }

    .mobile-scroll-hint {
        display: none;
        padding: 6px 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 0.78rem;
        text-align: center;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    /* Action Buttons Area */
    .bulk-footer-actions {
        margin-top: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        width: 100%;
    }
    .bulk-action-btns {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Loading Overlay */
    .loading-spinner-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.75);
        z-index: 9999;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        padding: 1rem;
        box-sizing: border-box;
        text-align: center;
    }
    .loading-spinner-overlay.show {
        display: flex;
    }
    .spinner-circle {
        width: 48px;
        height: 48px;
        border: 4px solid rgba(255, 255, 255, 0.25);
        border-top: 4px solid #ffffff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-bottom: 1rem;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* =======================================================
       RESPONSIVE MEDIA QUERIES (Mobile & Tablet Optimization)
       ======================================================= */
    @media (max-width: 900px) {
        .form-grid-3 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .container-user-mgmt {
            padding: 0 0.75rem;
        }
        .page-header-flex {
            flex-direction: column;
            align-items: stretch;
            gap: 0.85rem;
        }
        .page-header-title h1 {
            font-size: 1.35rem;
        }
        .page-header-actions {
            display: grid;
            grid-template-columns: 1fr;
            width: 100%;
            gap: 0.5rem;
        }
        .page-header-actions .btn {
            width: 100%;
            justify-content: center;
            padding: 0.65rem;
            font-size: 0.9rem;
        }

        /* Tabs on mobile */
        .import-tabs-nav {
            display: flex;
            width: 100%;
            gap: 0.25rem;
            padding-bottom: 4px;
        }
        .import-tab-btn {
            flex: 1 1 auto;
            padding: 0.65rem 0.75rem;
            font-size: 0.85rem;
            text-align: center;
            justify-content: center;
        }

        /* Step cards on mobile */
        .step-card {
            padding: 1rem 0.85rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .step-card-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        .step-card-header h3 {
            font-size: 1.05rem;
        }
        .step-card-header .btn,
        .step-card-header .badge {
            width: 100%;
            text-align: center;
            justify-content: center;
            box-sizing: border-box;
        }

        /* Form grids on mobile */
        .form-grid-2,
        .form-grid-3 {
            grid-template-columns: 1fr !important;
            gap: 0.85rem;
        }

        /* Dropzone on mobile */
        .dropzone-box {
            padding: 1.5rem 0.75rem;
        }
        .dropzone-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .dropzone-text {
            font-size: 0.9rem;
        }
        .dropzone-subtext {
            font-size: 0.75rem;
        }

        /* Stat Pills on mobile */
        .stats-summary-grid {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        .stat-pill {
            padding: 0.75rem 0.85rem;
        }
        .stat-pill-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .stat-pill-content h4 {
            font-size: 1.15rem;
        }

        /* Show mobile swipe indicator */
        .mobile-scroll-hint {
            display: block;
        }

        /* Review Controls Row */
        .review-filter-row {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 0.5rem;
        }
        .review-filter-row input {
            width: 100% !important;
            box-sizing: border-box;
        }
        .review-filter-row button {
            width: 100%;
            justify-content: center;
        }

        /* Bottom Actions on mobile */
        .bulk-footer-actions {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: 0.85rem;
        }
        .bulk-action-btns {
            display: flex;
            flex-direction: column-reverse;
            width: 100%;
            gap: 0.5rem;
        }
        .bulk-action-btns .btn {
            width: 100%;
            justify-content: center;
            padding: 0.75rem;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .single-action-btns {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 0.5rem;
        }
        .single-action-btns .btn {
            width: 100%;
            justify-content: center;
            padding: 0.75rem;
            font-size: 0.95rem;
        }
    }
</style>

<div class="container container-user-mgmt">
    <div class="page-header-flex">
        <div class="page-header-title">
            <h1><i class="fas fa-user-plus"></i> User Management</h1>
            <p>Register single students or bulk import student batches with automated Excel validation</p>
        </div>
        <div class="page-header-actions">
            <button type="button" class="btn btn-success btn-sm" onclick="downloadExcelTemplate()" title="Download Excel Spreadsheet Template (.xlsx)">
                <i class="fas fa-file-excel"></i> Download Excel Template (.xlsx)
            </button>
            <a href="view_users.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-users"></i> View All Users
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem; word-break: break-word;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem; word-break: break-word;">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="import-tabs-nav">
        <button type="button" class="import-tab-btn <?php echo $active_tab === 'single' ? 'active' : ''; ?>" onclick="switchTab('single')">
            <i class="fas fa-user"></i> Single User Registration
        </button>
        <button type="button" class="import-tab-btn <?php echo $active_tab === 'bulk' ? 'active' : ''; ?>" onclick="switchTab('bulk')">
            <i class="fas fa-file-excel"></i> Bulk Import Students (Excel Only)
        </button>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 1: SINGLE USER REGISTRATION                              -->
    <!-- ============================================================ -->
    <div id="tab-single" class="tab-content" style="<?php echo $active_tab === 'single' ? 'display: block;' : 'display: none;'; ?>">
        <div class="card" style="box-sizing: border-box; width: 100%;">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Enter New User Details</h3>
            </div>
            <form method="POST" action="" style="padding: var(--spacing-lg, 1.25rem); box-sizing: border-box;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="form_action" value="single_user">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="its_number"><i class="fas fa-id-card"></i> ITS Number *</label>
                        <input type="text" id="its_number" name="its_number" class="form-control" placeholder="e.g. 30450001" required>
                    </div>

                    <div class="form-group">
                        <label for="tr_number"><i class="fas fa-id-badge"></i> TR Number</label>
                        <input type="text" id="tr_number" name="tr_number" class="form-control" placeholder="e.g. 26001">
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="category"><i class="fas fa-mosque"></i> Jamea (Branch)</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">-- Select Jamea --</option>
                            <option value="Surat">Surat</option>
                            <option value="Marol">Marol</option>
                            <option value="Karachi">Karachi</option>
                            <option value="Nairobi">Nairobi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="classification"><i class="fas fa-tags"></i> Classification (Class)</label>
                        <select id="classification" name="classification" class="form-control">
                            <option value="Talabat">Talabat</option>
                            <option value="Taalebaat">Taalebaat</option>
                            <option value="Muntasebeen">Muntasebeen</option>
                            <option value="Muntasebaat">Muntasebaat</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Murtaza Bhai Shabbir Bhai" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="e.g. 26001@jameasaifiyah.edu" required>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="+919876543210">
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Initial account password" required>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="role"><i class="fas fa-user-tag"></i> Role *</label>
                        <select id="role" name="role" class="form-control" required onchange="toggleSingleAdminType()">
                            <option value="user" selected>User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group" id="single_admin_type_group" style="display: none;">
                        <label for="admin_type"><i class="fas fa-user-shield"></i> Admin Type</label>
                        <select id="admin_type" name="admin_type" class="form-control">
                            <option value="">Select Admin Type</option>
                            <option value="super_admin">Super Admin (Full Access)</option>
                            <option value="amali_coordinator">Amali Coordinator</option>
                            <option value="surat_amali_coordinator">Surat Amali Coordinator</option>
                            <option value="marol_amali_coordinator">Marol Amali Coordinator</option>
                            <option value="karachi_amali_coordinator">Karachi Amali Coordinator</option>
                            <option value="nairobi_amali_coordinator">Nairobi Amali Coordinator</option>
                        </select>
                    </div>
                </div>

                <div class="single-action-btns" style="margin-top: 1.25rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Single User
                    </button>
                    <a href="view_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 2: BULK IMPORT STUDENTS (EXCEL ONLY)                     -->
    <!-- ============================================================ -->
    <div id="tab-bulk" class="tab-content" style="<?php echo $active_tab === 'bulk' ? 'display: block;' : 'display: none;'; ?>">
        <form id="bulkImportForm" method="POST" action="">
            <input type="hidden" name="csrf_token" id="bulk_csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="form_action" value="bulk_import">
            <input type="hidden" name="bulk_json_data" id="bulk_json_data" value="">

            <!-- STEP 1: PRE-IMPORT CONFIGURATION QUESTIONS -->
            <div class="step-card">
                <div class="step-card-header">
                    <h3><span class="step-badge">1</span> Pre-Import Configuration Settings</h3>
                    <span class="badge badge-primary">Mandatory Questions</span>
                </div>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.25rem;">
                    Choose the designated Branch (Jamea), Role, and Classification for this import batch. All students in this upload will share these settings.
                </p>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="bulk_branch"><i class="fas fa-mosque"></i> 1. Choose Branch (Jamea) *</label>
                        <select id="bulk_branch" name="bulk_branch" class="form-control" required onchange="onBatchConfigChange()">
                            <option value="">-- Select Branch --</option>
                            <option value="Surat">Surat</option>
                            <option value="Marol">Marol</option>
                            <option value="Karachi">Karachi</option>
                            <option value="Nairobi">Nairobi</option>
                        </select>
                        <small style="color: #64748b; display: block; margin-top: 3px;">All students in this upload will be mapped to this branch.</small>
                    </div>

                    <div class="form-group">
                        <label for="bulk_role"><i class="fas fa-user-tag"></i> 2. Choose Role *</label>
                        <select id="bulk_role" name="bulk_role" class="form-control" required onchange="toggleBulkAdminType(); onBatchConfigChange();">
                            <option value="user" selected>User (Standard Student / Member)</option>
                            <option value="admin">Admin</option>
                        </select>
                        <small style="color: #64748b; display: block; margin-top: 3px;">Sets default permissions for all imported accounts.</small>
                    </div>

                    <div class="form-group">
                        <label for="bulk_classification"><i class="fas fa-tags"></i> 3. Choose Classification *</label>
                        <select id="bulk_classification" name="bulk_classification" class="form-control" required onchange="onBatchConfigChange()">
                            <option value="Talabat" selected>Talabat</option>
                            <option value="Taalebaat">Taalebaat</option>
                            <option value="Muntasebeen">Muntasebeen</option>
                            <option value="Muntasebaat">Muntasebaat</option>
                        </select>
                        <small style="color: #64748b; display: block; margin-top: 3px;">Applied to all students in this import batch.</small>
                    </div>
                </div>

                <div class="form-group" id="bulk_admin_type_group" style="display: none; margin-top: 1rem;">
                    <label for="bulk_admin_type"><i class="fas fa-user-shield"></i> Admin Type Assignment</label>
                    <select id="bulk_admin_type" name="bulk_admin_type" class="form-control" style="max-width: 400px;">
                        <option value="">Select Specific Admin Role</option>
                        <option value="amali_coordinator">Amali Coordinator</option>
                        <option value="surat_amali_coordinator">Surat Amali Coordinator</option>
                        <option value="marol_amali_coordinator">Marol Amali Coordinator</option>
                        <option value="karachi_amali_coordinator">Karachi Amali Coordinator</option>
                        <option value="nairobi_amali_coordinator">Nairobi Amali Coordinator</option>
                    </select>
                </div>
            </div>

            <!-- STEP 2: TEMPLATE GUIDELINES & EXCEL FILE UPLOAD -->
            <div class="step-card">
                <div class="step-card-header">
                    <h3><span class="step-badge">2</span> Excel Spreadsheet Upload (.xlsx / .xls only)</h3>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadExcelTemplate()">
                        <i class="fas fa-file-excel"></i> Download Excel Template (.xlsx)
                    </button>
                </div>

                <div style="background: #f8fafc; border-left: 4px solid var(--primary-500, #064e3b); padding: 0.85rem 1rem; border-radius: 6px; margin-bottom: 1.25rem;">
                    <h4 style="margin: 0 0 0.4rem 0; color: #1e293b; font-size: 0.95rem;">
                        <i class="fas fa-info-circle text-primary"></i> Exact 5 Column Headers Specification:
                    </h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #475569;">
                        Please ensure your Excel spreadsheet contains the following 5 columns only:
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        <span class="badge badge-secondary" style="font-family: monospace;">TR Number *</span>
                        <span class="badge badge-secondary" style="font-family: monospace;">ITS Number *</span>
                        <span class="badge badge-secondary" style="font-family: monospace;">Full Name *</span>
                        <span class="badge badge-secondary" style="font-family: monospace;">Email *</span>
                        <span class="badge badge-secondary" style="font-family: monospace;">Phone Number (Optional)</span>
                    </div>
                    <small style="display: block; margin-top: 0.5rem; color: #64748b; line-height: 1.4;">
                        <i class="fas fa-key"></i> <strong>Password Security Notice:</strong> Each student's initial password is automatically set to their <strong>TR Number</strong>.
                    </small>
                </div>

                <div class="dropzone-box" id="dropzoneBox" onclick="document.getElementById('fileInput').click()">
                    <input type="file" id="fileInput" name="bulk_file" class="dropzone-input" accept=".xlsx, .xls" onchange="handleFileSelected(this.files)">
                    <div class="dropzone-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="dropzone-text" id="dropzonePromptText">Click or Drag & Drop Excel spreadsheet (.xlsx, .xls) here</div>
                    <div class="dropzone-subtext">Real-time duplicate validation will run automatically upon file selection</div>
                </div>

                <div id="fileInfoBadge" style="display: none; margin-top: 0.85rem; padding: 0.65rem 0.85rem; background: #e0f2fe; border: 1px solid #bae6fd; border-radius: var(--radius-md, 8px); color: #0369a1; font-size: 0.88rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <i class="fas fa-file-excel"></i> <span id="fileNameDisplay"><strong>Filename.xlsx</strong></span>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 3px 10px; font-size: 0.75rem;" onclick="clearUploadedFile()">
                            <i class="fas fa-times"></i> Change File
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: LIVE PRE-IMPORT VALIDATION & DUPLICATE REVIEW -->
            <div class="step-card" id="validationReviewCard" style="display: none;">
                <div class="step-card-header">
                    <h3><span class="step-badge">3</span> Meticulous Duplicate & Data Review</h3>
                    <div class="review-filter-row">
                        <input type="text" id="filterReviewInput" class="form-control" placeholder="Search preview rows..." style="width: 200px; padding: 5px 10px; font-size: 0.85rem;" onkeyup="filterReviewRows()">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="revalidateData()">
                            <i class="fas fa-sync-alt"></i> Re-Check Validation
                        </button>
                    </div>
                </div>

                <!-- Stat Pills -->
                <div class="stats-summary-grid">
                    <div class="stat-pill total">
                        <div class="stat-pill-icon"><i class="fas fa-list-ol"></i></div>
                        <div class="stat-pill-content">
                            <h4 id="statTotalRows">0</h4>
                            <span>Total Rows Detected</span>
                        </div>
                    </div>
                    <div class="stat-pill valid">
                        <div class="stat-pill-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-pill-content">
                            <h4 id="statValidRows">0</h4>
                            <span>Ready to Import</span>
                        </div>
                    </div>
                    <div class="stat-pill error">
                        <div class="stat-pill-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="stat-pill-content">
                            <h4 id="statErrorRows">0</h4>
                            <span>Issues / Conflicts</span>
                        </div>
                    </div>
                </div>

                <!-- Conflict Alert Banner -->
                <div id="conflictAlertBanner" style="display: none; padding: 0.85rem 1rem; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: var(--radius-md, 8px); margin-bottom: 1rem; color: #991b1b; font-size: 0.9rem;">
                    <strong style="display: block; margin-bottom: 0.25rem;"><i class="fas fa-shield-alt"></i> Duplicate or Data Conflicts Detected!</strong>
                    <span id="conflictAlertDetails">Some TR Numbers or ITS Numbers already exist or are repeated in this spreadsheet. Please resolve highlighted rows below before proceeding.</span>
                </div>

                <!-- Mobile Horizontal Scroll Hint -->
                <div class="mobile-scroll-hint">
                    <i class="fas fa-arrows-left-right"></i> Scroll table horizontally to view full student details
                </div>

                <!-- Review Table -->
                <div class="review-table-container">
                    <table class="table-striped">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;">#</th>
                                <th style="width: 110px;">TR Number</th>
                                <th style="width: 120px;">ITS Number</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th style="width: 130px;">Phone</th>
                                <th style="text-align: center; width: 110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="reviewTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Import Actions -->
                <div class="bulk-footer-actions">
                    <div style="font-size: 0.85rem; color: #64748b;">
                        <i class="fas fa-info-circle"></i> Only clean batches with zero unresolved conflicts can be committed to the database.
                    </div>
                    <div class="bulk-action-btns">
                        <button type="button" class="btn btn-secondary" onclick="clearUploadedFile()">
                            <i class="fas fa-times"></i> Discard Batch
                        </button>
                        <button type="submit" id="submitBulkBtn" class="btn btn-primary" onclick="return confirmBulkSubmit()">
                            <i class="fas fa-file-upload"></i> Execute Bulk Import (<span id="btnImportCount">0</span> Students)
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Fullscreen Spinner Overlay -->
<div class="loading-spinner-overlay" id="loadingOverlay">
    <div class="spinner-circle"></div>
    <h3 id="loadingText" style="margin: 0; font-size: 1.15rem; font-weight: 600;">Validating student records...</h3>
    <p style="color: #cbd5e1; font-size: 0.85rem; margin-top: 6px;">Please wait while the system checks database uniqueness</p>
</div>

<script>
let parsedRawRows = [];
let lastValidatedData = null;

// Download Pure Excel Template (.xlsx) via SheetJS
function downloadExcelTemplate() {
    if (typeof XLSX === 'undefined') {
        window.location.href = 'download_user_template.php';
        return;
    }
    const wb = XLSX.utils.book_new();
    const wsData = [
        ["TR Number", "ITS Number", "Full Name", "Email", "Phone Number"],
        ["26001", "30450001", "Murtaza Bhai Shabbir Bhai", "26001@jameasaifiyah.edu", "+919876543210"],
        ["26002", "30450002", "Fatema Bai Aliasgar Bhai", "26002@jameasaifiyah.edu", "+919876543211"]
    ];
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!cols'] = [
        { wch: 15 }, // TR Number
        { wch: 15 }, // ITS Number
        { wch: 35 }, // Full Name
        { wch: 30 }, // Email
        { wch: 20 }  // Phone Number
    ];
    XLSX.utils.book_append_sheet(wb, ws, "Students");
    XLSX.writeFile(wb, "ziyafat_student_bulk_import_template.xlsx");
}

// Tab Switching
function switchTab(tab) {
    document.querySelectorAll('.import-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    
    if (tab === 'single') {
        document.querySelector('.import-tab-btn:nth-child(1)').classList.add('active');
        document.getElementById('tab-single').style.display = 'block';
    } else {
        document.querySelector('.import-tab-btn:nth-child(2)').classList.add('active');
        document.getElementById('tab-bulk').style.display = 'block';
    }
}

// Single User Admin Type Toggle
function toggleSingleAdminType() {
    var role = document.getElementById('role').value;
    var group = document.getElementById('single_admin_type_group');
    if (role === 'admin') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        document.getElementById('admin_type').value = '';
    }
}

// Bulk User Admin Type Toggle
function toggleBulkAdminType() {
    var role = document.getElementById('bulk_role').value;
    var group = document.getElementById('bulk_admin_type_group');
    if (role === 'admin') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        document.getElementById('bulk_admin_type').value = '';
    }
}

function onBatchConfigChange() {
    if (parsedRawRows.length > 0) {
        validateRowsWithBackend(parsedRawRows);
    }
}

// Drag & Drop event handlers
const dropzone = document.getElementById('dropzoneBox');
['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    }, false);
});
['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
    }, false);
});
dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files.length) {
        handleFileSelected(files);
    }
});

// File Selection & Parsing Handler (Excel Only)
function handleFileSelected(files) {
    if (!files || !files.length) return;
    const file = files[0];
    
    const validExtensions = ['.xlsx', '.xls'];
    const fileName = file.name.toLowerCase();
    const isValid = validExtensions.some(ext => fileName.endsWith(ext));
    
    if (!isValid) {
        alert('CSV files are not supported. Please upload an Excel spreadsheet (.xlsx or .xls) only.');
        clearUploadedFile();
        return;
    }

    document.getElementById('fileNameDisplay').innerHTML = `<strong>${escapeHtml(file.name)}</strong> (${(file.size / 1024).toFixed(1)} KB)`;
    document.getElementById('fileInfoBadge').style.display = 'block';
    document.getElementById('dropzonePromptText').textContent = 'Excel File Loaded: ' + file.name;

    showLoading(true, 'Reading and parsing Excel spreadsheet...');

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Read first worksheet
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            // Convert to JSON with headers
            const rawJson = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
            
            if (!rawJson || rawJson.length < 2) {
                showLoading(false);
                alert('Uploaded Excel spreadsheet appears to be empty or missing data rows.');
                return;
            }

            // Map Column Headers (5 standard columns)
            const headerRow = rawJson[0];
            const headerMap = {};
            headerRow.forEach((colName, idx) => {
                const norm = String(colName || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                if (['trnumber', 'trno', 'tr', 'rollno', 'studenttr'].includes(norm)) {
                    headerMap.tr_number = idx;
                } else if (['itsnumber', 'itsno', 'its', 'itsid'].includes(norm)) {
                    headerMap.its_number = idx;
                } else if (['fullname', 'name', 'studentname'].includes(norm)) {
                    headerMap.name = idx;
                } else if (['email', 'emailaddress', 'mail'].includes(norm)) {
                    headerMap.email = idx;
                } else if (['phonenumber', 'phone', 'mobile', 'contact'].includes(norm)) {
                    headerMap.phone_number = idx;
                }
            });

            parsedRawRows = [];
            for (let i = 1; i < rawJson.length; i++) {
                const row = rawJson[i];
                // Skip empty lines
                if (!row || !row.some(cell => String(cell).trim() !== '')) continue;

                parsedRawRows.push({
                    tr_number: headerMap.tr_number !== undefined ? String(row[headerMap.tr_number] || '').trim() : '',
                    its_number: headerMap.its_number !== undefined ? String(row[headerMap.its_number] || '').trim() : '',
                    name: headerMap.name !== undefined ? String(row[headerMap.name] || '').trim() : '',
                    email: headerMap.email !== undefined ? String(row[headerMap.email] || '').trim() : '',
                    phone_number: headerMap.phone_number !== undefined ? String(row[headerMap.phone_number] || '').trim() : ''
                });
            }

            if (parsedRawRows.length === 0) {
                showLoading(false);
                alert('No student records found in the Excel spreadsheet.');
                return;
            }

            // Perform Backend AJAX Verification
            validateRowsWithBackend(parsedRawRows);

        } catch (err) {
            showLoading(false);
            console.error(err);
            alert('Error parsing Excel spreadsheet: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

// Real-Time AJAX Validation against Backend and MySQL DB
function validateRowsWithBackend(rows) {
    const branch = document.getElementById('bulk_branch').value;
    const role = document.getElementById('bulk_role').value;
    const classification = document.getElementById('bulk_classification').value;
    const csrfToken = document.getElementById('bulk_csrf_token').value;

    if (!branch) {
        showLoading(false);
        document.getElementById('validationReviewCard').style.display = 'block';
        alert('Please select a Branch (Jamea) in Step 1 before proceeding.');
        document.getElementById('bulk_branch').focus();
        return;
    }

    if (!classification) {
        showLoading(false);
        document.getElementById('validationReviewCard').style.display = 'block';
        alert('Please select a Classification in Step 1 before proceeding.');
        document.getElementById('bulk_classification').focus();
        return;
    }

    showLoading(true, 'Checking database for duplicate TR, ITS, and Email records...');

    fetch('ajax_validate_bulk_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            csrf_token: csrfToken,
            branch: branch,
            role: role,
            classification: classification,
            rows: rows
        })
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        if (!result.success) {
            alert('Validation check failed: ' + result.message);
            return;
        }

        lastValidatedData = result;
        renderReviewTable(result);
    })
    .catch(err => {
        showLoading(false);
        console.error(err);
        alert('Failed to connect to validation server: ' + err.message);
    });
}

// Render Review Table & Stats
function renderReviewTable(result) {
    const card = document.getElementById('validationReviewCard');
    card.style.display = 'block';

    document.getElementById('statTotalRows').textContent = result.total_rows;
    document.getElementById('statValidRows').textContent = result.valid_count;
    document.getElementById('statErrorRows').textContent = result.error_count;
    document.getElementById('btnImportCount').textContent = result.valid_count;

    const banner = document.getElementById('conflictAlertBanner');
    const submitBtn = document.getElementById('submitBulkBtn');

    if (result.error_count > 0) {
        banner.style.display = 'block';
        let detailText = `Detected ${result.error_count} issue(s): `;
        if (result.file_conflicts_count > 0) detailText += `${result.file_conflicts_count} in-file duplicate(s); `;
        if (result.db_conflicts_count > 0) detailText += `${result.db_conflicts_count} database conflict(s); `;
        document.getElementById('conflictAlertDetails').textContent = detailText + 'Please resolve highlighted issues before importing.';
        
        submitBtn.disabled = true;
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-secondary');
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        banner.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-secondary');
        submitBtn.classList.add('btn-primary');
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    }

    // Set serialized data into hidden input for submission
    document.getElementById('bulk_json_data').value = JSON.stringify(result.validated_rows);

    // Build Table Rows
    const tbody = document.getElementById('reviewTableBody');
    tbody.innerHTML = '';

    result.validated_rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'review-row';
        tr.dataset.search = `${row.tr_number} ${row.its_number} ${row.name} ${row.email}`.toLowerCase();

        const hasError = row.status === 'error';
        if (hasError) {
            tr.style.backgroundColor = 'rgba(239, 68, 68, 0.07)';
        }

        let statusBadge = '';
        if (!hasError) {
            statusBadge = '<span class="table-badge badge-valid"><i class="fas fa-check"></i> Valid</span>';
        } else {
            statusBadge = '<span class="table-badge badge-error"><i class="fas fa-times-circle"></i> Issue</span>';
        }

        let errorDetails = '';
        if (hasError && row.errors && row.errors.length) {
            errorDetails = `<div class="error-list-tooltip"><i class="fas fa-exclamation-triangle"></i> ${row.errors.join('<br><i class="fas fa-exclamation-triangle"></i> ')}</div>`;
        }

        tr.innerHTML = `
            <td style="text-align: center; color: #64748b; font-weight: 600;">${row.row_num}</td>
            <td style="font-weight: 600; color: #1e293b; white-space: nowrap;">
                ${row.tr_number ? escapeHtml(row.tr_number) : '<span style="color: #ef4444;">[Missing]</span>'}
            </td>
            <td style="font-family: monospace; white-space: nowrap;">
                ${row.its_number ? escapeHtml(row.its_number) : '<span style="color: #ef4444;">[Missing]</span>'}
            </td>
            <td>
                <strong>${row.name ? escapeHtml(row.name) : '<span style="color: #ef4444;">[Missing Name]</span>'}</strong>
                ${errorDetails}
            </td>
            <td style="color: #475569; word-break: break-all;">
                ${row.email ? escapeHtml(row.email) : '<span style="color: #ef4444;">[Missing]</span>'}
            </td>
            <td style="color: #64748b; white-space: nowrap;">${row.phone_number ? escapeHtml(row.phone_number) : '—'}</td>
            <td style="text-align: center;">${statusBadge}</td>
        `;
        tbody.appendChild(tr);
    });
}

function filterReviewRows() {
    const q = document.getElementById('filterReviewInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.review-row');
    rows.forEach(r => {
        if (!q || r.dataset.search.includes(q)) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function revalidateData() {
    if (parsedRawRows.length > 0) {
        validateRowsWithBackend(parsedRawRows);
    }
}

function clearUploadedFile() {
    parsedRawRows = [];
    lastValidatedData = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('bulk_json_data').value = '';
    document.getElementById('fileInfoBadge').style.display = 'none';
    document.getElementById('dropzonePromptText').textContent = 'Click or Drag & Drop Excel spreadsheet (.xlsx, .xls) here';
    document.getElementById('validationReviewCard').style.display = 'none';
}

function confirmBulkSubmit() {
    if (!lastValidatedData || lastValidatedData.valid_count === 0) {
        alert('No valid student records are ready for import.');
        return false;
    }
    if (lastValidatedData.error_count > 0) {
        alert(`Cannot proceed: There are ${lastValidatedData.error_count} unresolved data conflicts or duplicate records in this spreadsheet. Please fix and re-upload.`);
        return false;
    }
    
    const branch = document.getElementById('bulk_branch').value;
    const classification = document.getElementById('bulk_classification').value;
    return confirm(`Are you sure you want to import ${lastValidatedData.valid_count} students into the "${branch}" branch with classification "${classification}"?\n\nPasswords will be initialized to each student's TR Number.`);
}

function showLoading(show, msg) {
    const overlay = document.getElementById('loadingOverlay');
    if (show) {
        document.getElementById('loadingText').textContent = msg || 'Processing...';
        overlay.classList.add('show');
    } else {
        overlay.classList.remove('show');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once '../includes/footer.php'; ?>