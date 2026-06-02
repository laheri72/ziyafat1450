<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Book Transcription (Istinsakh ul Kutub)';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];

// Get user's assigned books with page tracking
$my_books = get_book_progress_with_pages($conn, $user_id);

// Get user's pending requests
$pending_requests = get_user_pending_book_requests($conn, $user_id);

// Get all available books that are not yet tagged to any user
$all_books = get_available_books($conn);

$has_assigned_books = user_has_active_book_assignment($conn, $user_id);
$has_pending_requests = ($pending_requests->num_rows > 0);

// Create array of selected book IDs
$selected_book_ids = [];
$my_books->data_seek(0);
while ($book = $my_books->fetch_assoc()) {
    $selected_book_ids[] = $book['id'];
}
$my_books->data_seek(0);

require_once '../includes/header.php';
?>

<style>
    /* Professional Card Distinction */
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

    .book-card {
        margin-bottom: 25px !important; /* overrides previous 20px */
    }
    .book-grid-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .empty-state {
        padding: 1rem 1.25rem;
        border: 1px dashed var(--border-color);
        border-radius: var(--radius-lg);
        background: var(--bg-secondary);
        color: var(--text-secondary);
    }
    @media (max-width: 480px) {
        .book-header {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .book-arabic-name {
            font-size: 1.25rem !important;
        }
    }
</style>

<div class="container">
    <div class="page-header">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; color: #666; text-decoration: none; font-weight: 500; font-size: 14px; padding: 6px 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e0e0e0; transition: all 0.2s;">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <h1><i class="fas fa-book"></i> Istinsakh ul Kutub</h1>
        <p>Select and track your book transcription progress</p>
    </div>

    <!-- Pending Requests Section -->
    <?php if ($has_pending_requests): ?>
        <div id="pending-requests-container" class="mb-4">
            <h2 class="mb-3" style="font-size: 1.25rem; color: var(--warning-700);"><i class="fas fa-clock-rotate-left"></i> Application Pending</h2>
            <?php while ($req = $pending_requests->fetch_assoc()): ?>
                <div class="card" style="border-left: 5px solid var(--warning) !important; background: #fffdf5 !important;">
                    <div class="card-header" style="background: transparent; border-bottom: none; padding-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                            <div>
                                <h3 style="font-size: 1.1rem; color: #856404;"><?php echo htmlspecialchars($req['book_name']); ?></h3>
                                <p style="font-size: 0.85rem; color: #997404;">Requested on <?php echo date('M d, Y', strtotime($req['requested_at'])); ?></p>
                            </div>
                            <span class="badge badge-warning" style="padding: 6px 12px; font-size: 0.75rem;">Awaiting Admin Review</span>
                        </div>
                    </div>
                    <div style="padding: 0 var(--spacing-lg) var(--spacing-lg) var(--spacing-lg);">
                        <p dir="rtl" style="font-size: 1.25rem; color: var(--primary-600); margin-bottom: 10px;"><?php echo htmlspecialchars($req['book_name_arabic']); ?></p>
                        <div style="font-size: 0.9rem; color: #666; display: flex; gap: 20px;">
                            <span><strong>Author:</strong> <?php echo htmlspecialchars($req['author']); ?></span>
                            <span><strong>Total Pages:</strong> <?php echo $req['total_pages']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php $pending_requests->data_seek(0); // Reset for possible further use ?>
        </div>
    <?php endif; ?>

    <!-- My Books Section -->
    <div id="my-books-container">
        <?php if ($my_books->num_rows > 0): ?>
            <h2 class="mb-3" style="font-size: 1.25rem;"><i class="fas fa-list"></i> My Tagged Kutub</h2>
            <?php while ($book = $my_books->fetch_assoc()): ?>
            <?php 
                $pages_completed = $book['pages_completed'] ?? 0;
                $total_pages = $book['total_pages'] ?? 0;
                $progress_percentage = $total_pages > 0 ? round(($pages_completed / $total_pages) * 100, 2) : 0;
                $is_completed = ($book['status'] === 'completed');
            ?>
            <div class="card book-card" data-book-id="<?php echo $book['id']; ?>">
                <div class="card-header book-header">
                    <div>
                        <h3><?php echo htmlspecialchars($book['book_name']); ?></h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary);">By <?php echo htmlspecialchars($book['author']); ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span class="book-arabic-name" dir="rtl" style="font-size: 1.5rem; color: var(--primary-600); display: block;"><?php echo htmlspecialchars($book['book_name_arabic']); ?></span>
                        <span class="badge <?php echo $is_completed ? 'badge-success' : 'badge-warning'; ?> status-badge">
                            <?php echo $is_completed ? 'Completed' : 'In Progress'; ?>
                        </span>
                    </div>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <div class="book-grid-info">
                        <div><strong>Started:</strong> <?php echo date('M d, Y', strtotime($book['started_date'])); ?></div>
                        <div><strong>Total Pages:</strong> <?php echo $total_pages; ?></div>
                        <?php if ($is_completed): ?>
                            <div><strong>Completed:</strong> <?php echo date('M d, Y', strtotime($book['completed_date'])); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress-label">
                            <span class="progress-label-text">Progress: <span class="completed-count"><?php echo $pages_completed; ?></span> / <?php echo $total_pages; ?> pages</span>
                            <span class="progress-label-value"><span class="percent-text"><?php echo $progress_percentage; ?></span>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                    </div>

                    <?php if (!$is_completed): ?>
                    <form class="ajax-book-form mt-3" data-action="update_progress">
                        <input type="hidden" name="action" value="update_progress">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        
                        <div class="form-group">
                            <label>Update Pages Completed</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="pages_completed" class="form-control" 
                                       min="0" max="<?php echo $total_pages; ?>" 
                                       value="<?php echo $pages_completed; ?>" required>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>

                    <form class="ajax-book-form" data-action="complete">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                        <div class="form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any notes..."><?php echo htmlspecialchars($book['notes'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Mark as completed?')">
                            <i class="fas fa-check"></i> Mark as Completed
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="mt-2">
                            <strong>Notes:</strong>
                            <p style="padding: 10px; background: var(--bg-tertiary); border-radius: var(--radius-md); font-style: italic;">
                                <?php echo htmlspecialchars($book['notes'] ?: 'No notes.'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <strong>No kutub are tagged to your account yet.</strong>
                <div style="margin-top: 0.5rem;">Use the request section below to ask the admin for an available book.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Request Books -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Request Kutub Access</h3>
        </div>
        <div style="padding: var(--spacing-lg);">
            <?php if ($has_assigned_books || $has_pending_requests): ?>
                <div class="empty-state">
                    <?php if ($has_assigned_books): ?>
                        You already have tagged kutub. New requests are hidden until admin review or until you are released.
                    <?php else: ?>
                        Your request for a book is currently pending. Please wait for admin approval before making another request.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($all_books->num_rows > 0): ?>
                    <form class="ajax-book-form" data-action="request_book">
                        <input type="hidden" name="action" value="request_book">
                        <div class="form-group">
                            <label>Select Book</label>
                            <select name="book_id" class="form-control" required>
                                <option value="">-- Choose an available book --</option>
                                <?php 
                                $all_books->data_seek(0);
                                while ($book = $all_books->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $book['id']; ?>">
                                        <?php echo htmlspecialchars($book['book_name']); ?> (<?php echo $book['total_pages']; ?> pages)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Send Request to Admin
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        No unassigned active books are available right now.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.ajax-book-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const action = this.getAttribute('data-action');
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch('ajax_book_transcription.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                if (action === 'request_book' || action === 'complete') {
                    // Reload to reflect significant structure changes
                    setTimeout(() => location.reload(), 1000);
                } else if (action === 'update_progress') {
                    // Direct UI update
                    const card = this.closest('.book-card');
                    card.querySelector('.completed-count').innerText = result.data.pages_completed;
                    card.querySelector('.percent-text').innerText = result.data.percentage;
                    card.querySelector('.progress-fill').style.width = result.data.percentage + '%';
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Connection error', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
