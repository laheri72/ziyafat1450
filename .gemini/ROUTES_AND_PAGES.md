# Routes and Pages

## Public Routes
- `/index.php`: Landing page, User/Admin authentication, and role-based redirection.
- `/auth/logout.php`: Destroys session and redirects to login.
- `/auth/register.php`: New user registration.

## User Routes (`/user`)
- `index.php`: User dashboard with optimized mobile grid navigation.
- `quran_tracking.php`: AJAX-enabled multi-select Juz tracking.
- `dua_tracking.php`: Interface to log Dua counts via AJAX.
- `dua_history.php`: View history of recorded Duas.
- `book_transcription.php`: AJAX-enabled book transcription management.
- `surat_finance_report.php`: Mobile-optimized financial report for Surat users.
- `profile.php`: View/Edit user profile information.
- `ajax_dua_entry.php`: API endpoint for logging Dua counts.
- `ajax_quran_tracking.php`: API endpoint for batch Juz updates.
- `ajax_book_transcription.php`: API endpoint for book progress.

## Admin Routes (`/admin`)
- `index.php`: Admin dashboard with interactive stat card modals.
- `view_users.php`: Mobile-responsive user list with search.
- `user_details.php`: Detailed view of a specific user's progress.
- `add_user.php`: Form to create new users.
- `edit_user.php`: User editing with Super Admin password reset button.
- `reports.php`: Financial reports across different categories.
- `amali_reports.php`: Detailed spiritual progress reports with a portal to the Advanced Reports Generator.
- `advanced_reports.php`: Enterprise-level advanced report generator displaying customized progress matrices live on-screen, filtered by branch, specific item selections (Select2), print stylesheets, and Excel export.
- `add_contribution.php`: Record new financial payments via AJAX.
- `manage_books.php`: CRUD for `books_master`.
- `manage_duas.php`: CRUD for `duas_master`.
- `ajax_add_contribution.php`: API endpoint for financial records.
- `ajax_dashboard_details.php`: API endpoint for fetching dashboard card details (Dua, Tasbeeh, Namaz, Ziyarat) in regional coordinator or global scopes.

## Internal / Partial Paths
- `includes/header.php`: Common nav, collapsible user dropdown, responsive sidebar toggle, and Toast system.
- `includes/footer.php`: Closing tags and script inclusions.
