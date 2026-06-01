# System Architecture

## Technology Stack
- **Backend:** PHP 7.2+
- **Database:** MariaDB (InnoDB engine)
- **Frontend:** HTML5, CSS3 (Vanilla), JavaScript (Vanilla), jQuery (Select2).
- **Icons:** FontAwesome 6.4.0

## Responsive Strategy
- **Viewport:** Mobile-first approach using CSS variables for spacing.
- **Tables:** Custom `@media` queries transform `<table>` structures into stackable cards on devices < 768px.
- **Sidebar:** State persisted in `localStorage` to prevent layout flicker across sessions.