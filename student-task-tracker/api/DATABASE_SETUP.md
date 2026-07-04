# Local Database Setup

How to get the `student_task_tracker` database running on your machine for local development.

## 1. Install MySQL / MariaDB

The easiest option is **XAMPP** (bundles MariaDB + phpMyAdmin), but any MySQL/MariaDB server works.

- Download XAMPP: https://www.apachefriends.org/
- Open the **XAMPP Control Panel** and click **Start** next to **MySQL**.

## 2. Create the database and tables

The schema lives in `api/database.sql`. Run it once — pick whichever method you prefer.

**Option A — phpMyAdmin (GUI)**
1. Go to http://localhost/phpmyadmin
2. Click the **Import** tab.
3. Choose `api/database.sql` and click **Go**.

**Option B — command line**
```bash
# from the student-task-tracker/api folder
mysql -u root -p < database.sql
```
(Press Enter at the password prompt if your root user has no password — the XAMPP default.)

This creates the `student_task_tracker` database plus the `users` and `tasks` tables.

## 3. Configure your credentials (`.env`)

The API reads its DB credentials from `api/.env`, which is **not** committed to git so everyone can use their own settings. A ready-to-use file has been created for you with XAMPP defaults:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=student_task_tracker
```

Adjust these if your setup differs:

| Variable | What it is | Change it if... |
|----------|------------|-----------------|
| `DB_HOST` | Server address | Your DB runs on another host |
| `DB_PORT` | MySQL port | You changed it from the 3306 default |
| `DB_USER` | Login user | You don't use `root` |
| `DB_PASS` | Password | Your DB user has a password |
| `DB_NAME` | Database name | You renamed the database |

## 4. Run the PHP API

From the `api/` folder, start PHP's built-in server:

```bash
# from student-task-tracker/api
php -S localhost:8000 router.php
```

The API is now at `http://localhost:8000`.

## 5. Run the frontend

In a separate terminal, from `student-task-tracker/`:

```bash
npm install     # first time only
npm run serve
```

Open the URL Vue prints (usually `http://localhost:8080`).

## Troubleshooting

- **"Database connection failed"** — MySQL isn't running, or the `.env` credentials/port are wrong. Confirm MySQL is started in XAMPP and that `DB_PORT` matches.
- **"Unknown database 'student_task_tracker'"** — you skipped step 2; import `database.sql`.
- **"Access denied for user"** — the `DB_USER` / `DB_PASS` in `.env` don't match your MySQL login.
- **Port 3306 already in use** — another MySQL instance is running; stop it or set a different `DB_PORT` in `.env` (and in MySQL).
- **Tables not showing** — re-run `database.sql`; the `CREATE ... IF NOT EXISTS` statements are safe to run again.
