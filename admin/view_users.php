<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

$page_title = 'View Users';
$css_path = '../assets/css/';
$js_path = '../assets/js/';

// Get filter parameters
$filter_category = isset($_GET['filter_category']) ? clean_input($_GET['filter_category']) : '';

// Check admin type and set category restrictions
$is_super = is_super_admin();
$assigned_category = get_assigned_category();

// If not super admin (i.e. Amali Coordinator), force filter strictly to their assigned category
if (!$is_super) {
    $filter_category = $assigned_category ? $assigned_category : '';
}

// Get users
$sql = "SELECT 
            u.id,
            u.its_number,
            u.tr_number,
            u.category,
            u.name,
            u.email,
            u.phone_number,
            u.role,
            u.created_at
        FROM users u
        WHERE (u.role = 'user' OR u.role = 'admin')";

if ($filter_category) {
    $sql .= " AND u.category = ?";
}

$sql .= " ORDER BY u.category, u.tr_number ASC";

if ($filter_category) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter_category);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query($sql);
}

require_once '../includes/header.php';
?>

<div class="container container-data">
    <h1 class="mb-3"><i class="fas fa-users"></i> View Users</h1>

    <!-- Filter Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Users</h3>
        </div>
        <form method="GET" action="" style="padding: var(--spacing-lg);">
            <?php if (!$is_super): ?>
                <input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($filter_category); ?>">
            <?php endif; ?>
            <div class="form-group" style="margin-bottom: var(--spacing-md);">
                <label for="filter_category"><i class="fas fa-mosque"></i> Filter by Jamea</label>
                <select id="filter_category" name="filter_category" class="form-control" <?php echo !$is_super ? 'disabled' : ''; ?>>
                    <?php if ($is_super): ?>
                        <option value="">All Jamea</option>
                        <option value="Surat" <?php echo $filter_category === 'Surat' ? 'selected' : ''; ?>>Surat</option>
                        <option value="Marol" <?php echo $filter_category === 'Marol' ? 'selected' : ''; ?>>Marol</option>
                        <option value="Karachi" <?php echo $filter_category === 'Karachi' ? 'selected' : ''; ?>>Karachi</option>
                        <option value="Nairobi" <?php echo $filter_category === 'Nairobi' ? 'selected' : ''; ?>>Nairobi</option>
                        <option value="Muntasib" <?php echo $filter_category === 'Muntasib' ? 'selected' : ''; ?>>Muntasib</option>
                    <?php else: ?>
                        <option value="<?php echo htmlspecialchars($filter_category); ?>" selected><?php echo htmlspecialchars($filter_category ?: 'Assigned Branch'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <?php if ($is_super): ?>
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <?php if ($filter_category): ?>
                    <a href="view_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h3><i class="fas fa-users"></i> All Users & Admins <?php echo $filter_category ? '- ' . htmlspecialchars($filter_category) : ''; ?></h3>
                <div class="action-buttons" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name, ITS, or TR..." style="width: 280px; margin: 0;">
                    <button onclick="exportTableToCSV('dataTable', 'users.csv')" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <?php if (is_super_admin()): ?>
                    <a href="add_user.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-container">
            <?php if ($users->num_rows > 0): ?>
                <table id="dataTable" class="responsive-table-stack">
                    <thead>
                        <tr>
                            <th>ITS Number</th>
                            <th>TR Number</th>
                            <th>Jamea</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td data-label="ITS"><?php echo htmlspecialchars($user['its_number']); ?></td>
                                <td data-label="TR"><?php echo htmlspecialchars($user['tr_number']); ?></td>
                                <td data-label="Jamea">
                                    <?php if ($user['category']): ?>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($user['category']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Name"><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td data-label="Phone"><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></td>
                                <td data-label="Role">
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-danger' : 'badge-primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td data-label="Joined"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No users found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>