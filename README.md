# TerviseSamm (Vormipäevik)

Rakendus õpilaste füüsilise vormi logimiseks ning õpetajate rühmade koondülevaateks.

→ [**Funktsionaalsuse kirjeldus**](FEATURES.md)

## Nõuded

- PHP 8.1+
- MySQL 8.0+
- Veebibrauser

## Installatsioon

### 1. Andmebaas

```bash
mysql -u root -p < database/init.sql
```

### 2. Kasutajad ja seed

```bash
php database/seed.php
```

Parool kõigile vaikekasutajatele: `secret`

- **Boss** (admin): boss@school.ee
- **Malle** (õpetaja): malle@school.ee
- **Jüri** (õpilane): jyri@school.ee

### 3. Konfiguratsioon

Kopeeri `config/.env.example` või muuda `config/.env` andmebaasi ja muu seadistuse jaoks.

### 4. Käivitus

**PHP sisseehitatud server:**
```bash
php -S localhost:8000 -t public public/router.php
```

Ava brauseris: http://localhost:8000/login.html

**Apache:** sea DocumentRoot kaustale `public/` ja luba mod_rewrite.

## Struktuur

- `public/` – frontend (HTML, CSS, JS) ja sissepääs
- `src/` – PHP kontrollerid, repos, teenused
- `config/` – .env
- `database/` – SQL ja seed
