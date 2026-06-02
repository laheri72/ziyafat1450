<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

// Allow only super admin and global amali coordinator-level access.
if (!can_manage_amali_masters()) {
    header('Location: index.php');
    exit();
}

$page_title = 'Manage Books';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$error = '';
$success = '';

// Handle Add/Edit/Delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $book_name = clean_input($_POST['book_name']);
        $book_name_arabic = clean_input($_POST['book_name_arabic']);
        $author = clean_input($_POST['author']);
        $total_pages = intval($_POST['total_pages']);
        $description = clean_input($_POST['description']);
        
        $sql = "INSERT INTO books_master (book_name, book_name_arabic, author, total_pages, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssis", $book_name, $book_name_arabic, $author, $total_pages, $description);
        
        if ($stmt->execute()) {
            $success = 'Book added successfully!';
        } else {
            $error = 'Failed to add book.';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $book_name = clean_input($_POST['book_name']);
        $book_name_arabic = clean_input($_POST['book_name_arabic']);
        $author = clean_input($_POST['author']);
        $total_pages = intval($_POST['total_pages']);
        $description = clean_input($_POST['description']);
        
        $sql = "UPDATE books_master SET book_name = ?, book_name_arabic = ?, author = ?, total_pages = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisi", $book_name, $book_name_arabic, $author, $total_pages, $description, $id);
        
        if ($stmt->execute()) {
            $success = 'Book updated successfully!';
        } else {
            $error = 'Failed to update book.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE books_master SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Book deactivated successfully!';
        } else {
            $error = 'Failed to deactivate book.';
        }
    } elseif ($action === 'activate') {
        $id = intval($_POST['id']);
        
        $sql = "UPDATE books_master SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = 'Book activated successfully!';
        } else {
            $error = 'Failed to activate book.';
        }
    } elseif ($action === 'assign_book') {
        $book_id = intval($_POST['book_id']);
        $user_id = intval($_POST['user_id']);

        $check_sql = "SELECT id, status FROM book_transcription WHERE book_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $book_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();

        if ($existing && in_array($existing['status'], ['selected', 'completed'], true)) {
            $error = 'This book is already tagged to a user. Revoke the current access first.';
        } else {
            if ($existing) {
                $sql = "UPDATE book_transcription
                        SET user_id = ?, pages_completed = 0, started_date = CURDATE(), completed_date = NULL, status = 'selected', notes = NULL
                        WHERE book_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $book_id);
            } else {
                $sql = "INSERT INTO book_transcription (user_id, book_id, pages_completed, started_date, status)
                        VALUES (?, ?, 0, CURDATE(), 'selected')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $user_id, $book_id);
            }

            if ($stmt->execute()) {
                $success = 'Book assigned successfully!';
            } else {
                $error = 'Failed to assign book.';
            }
        }
    } elseif ($action === 'approve_request') {
        $request_id = intval($_POST['request_id']);

        $request_sql = "SELECT * FROM book_transcription_requests WHERE id = ? AND request_status = 'pending'";
        $request_stmt = $conn->prepare($request_sql);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request = $request_stmt->get_result()->fetch_assoc();

        if (!$request) {
            $error = 'Request not found or already reviewed.';
        } else {
            $book_check_sql = "SELECT id, status FROM book_transcription WHERE book_id = ?";
            $book_check_stmt = $conn->prepare($book_check_sql);
            $book_check_stmt->bind_param("i", $request['book_id']);
            $book_check_stmt->execute();
            $existing = $book_check_stmt->get_result()->fetch_assoc();

            if ($existing && in_array($existing['status'], ['selected', 'completed'], true)) {
                $error = 'This book is already tagged to a user.';
            } else {
                if ($existing) {
                    $assign_sql = "UPDATE book_transcription
                                   SET user_id = ?, pages_completed = 0, started_date = CURDATE(), completed_date = NULL, status = 'selected', notes = NULL
                                   WHERE book_id = ?";
                    $assign_stmt = $conn->prepare($assign_sql);
                    $assign_stmt->bind_param("ii", $request['user_id'], $request['book_id']);
                } else {
                    $assign_sql = "INSERT INTO book_transcription (user_id, book_id, pages_completed, started_date, status)
                                   VALUES (?, ?, 0, CURDATE(), 'selected')";
                    $assign_stmt = $conn->prepare($assign_sql);
                    $assign_stmt->bind_param("ii", $request['user_id'], $request['book_id']);
                }

                if ($assign_stmt->execute()) {
                    $review_sql = "UPDATE book_transcription_requests
                                   SET request_status = 'approved', reviewed_at = NOW(), reviewed_by = ?, review_notes = NULL
                                   WHERE id = ?";
                    $review_stmt = $conn->prepare($review_sql);
                    $reviewer_id = $_SESSION['user_id'];
                    $review_stmt->bind_param("ii", $reviewer_id, $request_id);
                    if ($review_stmt->execute()) {
                        $success = 'Request approved and book assigned.';
                    } else {
                        $error = 'Book assigned, but request review could not be saved.';
                    }
                } else {
                    $error = 'Failed to approve request.';
                }
            }
        }
    } elseif ($action === 'reject_request') {
        $request_id = intval($_POST['request_id']);
        $review_sql = "UPDATE book_transcription_requests
                       SET request_status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, review_notes = NULL
                       WHERE id = ? AND request_status = 'pending'";
        $review_stmt = $conn->prepare($review_sql);
        $reviewer_id = $_SESSION['user_id'];
        $review_stmt->bind_param("ii", $reviewer_id, $request_id);

        if ($review_stmt->execute() && $review_stmt->affected_rows > 0) {
            $success = 'Request rejected.';
        } else {
            $error = 'Failed to reject request.';
        }
    } elseif ($action === 'revoke_assignment') {
        $book_id = intval($_POST['book_id'] ?? $_POST['id'] ?? 0);
        $sql = "UPDATE book_transcription SET status = 'revoked' WHERE book_id = ? AND status IN ('selected', 'completed')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = 'Access revoked successfully.';
        } else {
            $error = 'Failed to revoke access.';
        }
    }
}

