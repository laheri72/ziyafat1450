# Improvement Opportunities

## Completed ✅
- **Real-time Feedback:** Site-wide implementation of AJAX/Fetch for all tracking and data entry.
- **Mobile Optimization:** Fully responsive navigation and table structures.
- **Admin Utilities:** Added Super Admin password reset functionality.

## Technical Debt (High Priority)
- **Password Hashing:** **CRITICAL.** Implement `password_hash()` immediately to replace plain-text storage.
- **Environment Configuration:** Move Database credentials to a `.env` file.
- **Code Duplication:** Refactor repeated AJAX handling patterns into a shared controller or class.

## Future Enhancements
- **Interactive Charts:** Use Chart.js for the visual "Waterfall" and Amali progress on the dashboard.
