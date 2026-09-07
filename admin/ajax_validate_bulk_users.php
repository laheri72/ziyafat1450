<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

init_session();
header('Content-Type: application/json');

if (!is_logged_in() || !is_super_admin()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only Super Admins can perform bulk validation.'
    ]);
    exit();
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON request payload.'
    ]);
    exit();
}

$csrf_token = $input['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Security token expired or invalid. Please refresh the page.'
    ]);
    exit();
}

$rows = $input['rows'] ?? [];
$branch = clean_input($input['branch'] ?? '');
$role = clean_input($input['role'] ?? 'user');
$classification = clean_input($input['classification'] ?? $input['default_classification'] ?? 'Talabat');

$allowed_branches = ['Surat', 'Marol', 'Karachi', 'Nairobi'];
$allowed_classifications = ['Talabat', 'Taalebaat', 'Muntasebeen', 'Muntasebaat'];

if (empty($branch) || !in_array($branch, $allowed_branches)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid Branch (Jamea) from the pre-import settings.'
    ]);
    exit();
}

if (empty($classification) || !in_array($classification, $allowed_classifications)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid Classification from the pre-import settings.'
    ]);
    exit();
}

if (empty($rows)) {
    echo json_encode([
        'success' => false,
        'message' => 'No user records received to validate.'
    ]);
    exit();
}

$total_rows = count($rows);
$validated_rows = [];
$tr_occurrences = [];
$its_occurrences = [];
$email_occurrences = [];

$all_tr = [];
$all_its = [];
$all_emails = [];

// First Pass: Clean data and track in-file duplicates
foreach ($rows as $index => $row) {
    $row_num = $index + 1;
    $tr = trim((string)($row['tr_number'] ?? ''));
    $its = trim((string)($row['its_number'] ?? ''));
    $name = trim((string)($row['name'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $phone = trim((string)($row['phone_number'] ?? ''));

    $validated_rows[$index] = [
        'row_num' => $row_num,
        'tr_number' => $tr,
        'its_number' => $its,
        'name' => $name,
        'email' => $email,
        'phone_number' => $phone,
        'classification' => $classification,
        'category' => $branch,
        'role' => $role,
        'status' => 'valid',
        'errors' => []
    ];

    // Missing field checks
    if ($tr === '') {
        $validated_rows[$index]['errors'][] = 'TR Number is required.';
    } else {
        $tr_lower = strtolower($tr);
        if (!isset($tr_occurrences[$tr_lower])) {
            $tr_occurrences[$tr_lower] = [];
        }
        $tr_occurrences[$tr_lower][] = $row_num;
        $all_tr[$tr] = true;
    }

    if ($its === '') {
        $validated_rows[$index]['errors'][] = 'ITS Number is required.';
    } else {
        $its_lower = strtolower($its);
        if (!isset($its_occurrences[$its_lower])) {
            $its_occurrences[$its_lower] = [];
        }
        $its_occurrences[$its_lower][] = $row_num;
        $all_its[$its] = true;

        if (!preg_match('/^[0-9]{6,12}$/', $its)) {
            $validated_rows[$index]['errors'][] = 'ITS Number should be 6-12 numeric digits.';
        }
    }

    if ($name === '') {
        $validated_rows[$index]['errors'][] = 'Full Name is required.';
    }

    if ($email === '') {
        $validated_rows[$index]['errors'][] = 'Email is required.';
    } else {
        $email_lower = strtolower($email);
        if (!isset($email_occurrences[$email_lower])) {
            $email_occurrences[$email_lower] = [];
        }
        $email_occurrences[$email_lower][] = $row_num;
        $all_emails[$email] = true;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validated_rows[$index]['errors'][] = 'Invalid email address format.';
        }
    }
}

// In-file duplicate flagging
$file_conflicts_count = 0;
foreach ($validated_rows as $index => &$vrow) {
    $tr_key = strtolower($vrow['tr_number']);
    if ($tr_key !== '' && isset($tr_occurrences[$tr_key]) && count($tr_occurrences[$tr_key]) > 1) {
        $other_rows = array_diff($tr_occurrences[$tr_key], [$vrow['row_num']]);
        $vrow['errors'][] = 'Duplicate TR Number in file (also on row ' . implode(', ', $other_rows) . ').';
        $file_conflicts_count++;
    }

    $its_key = strtolower($vrow['its_number']);
    if ($its_key !== '' && isset($its_occurrences[$its_key]) && count($its_occurrences[$its_key]) > 1) {
        $other_rows = array_diff($its_occurrences[$its_key], [$vrow['row_num']]);
        $vrow['errors'][] = 'Duplicate ITS Number in file (also on row ' . implode(', ', $other_rows) . ').';
        $file_conflicts_count++;
    }

    $email_key = strtolower($vrow['email']);
    if ($email_key !== '' && isset($email_occurrences[$email_key]) && count($email_occurrences[$email_key]) > 1) {
        $other_rows = array_diff($email_occurrences[$email_key], [$vrow['row_num']]);
        $vrow['errors'][] = 'Duplicate Email in file (also on row ' . implode(', ', $other_rows) . ').';
        $file_conflicts_count++;
    }
}
unset($vrow);

// Database Duplicate Checking
$db_its_map = [];
$db_tr_map = [];
$db_email_map = [];
$db_conflicts_count = 0;

if (!empty($all_its) || !empty($all_tr) || !empty($all_emails)) {
    $its_list = array_keys($all_its);
    $tr_list = array_keys($all_tr);
    $email_list = array_keys($all_emails);

    // Check ITS in DB
    if (!empty($its_list)) {
        $chunk_size = 500;
        $chunks = array_chunk($its_list, $chunk_size);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $types = str_repeat('s', count($chunk));
            $stmt = $conn->prepare("SELECT its_number, name FROM users WHERE its_number IN ($placeholders)");
            if ($stmt) {
                $stmt->bind_param($types, ...$chunk);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($db_row = $res->fetch_assoc()) {
                    $db_its_map[strtolower($db_row['its_number'])] = $db_row['name'];
                }
            }
        }
    }

    // Check TR in DB
    if (!empty($tr_list)) {
        $chunk_size = 500;
        $chunks = array_chunk($tr_list, $chunk_size);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $types = str_repeat('s', count($chunk));
            $stmt = $conn->prepare("SELECT tr_number, name, its_number FROM users WHERE tr_number IN ($placeholders) AND tr_number IS NOT NULL AND tr_number != ''");
            if ($stmt) {
                $stmt->bind_param($types, ...$chunk);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($db_row = $res->fetch_assoc()) {
                    $db_tr_map[strtolower($db_row['tr_number'])] = [
                        'name' => $db_row['name'],
                        'its' => $db_row['its_number']
                    ];
                }
            }
        }
    }

    // Check Email in DB
    if (!empty($email_list)) {
        $chunk_size = 500;
        $chunks = array_chunk($email_list, $chunk_size);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $types = str_repeat('s', count($chunk));
            $stmt = $conn->prepare("SELECT email, name, its_number FROM users WHERE email IN ($placeholders) AND email IS NOT NULL AND email != ''");
            if ($stmt) {
                $stmt->bind_param($types, ...$chunk);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($db_row = $res->fetch_assoc()) {
                    $db_email_map[strtolower($db_row['email'])] = [
                        'name' => $db_row['name'],
                        'its' => $db_row['its_number']
                    ];
                }
            }
        }
    }
}