// Dashboard data
$books = get_books_with_assignments($conn);
$available_books = get_available_books($conn);
$pending_requests = get_pending_book_requests($conn);
$all_users = $conn->query("SELECT id, name, its_number FROM users ORDER BY name ASC");

$active_books_result = $conn->query("SELECT 
        COUNT(*) AS total_books,
        SUM(CASE WHEN bt.id IS NULL THEN 1 ELSE 0 END) AS available_books,
        SUM(CASE WHEN bt.id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_books
    FROM books_master bm
    LEFT JOIN book_transcription bt ON bt.book_id = bm.id AND bt.status IN ('selected', 'completed')
    WHERE bm.is_active = 1");
$active_books_stats = $active_books_result ? $active_books_result->fetch_assoc() : ['total_books' => 0, 'available_books' => 0, 'assigned_books' => 0];
$pending_request_count = $pending_requests ? $pending_requests->num_rows : 0;

require_once '../includes/header.php';
?>

<style>
    /* Professional Card Distinction - Permanent Borders */
    .card {
        border: 1px solid #243b53 !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
        margin-bottom: 25px !important;
        background: #ffffff !important;
    }

    .card-header {
        background: #f8fafc !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        border-color: var(--primary-500) !important;
    }
    
    .stat-card {
        border: 1px solid #243b53 !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12) !important;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        border-color: var(--primary-500) !important;
    }
</style>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-book"></i> Manage Books (Istinsakh ul Kutub)</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <h4>Active Books</h4>
                <div class="stat-icon"><i class="fas fa-book"></i></div>
            </div>
            <div class="stat-value"><?php echo (int)($active_books_stats['total_books'] ?? 0); ?></div>
            <div class="stat-label">Published kutub</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <h4>Tagged Kutub</h4>
                <div class="stat-icon"><i class="fas fa-user-tag"></i></div>
            </div>
            <div class="stat-value"><?php echo (int)($active_books_stats['assigned_books'] ?? 0); ?></div>
            <div class="stat-label">Currently assigned</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <h4>Available</h4>
                <div class="stat-icon"><i class="fas fa-unlock"></i></div>
            </div>
            <div class="stat-value"><?php echo (int)($active_books_stats['available_books'] ?? 0); ?></div>
            <div class="stat-label">Ready for request</div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <h4>Pending Requests</h4>
                <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            </div>
            <div class="stat-value"><?php echo (int)$pending_request_count; ?></div>
            <div class="stat-label">Awaiting review</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Assign Kutub Manually</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <form method="POST" action="" class="action-buttons" style="align-items: flex-end; gap: 12px;">
                <input type="hidden" name="action" value="assign_book">
                <div class="form-group" style="flex: 1; min-width: 220px; margin-bottom: 0;">
                    <label>Available Book</label>
                    <select name="book_id" class="form-control" required>
                        <option value="">-- Choose an available book --</option>
                        <?php if ($available_books && $available_books->num_rows > 0): ?>
                            <?php while ($book = $available_books->fetch_assoc()): ?>
                                <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['book_name']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; min-width: 220px; margin-bottom: 0;">
                    <label>User</label>
                    <select name="user_id" class="form-control" required>
                        <option value="">-- Choose a user --</option>
                        <?php if ($all_users && $all_users->num_rows > 0): ?>
                            <?php while ($user = $all_users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['its_number']); ?>)</option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-link"></i> Tag User to Book
                </button>
            </form>
        </div>
    </div>

    <div class="card" id="pending-requests">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Book Requests</h3>
        </div>
        <div class="table-container">
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Requested</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $pending_requests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['user_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($request['user_its']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['book_name']); ?></strong><br>
                                    <small><?php echo $request['total_pages']; ?> pages</small>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this request and assign the book?');">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this request?');">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No pending requests right now.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add New Book Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> Add New Book</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="book_name"><i class="fas fa-book"></i> Book Name (English) *</label>
                <input type="text" id="book_name" name="book_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="book_name_arabic"><i class="fas fa-language"></i> Book Name (Arabic)</label>
                <input type="text" id="book_name_arabic" name="book_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="author"><i class="fas fa-user-edit"></i> Author *</label>
                <input type="text" id="author" name="author" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="total_pages"><i class="fas fa-file-alt"></i> Total Pages *</label>
                <input type="number" id="total_pages" name="total_pages" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="description"><i class="fas fa-info-circle"></i> Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Add Book
            </button>
        </form>
    </div>

    <!-- Books List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Kutub Assignment Overview</h3>
        </div>
        <div class="table-container">
            <?php if ($books->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book Name</th>
                            <th>Assigned User</th>
                            <th>Progress</th>
                            <th>State</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                            <?php
                                $is_assigned = !empty($book['assigned_user_id']);
                                $pages_completed = (int)($book['pages_completed'] ?? 0);
                                $total_pages = (int)($book['total_pages'] ?? 0);
                                $progress_pct = $total_pages > 0 ? round(($pages_completed / $total_pages) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td><?php echo $book['id']; ?></td>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td>
                                    <?php if ($is_assigned): ?>
                                        <strong><?php echo htmlspecialchars($book['assigned_user_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($book['assigned_user_its']); ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_assigned): ?>
                                        <div class="progress-bar" style="height: 8px; margin-bottom: 0.35rem;">
                                            <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%;"></div>
                                        </div>
                                        <small><?php echo $pages_completed; ?> / <?php echo $total_pages; ?> pages</small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">Not started</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$book['is_active']): ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php elseif ($is_assigned): ?>
                                        <span class="badge badge-success"><?php echo ucfirst($book['assignment_status']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick='editBook(<?php echo json_encode($book, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($is_assigned): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this book?');">
                                            <input type="hidden" name="action" value="revoke_assignment">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-user-slash"></i> Revoke Access
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">Use manual assignment above or a request approval.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No books found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:white; margin:50px auto; padding:20px; max-width:600px; border-radius:8px;">
        <h3><i class="fas fa-edit"></i> Edit Book</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label for="edit_book_name">Book Name (English) *</label>
                <input type="text" id="edit_book_name" name="book_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_book_name_arabic">Book Name (Arabic)</label>
                <input type="text" id="edit_book_name_arabic" name="book_name_arabic" class="form-control" dir="rtl">
            </div>

            <div class="form-group">
                <label for="edit_author">Author *</label>
                <input type="text" id="edit_author" name="author" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="edit_total_pages">Total Pages *</label>
                <input type="number" id="edit_total_pages" name="total_pages" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update
                </button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editBook(book) {
    document.getElementById('edit_id').value = book.id;
    document.getElementById('edit_book_name').value = book.book_name;
    document.getElementById('edit_book_name_arabic').value = book.book_name_arabic || '';
    document.getElementById('edit_author').value = book.author;
    document.getElementById('edit_total_pages').value = book.total_pages;
    document.getElementById('edit_description').value = book.description || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>