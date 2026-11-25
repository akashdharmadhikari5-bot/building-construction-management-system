# Building Construction Management System (BCMS)

## Overview
A complete web-based Building Construction Management System for managing construction projects, clients, workers, materials, tasks, expenses, and payments.

**Tech Stack:** PHP 8+ · MySQL 8 · Bootstrap 5 · Chart.js · XAMPP (Windows)

---

## 🚀 Quick Start (Windows + XAMPP)

### Step 1: Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install to `C:\xampp` (default)
3. Open **XAMPP Control Panel** and start **Apache** and **MySQL**

### Step 2: Place Project Files
Copy the entire `bcms` folder into your XAMPP web root:
```
C:\xampp\htdocs\bcms\
```
So the structure becomes:
```
C:\xampp\htdocs\bcms\
  ├── public\
  ├── app\
  ├── config\
  ├── database\
  ├── docs\
  └── ...
```

### Step 3: Create the Database
1. Open your browser: http://localhost/phpmyadmin
2. Click **New** (left sidebar)
3. Database name: `bcms_db` → **Create**
4. Click on `bcms_db` → Click **Import** tab
5. Click **Choose File** → select `d:\bcms\database\schema.sql`
6. Click **Go**
7. *(Optional)* Import `database\seed.sql` for demo data

### Step 4: Configure the Application
1. Copy `.env.example` to `.env`:
   ```
   copy d:\bcms\.env.example d:\bcms\.env
   ```
2. Open `d:\bcms\.env` in VS Code and set:
   ```
   DB_HOST=127.0.0.1
   DB_USERNAME=root
   DB_PASSWORD=          ← leave blank for default XAMPP MySQL
   DB_DATABASE=bcms_db
   APP_URL=http://localhost/bcms/public
   ```

### Step 5: Run Setup Wizard
Open your browser: **http://localhost/bcms/public/setup.php**
1. **Step 1** – Test DB connection
2. **Step 3** – Verify all tables exist
3. **Step 2** – Create your initial Administrator account (name, email, password)

> 🔒 **Security Notice:** The setup wizard automatically locks administrator account creation once an administrator user exists in the database. For production deployments, delete or rename `setup.php`.

### Step 6: Login
Open: **http://localhost/bcms/public/login.php**
Log in with your administrator credentials or demo accounts.

---

## 🧪 Running Automated Tests

Run the test suite directly from your terminal or command prompt:

```bash
# Basic integration and existence checks:
php tests/test_basic.php

# Mathematical formulas & business logic rules (offline unit verification):
php tests/test_business_logic.php
```

*Note for Windows/XAMPP users running from drive D: or custom drives:*
If your PHP CLI ini has relative extension paths, provide the explicit XAMPP extension directory:
```bash
php -d extension_dir="C:\xampp\php\ext" -d browscap="C:\xampp\php\extras\browscap.ini" tests/test_basic.php
php -d extension_dir="C:\xampp\php\ext" -d browscap="C:\xampp\php\extras\browscap.ini" tests/test_business_logic.php
```

---

## 📁 Project Structure

```
bcms/
├── public/               ← Web root (point Apache here)
│   ├── login.php
│   ├── logout.php
│   ├── setup.php
│   ├── dashboard.php
│   ├── clients.php
│   ├── projects.php
│   ├── project_view.php
│   ├── workers.php
│   ├── worker_view.php
│   ├── assignments.php
│   ├── attendance.php
│   ├── tasks.php
│   ├── materials.php
│   ├── purchases.php
│   ├── usage.php
│   ├── expenses.php
│   ├── payments.php
│   ├── reports.php
│   ├── wages.php
│   ├── budget.php
│   └── assets/
│       ├── css/bcms.css
│       └── js/bcms.js
├── app/
│   ├── models/           ← PHP model classes
│   ├── views/layout/     ← Shared layout partials
│   ├── services/         ← Business logic services
│   └── middleware/       ← Auth middleware
├── config/
│   ├── app.php           ← App config & helpers
│   ├── database.php      ← PDO connection & helpers
│   └── bootstrap.php     ← Bootstrap (included in every page)
├── database/
│   ├── schema.sql        ← Full database schema
│   └── seed.sql          ← Demo data
├── docs/                 ← Architecture documentation
├── storage/
│   ├── logs/             ← PHP error logs
│   └── uploads/          ← File uploads (protected)
├── .env.example          ← Config template
├── .gitignore
└── README.md
```

