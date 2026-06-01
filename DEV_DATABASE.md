# Local XAMPP Database

This repo can use InfinityFree/Railway settings in `.env`, while local XAMPP uses `.env.local`.

`.env.local` is ignored by git and should stay only on your machine.

InfinityFree free hosting does not allow remote MySQL connections from XAMPP, MySQL Workbench, desktop apps, or mobile apps. Local development must use a local database copy exported from InfinityFree phpMyAdmin, then imported into XAMPP MySQL.

## Option 1: import the full dev dump

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin.
3. Import `database/127_0_0_1.sql`.
4. Keep `.env.local` as:

```ini
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=if0_42041374_ziyafat1450
```

The dump creates and uses the `if0_42041374_ziyafat1450` database name.

To refresh local data later, export a new SQL file from InfinityFree phpMyAdmin, then import that file into XAMPP phpMyAdmin.

## Option 2: create an empty dev database

1. Create a database named `ziyafat1450_dev` in phpMyAdmin.
2. Import `database/ziyafat1450_schema.sql`.
3. Change `.env.local` to:

```ini
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=ziyafat1450_dev
```

Use this option when you want a clean database without the dump data.
