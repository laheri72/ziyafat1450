# Improvement Opportunities

## Completed ✅
- **Real-time Feedback:** Site-wide implementation of AJAX/Fetch for all tracking and data entry.
- **Mobile Optimization & Layout UX:** Fully responsive navigation, table structures, and a top-right user profile dropdown to facilitate quick mobile logouts.
- **Admin Utilities:** Added Super Admin password reset functionality.
- **Advanced Custom Reporting:** Interactive on-screen reporting engine featuring customizable progress matrices, Select2 multi-select filters, print stylesheets, and dynamic Excel spreadsheet export.
- **Dashboard Deep-Dive:** Clickable KPI cards displaying progress breakdowns (Dua, Tasbeeh, Namaz, Ziyarat) in popup modals.

## Technical Debt (High Priority)
- **Password Hashing:** **CRITICAL.** Implement `password_hash()` immediately to replace plain-text storage.
- **Environment Configuration:** Move Database credentials to a `.env` file.
- **Code Duplication:** Refactor repeated AJAX handling patterns into a shared controller or class.

## Future Enhancements
- **Interactive Charts:** Use Chart.js for the visual "Waterfall" and Amali progress on the dashboard.
