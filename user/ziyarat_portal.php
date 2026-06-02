<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

$page_title = 'Ziyarat Portal';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

$user_id = $_SESSION['user_id'];
$mazars = get_active_mazars($conn);
$ziyarat_total = get_ziyarat_total($conn, $user_id);
$ziyarat_breakdown = get_ziyarat_breakdown($conn, $user_id);
$recent_entries = get_recent_ziyarat_entries($conn, $user_id);

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

    .ziyarat-entry-actions {
        display: flex;

        justify-content: flex-end;
    }

    .ziyarat-delete-btn {
        min-width: 92px;
    }

    .ziyarat-delete-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.55);
        z-index: 2000;
    }

    .ziyarat-delete-modal.active {
        display: flex;
    }

    .ziyarat-delete-dialog {
        width: min(100%, 460px);
        background: #fff;
        border-radius: 8px;
        box-shadow: var(--shadow-2xl);
        overflow: hidden;
    }

    .ziyarat-delete-dialog-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border-color);
        background: #fef2f2;
        color: #991b1b;
    }

    .ziyarat-delete-dialog-header h3 {
        font-size: 1rem;
        margin: 0;
    }

    .ziyarat-delete-dialog-body {
        padding: 1.25rem;
    }

    .ziyarat-delete-summary {
        margin-top: 1rem;
        padding: 0.85rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-secondary);
        display: grid;
        gap: 0.45rem;
        font-size: 0.9rem;
    }

    .ziyarat-delete-summary strong {
        color: var(--text-primary);
    }

    .ziyarat-delete-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-color);
        background: var(--bg-secondary);
    }
</style>