---

## 👥 User Roles & Access

| Role | Access |
|------|--------|
| **Admin / Project Manager** | Full access: all modules, all projects, reports, settings |
| **Client** | Own projects, own payments, project status/progress |
| **Worker** | Own assigned tasks, own attendance records |

---

## 🔑 Default Demo Credentials
*(After importing seed.sql)*

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@bcms.local | *(set via setup.php)* |
| Client | ramesh@bcms.local | secret |
| Worker | mohan@bcms.local | secret |

> ⚠️ The seed.sql demo passwords use `secret` as a placeholder hash. Use `setup.php` to create real accounts.

---

## 📋 Modules & Features

### 1. Authentication
- Secure login with `password_hash()` / `password_verify()`
- Session regeneration on login
- CSRF protection on all forms
- Role-based access enforcement

### 2. Dashboard
- Real-time stats: projects, clients, workers, budget, expenses, payments
- Charts: project status distribution, monthly expenses
- Low stock alerts
- Recent projects and payments

### 3. Projects
- Create, view, edit, update status and progress
- Progress auto-calculation from tasks
- Remaining budget calculation
- Budget vs expense tracking

### 4. Clients
- Full CRUD management
- Project history per client
- Payment summary

### 5. Workers
- Add, edit, deactivate workers
- Assign to projects (prevents duplicate active assignments)
- Wage calculation from attendance

### 6. Attendance
- Daily attendance marking (Present/Absent/Half Day/Holiday)
- Bulk attendance by project + date
- Duplicate prevention (unique constraint on worker+project+date)
- Authorized corrections
- Working days calculation

### 7. Tasks
- Create and assign tasks to projects and workers
- Status and progress tracking (0-100%)
- Auto-status to 'completed' when progress = 100

### 8. Materials
- Material catalog with unit tracking
- Stock computed from purchases, usage, and adjustments
- Low stock alerts

### 9. Material Purchases
- Record purchases (increases stock)
- Stock synchronized via transaction

### 10. Material Usage
- Record site usage (decreases stock)
- Prevents negative inventory (insufficient stock check)

### 11. Expenses
- Add/edit/delete expenses by type
- Total expense calculation per project
- Monthly expense trends

### 12. Payments
- Record payments with mode and reference
- Client-project relationship verification (IDOR prevention)
- Payment summary per project

### 13. Reports
- **Project Report**: budget, expenses, payments, progress
- **Worker Report**: attendance, working days, calculated wages
- **Expense Report**: by type, date range, project
- **Payment Report**: by project, client, type
- Print-friendly output

### 14. Wage Calculator
- Select worker + project + date range
- Shows attendance breakdown + total wage

### 15. Budget Overview
- Visual budget analysis per project
- Budget utilization gauge
- Remaining budget tracking

---

## 🔒 Security Features
- PDO prepared statements (SQL injection prevention)
- CSRF tokens on all state-changing forms
- `htmlspecialchars()` on all output (XSS prevention)
- Role & record-level authorization on every page
- Password hashing with bcrypt (cost=12)
- Secure session configuration
- Activity logging

---

## 🛠 Troubleshooting

**"Database Connection Error"**
- Ensure XAMPP MySQL is running
- Check `.env` DB settings
- Verify `bcms_db` database exists

**"Class not found" error**
- Check file paths in `config/bootstrap.php`
- Verify all model files exist in `app/models/`

**"Page not found (404)"**
- Ensure `bcms` folder is in `C:\xampp\htdocs\`
- Check Apache is running
- Verify `APP_URL` in `.env` matches your actual URL

**Blank page / PHP errors**
- Enable error display: set `APP_ENV=development` in `.env`
- Check `storage/logs/php_errors.log`

---

## 📦 Running PHP Syntax Check
```bash
# In XAMPP shell or Windows CMD:
C:\xampp\php\php.exe -l public\dashboard.php
# Check all PHP files:
for /r %f in (*.php) do C:\xampp\php\php.exe -l "%f"
```

---

## 📧 Support
For MCA Academic Project — Building Construction Management System.
Technology: PHP 8+ | MySQL 8 | Bootstrap 5 | XAMPP | Windows 10/11
