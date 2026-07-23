# Authentication Flow

## Mechanism
PHP Session-based authentication with role and admin-type validation.

## Super Admin Password Reset
Added a bypass for forgotten passwords:
1. Super Admin navigates to `edit_user.php`.
2. System detects `admin_type === 'super_admin'`.
3. Displays **"Reset Password to TR Number"** button.
4. On confirmation, the `password` field in the `users` table is overwritten with the user's `tr_number`.

## Login Logic
- Login via **ITS Number** and **Password**.
- Currently uses **Plain-text** comparison (High security risk - see Security Notes).
- Role-based redirection:
    - Admin -> `admin/index.php`
    - User -> `user/index.php`

## Authorization Helpers
- `require_login()`: Basic barrier.
- `require_admin()`: Restricts to role='admin'.
- `is_super_admin()`: Restricts to Super Admin privileges (full cross-branch access, reset passwords, add users, edit branch assignments).
- `has_amali_access()`: Restricts to Amali feature suite.
- `get_assigned_category()`: Resolves assigned branch/Jamea for Amali Coordinators.

## Amali Coordinator Branch Access Control Policy
1. **Branch Isolation**: Non-Super Admin Amali Coordinators (`!is_super_admin()`) can ONLY view and report on users belonging to their assigned Jamea/branch (`get_assigned_category()`).
2. **Add User Restriction**: Only Super Admins (`is_super_admin()`) can access `admin/add_user.php` or see the "Add User" links.
3. **Locked Profile Branch Editing**: Jamea/category editing is disabled on `user/profile.php` and `admin/edit_user.php` (except for Super Admin).
4. **Enforced Pages**: `view_users.php`, `amali_reports.php`, `advanced_reports.php`, `export_advanced_report.php`, `ajax_dashboard_details.php`, `user_details.php`, `edit_user.php`, `delete_user.php`.

## Reversion Instructions (If needed in future)
If you ever want to revert these commit changes:
- Run `git revert <commit-hash>` in your repository terminal.
- No database migrations were created or modified for this security policy (`No production database change required`), so reverting code cleanly restores former multi-branch visibility without touching MySQL tables.