<div class="container">
    <div class="page-header">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 15px; color: #666; text-decoration: none; font-weight: 500; font-size: 14px; padding: 6px 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e0e0e0;">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <h1><i class="fas fa-kaaba"></i> Ziyarat Portal</h1>
        <p>Record your Ziyarat count by Mazar</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-card-header">
                <h4>Total Ziyarat</h4>
                <div class="stat-icon">
                    <i class="fas fa-kaaba"></i>
                </div>
            </div>
            <div class="stat-value" id="ziyaratTotal"><?php echo $ziyarat_total; ?></div>
            <div class="stat-label">All Mazars</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Ziyarat Count</h3>
        </div>
        <form id="ziyaratForm" style="padding: var(--spacing-lg);">
            <div class="form-group">
                <label for="mazar_id"><i class="fas fa-location-dot"></i> Select Mazar *</label>
                <select id="mazar_id" name="mazar_id" class="form-control" required <?php echo $mazars->num_rows === 0 ? 'disabled' : ''; ?>>
                    <option value="">-- Select Mazar --</option>
                    <?php while ($mazar = $mazars->fetch_assoc()): ?>
                        <option value="<?php echo $mazar['id']; ?>"><?php echo htmlspecialchars($mazar['mazar_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="count_to_add"><i class="fas fa-plus"></i> Add Count *</label>
                <input type="number" id="count_to_add" name="count_to_add" class="form-control" min="1" placeholder="Enter count to add" required <?php echo $mazars->num_rows === 0 ? 'disabled' : ''; ?>>
            </div>

            <?php if ($mazars->num_rows === 0): ?>
                <div class="alert alert-info">No Mazars available right now.</div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" <?php echo $mazars->num_rows === 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-save"></i> Add Ziyarat Entry
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Ziyarat Breakdown</h3>
        </div>
        <div class="table-container">
            <table id="ziyaratBreakdownTable" <?php echo $ziyarat_breakdown->num_rows > 0 ? '' : 'style="display:none;"'; ?>>
                <thead>
                    <tr>
                        <th>Mazar</th>
                        <th>Total Count</th>
                        <th>Last Entry</th>
                    </tr>
                </thead>
                <tbody id="ziyaratBreakdownRows">
                    <?php while ($row = $ziyarat_breakdown->fetch_assoc()): ?>
                        <tr data-mazar-id="<?php echo $row['id']; ?>">
                            <td><?php echo htmlspecialchars($row['mazar_name']); ?><?php echo !$row['is_active'] ? ' <span class="badge badge-danger">Inactive</span>' : ''; ?></td>
                            <td><strong class="mazar-total"><?php echo $row['total_count']; ?></strong></td>
                            <td class="last-entry-date"><?php echo $row['last_entry_date'] ? date('M d, Y', strtotime($row['last_entry_date'])) : '-'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <p id="ziyaratEmptyState" class="text-center" style="padding: 1rem; <?php echo $ziyarat_breakdown->num_rows > 0 ? 'display:none;' : ''; ?>">No Ziyarat data recorded yet.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock-rotate-left"></i> Recent Ziyarat Entries</h3>
        </div>
        <div class="table-container">
            <table id="ziyaratRecentTable" class="responsive-table-stack" <?php echo $recent_entries->num_rows > 0 ? '' : 'style="display:none;"'; ?>>
                <thead>
                    <tr>
                        <th>Mazar</th>
                        <th>Count</th>
                        <th>Entry Date</th>
                        <th>Recorded On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ziyaratRecentRows">
                    <?php while ($entry = $recent_entries->fetch_assoc()): ?>
                        <tr data-entry-id="<?php echo $entry['id']; ?>">
                            <td data-label="Mazar"><?php echo htmlspecialchars($entry['mazar_name']); ?></td>
                            <td data-label="Count"><strong><?php echo $entry['count_added']; ?></strong></td>
                            <td data-label="Entry Date"><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                            <td data-label="Recorded On"><?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?></td>
                            <td data-label="Actions">
                                <div class="ziyarat-entry-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-danger ziyarat-delete-btn"
                                        data-entry-id="<?php echo $entry['id']; ?>"
                                        data-mazar-name="<?php echo htmlspecialchars($entry['mazar_name'], ENT_QUOTES); ?>"
                                        data-count="<?php echo $entry['count_added']; ?>"
                                        data-entry-date="<?php echo date('M d, Y', strtotime($entry['entry_date'])); ?>"
                                    >
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <p id="ziyaratRecentEmptyState" class="text-center" style="padding: 1rem; <?php echo $recent_entries->num_rows > 0 ? 'display:none;' : ''; ?>">No individual entries yet.</p>
        </div>
    </div>
</div>

<div class="ziyarat-delete-modal" id="deleteZiyaratModal" role="dialog" aria-modal="true" aria-labelledby="deleteZiyaratTitle">
    <div class="ziyarat-delete-dialog">
        <div class="ziyarat-delete-dialog-header">
            <i class="fas fa-triangle-exclamation"></i>
            <h3 id="deleteZiyaratTitle">Delete Ziyarat Entry</h3>
        </div>
        <div class="ziyarat-delete-dialog-body">
            <p>This removes only this mistaken entry from your Ziyarat total. This action cannot be undone.</p>
            <div class="ziyarat-delete-summary">
                <div><strong>Mazar:</strong> <span id="deleteMazarName">-</span></div>
                <div><strong>Count:</strong> <span id="deleteCount">-</span></div>
                <div><strong>Entry Date:</strong> <span id="deleteEntryDate">-</span></div>
            </div>
        </div>
        <div class="ziyarat-delete-dialog-actions">
            <button type="button" class="btn btn-secondary" id="cancelDeleteZiyarat">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" id="confirmDeleteZiyarat">
                <i class="fas fa-trash"></i> Delete Entry
            </button>
        </div>
    </div>
</div>

<script>
let pendingDeleteEntry = null;

function setZiyaratTotal(value) {
    document.getElementById('ziyaratTotal').textContent = value;
}

function upsertBreakdownRow(data) {
    const row = document.querySelector(`tr[data-mazar-id="${data.mazar_id}"]`);

    if (row) {
        row.querySelector('.mazar-total').textContent = data.mazar_total;
        row.querySelector('.last-entry-date').textContent = data.entry_date_label || data.last_entry_label || '-';
        return;
    }

    const table = document.getElementById('ziyaratBreakdownTable');
    const rows = document.getElementById('ziyaratBreakdownRows');
    const emptyState = document.getElementById('ziyaratEmptyState');
    const newRow = document.createElement('tr');
    const mazarCell = document.createElement('td');
    const totalCell = document.createElement('td');
    const totalValue = document.createElement('strong');
    const dateCell = document.createElement('td');

    newRow.dataset.mazarId = data.mazar_id;
    mazarCell.textContent = data.mazar_name;
    totalValue.className = 'mazar-total';
    totalValue.textContent = data.mazar_total;
    totalCell.appendChild(totalValue);
    dateCell.className = 'last-entry-date';
    dateCell.textContent = data.entry_date_label || data.last_entry_label || '-';
    newRow.append(mazarCell, totalCell, dateCell);
    rows.appendChild(newRow);
    table.style.display = '';
    emptyState.style.display = 'none';
}

function createRecentEntryRow(data) {
    const row = document.createElement('tr');
    const mazarCell = document.createElement('td');
    const countCell = document.createElement('td');
    const countValue = document.createElement('strong');
    const entryDateCell = document.createElement('td');
    const recordedCell = document.createElement('td');
    const actionsCell = document.createElement('td');
    const actionsWrap = document.createElement('div');
    const deleteBtn = document.createElement('button');
    const deleteIcon = document.createElement('i');

    row.dataset.entryId = data.entry_id;
    mazarCell.dataset.label = 'Mazar';
    mazarCell.textContent = data.mazar_name;
    countCell.dataset.label = 'Count';
    countValue.textContent = data.count_added;
    countCell.appendChild(countValue);
    entryDateCell.dataset.label = 'Entry Date';
    entryDateCell.textContent = data.entry_date_label;
    recordedCell.dataset.label = 'Recorded On';
    recordedCell.textContent = data.recorded_at_label;
    actionsCell.dataset.label = 'Actions';
    actionsWrap.className = 'ziyarat-entry-actions';
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-sm btn-danger ziyarat-delete-btn';
    deleteBtn.dataset.entryId = data.entry_id;
    deleteBtn.dataset.mazarName = data.mazar_name;
    deleteBtn.dataset.count = data.count_added;
    deleteBtn.dataset.entryDate = data.entry_date_label;
    deleteIcon.className = 'fas fa-trash';
    deleteBtn.append(deleteIcon, document.createTextNode(' Delete'));
    actionsWrap.appendChild(deleteBtn);
    actionsCell.appendChild(actionsWrap);
    row.append(mazarCell, countCell, entryDateCell, recordedCell, actionsCell);

    return row;
}

function prependRecentEntry(data) {
    const table = document.getElementById('ziyaratRecentTable');
    const rows = document.getElementById('ziyaratRecentRows');
    const emptyState = document.getElementById('ziyaratRecentEmptyState');

    rows.prepend(createRecentEntryRow(data));
    table.style.display = '';
    emptyState.style.display = 'none';
}

function removeRecentEntry(entryId, remainingEntries) {
    const row = document.querySelector(`tr[data-entry-id="${entryId}"]`);
    const table = document.getElementById('ziyaratRecentTable');
    const emptyState = document.getElementById('ziyaratRecentEmptyState');

    if (row) {
        row.remove();
    }

    if (remainingEntries === 0) {
        table.style.display = 'none';
        emptyState.style.display = '';
    } else if (document.querySelectorAll('#ziyaratRecentRows tr').length === 0) {
        window.location.reload();
    }
}

function openDeleteModal(button) {
    pendingDeleteEntry = {
        entryId: button.dataset.entryId,
        mazarName: button.dataset.mazarName,
        count: button.dataset.count,
        entryDate: button.dataset.entryDate
    };

    document.getElementById('deleteMazarName').textContent = pendingDeleteEntry.mazarName;
    document.getElementById('deleteCount').textContent = pendingDeleteEntry.count;
    document.getElementById('deleteEntryDate').textContent = pendingDeleteEntry.entryDate;
    document.getElementById('deleteZiyaratModal').classList.add('active');
}

function closeDeleteModal() {
    pendingDeleteEntry = null;
    document.getElementById('deleteZiyaratModal').classList.remove('active');
}

document.getElementById('ziyaratForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    submitBtn.disabled = true;

    fetch('ajax_ziyarat_entry.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;

        if (data.success) {
            showToast(data.message, 'success');
            setZiyaratTotal(data.data.total_count);
            upsertBreakdownRow(data.data);
            prependRecentEntry(data.data);

            form.reset();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => {
        submitBtn.disabled = false;
        showToast('An error occurred. Please try again.', 'error');
    });
});

document.getElementById('ziyaratRecentRows').addEventListener('click', function(e) {
    const button = e.target.closest('.ziyarat-delete-btn');
    if (!button) return;

    openDeleteModal(button);
});

document.getElementById('cancelDeleteZiyarat').addEventListener('click', closeDeleteModal);

document.getElementById('deleteZiyaratModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

document.getElementById('confirmDeleteZiyarat').addEventListener('click', function() {
    if (!pendingDeleteEntry) return;

    const button = this;
    const formData = new FormData();
    formData.append('entry_id', pendingDeleteEntry.entryId);
    button.disabled = true;

    fetch('ajax_ziyarat_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;

        if (data.success) {
            showToast(data.message, 'success');
            setZiyaratTotal(data.data.total_count);
            upsertBreakdownRow(data.data);
            removeRecentEntry(data.data.entry_id, data.data.remaining_entries);
            closeDeleteModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => {
        button.disabled = false;
        showToast('An error occurred while deleting. Please try again.', 'error');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
