# Local XAMPP Database

This repo can use InfinityFree/Railway settings in `.env`, while local XAMPP uses `.env.local`.

`.env.local` is ignored by git and should stay only on your machine.

InfinityFree free hosting does not allow remote MySQL connections from XAMPP, MySQL Workbench, desktop apps, or mobile apps. Local development must use a local database copy exported from InfinityFree phpMyAdmin, then imported into XAMPP MySQL.

## Option 1: import an InfinityFree export

1. Start Apache and MySQL in XAMPP.
2. Export the real database from InfinityFree phpMyAdmin.
3. Create a local database named `if0_42041374_ziyafat1450` in XAMPP phpMyAdmin.
4. Import the InfinityFree SQL export into that local database.
4. Keep `.env.local` as:

```ini
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=if0_42041374_ziyafat1450
```

To refresh local data later, export a new SQL file from InfinityFree phpMyAdmin, then import that file into XAMPP phpMyAdmin.

Do not use `database/127_0_0_1.sql` as the InfinityFree replica. That file is from an older Hostinger database dump.

## Option 2: create an empty dev database

1. Create a database named `if0_42041374_ziyafat1450` in phpMyAdmin.
2. Import `database/ziyafat1450_schema.sql`.
3. Keep `.env.local` as:

```ini
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=if0_42041374_ziyafat1450
```

Use this option when you want a clean database without the dump data.