// Second Pass: Attach DB conflicts
$valid_count = 0;
$error_count = 0;

foreach ($validated_rows as $index => &$vrow) {
    $its_key = strtolower($vrow['its_number']);
    if ($its_key !== '' && isset($db_its_map[$its_key])) {
        $vrow['errors'][] = 'ITS Number already exists in database (User: "' . $db_its_map[$its_key] . '").';
        $db_conflicts_count++;
    }

    $tr_key = strtolower($vrow['tr_number']);
    if ($tr_key !== '' && isset($db_tr_map[$tr_key])) {
        $vrow['errors'][] = 'TR Number already exists in database (User: "' . $db_tr_map[$tr_key]['name'] . '", ITS: ' . $db_tr_map[$tr_key]['its'] . ').';
        $db_conflicts_count++;
    }

    $email_key = strtolower($vrow['email']);
    if ($email_key !== '' && isset($db_email_map[$email_key])) {
        $vrow['errors'][] = 'Email already registered in database (User: "' . $db_email_map[$email_key]['name'] . '", ITS: ' . $db_email_map[$email_key]['its'] . ').';
        $db_conflicts_count++;
    }

    if (!empty($vrow['errors'])) {
        $vrow['status'] = 'error';
        $error_count++;
    } else {
        $vrow['status'] = 'valid';
        $valid_count++;
    }
}
unset($vrow);

echo json_encode([
    'success' => true,
    'total_rows' => $total_rows,
    'valid_count' => $valid_count,
    'error_count' => $error_count,
    'file_conflicts_count' => $file_conflicts_count,
    'db_conflicts_count' => $db_conflicts_count,
    'can_import' => ($error_count === 0 && $valid_count > 0),
    'branch' => $branch,
    'role' => $role,
    'validated_rows' => $validated_rows
]);
exit();
