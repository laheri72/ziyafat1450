# Dependency Graph

## Server-Side
- **mysqli:** Core database extension.
- **datetime:** Used for fiscal year and payment period calculations.

## Client-Side
- **FontAwesome 6.4.0:** Icons (CDN).
- **Inter Font:** Typography (Google Fonts).
- **Select2:** Used for searchable user dropdowns in Admin (CDN).
- **SweetAlert2:** Used for confirmation dialogs (CDN).

## Internal Flow
- `includes/functions.php` -> Essential for every page (Auth & Progress).
- `assets/js/script.js` -> Centralized sidebar and UI initialization.
