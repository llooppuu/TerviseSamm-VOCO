# Run And Login Guide

This file contains universal setup and run steps for this project.

## Requirements

- PHP 8.1+
- MySQL 8.0+ or MariaDB
- A terminal (PowerShell, CMD, bash)

## 1. Configure Environment

Open `config/.env` and verify database values:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=vormipaevik
DB_USER=root
DB_PASS=
```

If your DB user has a password, set `DB_PASS` accordingly.

## 2. Start Database Server

If your database runs as a system service, ensure it is running and skip manual start.

If you start MariaDB manually, use a command like this (adjust paths and data directory for your system):

```powershell
mariadbd --datadir="<your-data-dir>" --port=3306 --bind-address=127.0.0.1 --console
```

## 3. Initialize Database Schema

From project root (`TerviseSamm-VOCO`):

```powershell
mariadb -u <db-user> -p < .\database\init.sql
```

Equivalent MySQL client command:

```powershell
mysql -u <db-user> -p < .\database\init.sql
```

## 4. Seed Default Users

From project root (`TerviseSamm-VOCO`):

```powershell
php .\database\seed.php
```

## 5. Start PHP Development Server

From project root (`TerviseSamm-VOCO`):

```powershell
php -S localhost:8000 -t public public/router.php
```

## 6. Open App

Open this URL in your browser:

`http://localhost:8000/login.html`

## Login Credentials (Seeded Users)

Default password for all users:

`secret`

Users:

- Boss (admin): `boss@school.ee`
- Malle (teacher): `malle@school.ee`
- Jyri (student): `jyri@school.ee`

## If Executables Are Not In PATH

Use full paths to your binaries, for example:

```powershell
& "C:\path\to\php.exe" -S localhost:8000 -t public public/router.php
& "C:\path\to\mariadb.exe" -u <db-user> -p < .\database\init.sql
```

## Troubleshooting

- If `seed.php` cannot connect, re-check `config/.env` and database credentials.
- If port `8000` is busy, start PHP on another port, for example `php -S localhost:8080 -t public public/router.php`.
- `AI_API_KEY` can be empty; the app will use fallback feedback text.
