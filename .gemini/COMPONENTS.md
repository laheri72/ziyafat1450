# Components and Reusable Logic

## UI Components
- **`includes/header.php`:**
  - Dynamic Navigation based on role.
  - **Collapsible Sidebar:** Optimized for both mobile (slide-in) and desktop (mini-mode).
  - **User Profile Dropdown:** Collapsible top-right menu for quick account settings and mobile-friendly logouts.
  - **Toast System:** Global `showToast(message, type)` function for real-time feedback.
- **`includes/footer.php`:** Closing body/html and centralized JS loading.
- **`assets/css/style.css`:**
  - **Responsive Table Stack:** Utility classes to handle complex tables on small screens.
  - Modern card layouts and progress bar styling.

## Core Logic
- **`includes/functions.php`:**
  - Session management and role-based authorization.
  - Data distribution logic (Waterfall Finance).
  - Centralized Amali progress calculations.

## Automation & Batching
- **AJAX Handlers:** Decoupled frontend/backend logic for Quran, Book, and Finance entry.
- **Interactive Modals:** Dynamic popup dialogs on the Admin Dashboard fetching statistics and rendering real-time progress bars.
- **`admin/ajax_dashboard_details.php`:** Secure backend API providing dynamic statistics for Duas, Tasbeeh, Namaz, and Ziyarat.
