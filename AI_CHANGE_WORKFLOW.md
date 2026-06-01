# AI Change Workflow

Use this file when asking AI/Codex to change features in local dev mode.

## Current database setup

- Production/hosted database: InfinityFree MySQL.
- Local dev database: XAMPP MySQL database named `if0_42041374_ziyafat1450`.
- Local config: `.env.local`, ignored by git.
- Production config: `.env` / hosted environment variables.

InfinityFree free hosting does not allow remote MySQL access from local tools, XAMPP, MySQL Workbench, or AI commands. Only PHP running on InfinityFree and InfinityFree phpMyAdmin can access the hosted database.

## What AI can do locally

AI can:

- Edit PHP, HTML, CSS, JS, and repo files.
- Change the local XAMPP database.
- Create SQL migration files for database structure or seed changes.
- Test changes against the local XAMPP database.
- Keep local-only data dumps ignored by git.

AI cannot directly:

- Connect from localhost to the InfinityFree database.
- Automatically push schema/data changes into InfinityFree MySQL.
- Safely treat local DB changes as production DB changes unless a migration/export step is prepared.

## How to request feature changes

Use this wording:

```text
Make this feature in local dev mode.
If database changes are needed, update the local XAMPP DB and create a SQL migration file for InfinityFree.
Do not edit or commit real data dumps.
Tell me exactly what SQL/import step I must run in InfinityFree phpMyAdmin.
```

## If the feature changes only code

AI should:

1. Edit the code.
2. Test locally.
3. Confirm no database changes are needed.

No InfinityFree database action is required.

## If the feature changes database structure

Examples:

- Add a new table.
- Add, rename, or remove a column.
- Add an index.
- Change enum values.
- Change default values.

AI should:

1. Apply the change to local XAMPP DB.
2. Create a migration SQL file in `database/migrations/`.
3. Make the SQL safe for phpMyAdmin import.
4. Tell the user to import that migration file in InfinityFree phpMyAdmin.

Important workflow rule:

- Any schema change, new table, column change, index change, enum change, or default change must be applied automatically to the local XAMPP database first.
- The matching SQL must also be saved as a migration file in `database/migrations/` so it can be imported manually into InfinityFree phpMyAdmin.
- Do not stop at the migration file alone when local dev can be updated safely.
- Always call out the exact migration file path that the user must import in production.

Recommended migration filename format:

```text
database/migrations/YYYY_MM_DD_short_description.sql
```

## If the feature changes required seed/master data

Examples:

- Add a default dua.
- Add a default book.
- Add system settings.

AI should:

1. Apply the seed change locally.
2. Create a SQL migration file with `INSERT`, `UPDATE`, or `INSERT ... ON DUPLICATE KEY UPDATE` where possible.
3. Tell the user to import that migration in InfinityFree phpMyAdmin.

Important workflow rule:

- Seed/master data changes that are part of a feature should also be applied in local XAMPP automatically when safe.
- The production-safe SQL must still be captured in a migration file for manual import to InfinityFree phpMyAdmin.

## If the feature changes user/live data

Examples:

- User records.
- Contributions.
- Quran progress.
- Dua entries.

Do not put real live data changes into git unless explicitly requested. For live data fixes, AI should prepare SQL for review and the user should run it manually in InfinityFree phpMyAdmin.

## Refreshing local dev data from InfinityFree

When local data should match production:

1. Export the database from InfinityFree phpMyAdmin.
2. Save it locally as an ignored file under `database/`.
3. Import it into XAMPP database `if0_42041374_ziyafat1450`.
4. Do not commit the export file.

## Golden rule

Every database-related feature change must end with one of these statements:

- `No production database change required.`
- `Production database change required: import this migration in InfinityFree phpMyAdmin: <file path>.`
- `Manual production data update required: run/review this SQL in InfinityFree phpMyAdmin.`
