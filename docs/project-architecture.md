# Project Architecture: quanlykho

This document describes the high-level architecture and technical design patterns of the **quanlykho** warehouse management system.

---

## 🏗️ Technical Stack
- **Core**: PHP 8.1+ (Vanilla)
- **Database**: MariaDB 10.4+ / MySQL 8.0
- **Web Server**: Apache (integrated via XAMPP)
- **Frontend**:
    - [Bootstrap 5.3](https://getbootstrap.com/): Core UI framework.
    - [jQuery 3.7](https://jquery.com/): DOM manipulation and AJAX.
    - [DataTables](https://datatables.net/): Advanced grid views for inventory and orders.
    - [Chart.js](https://www.chartjs.org/): KPI visualizations on the dashboard.
- **Backend Libraries (Composer)**:
    - `dompdf/dompdf`: Generates professional PDF outputs.
    - `phpoffice/phpspreadsheet`: Handles complex Excel imports (Lalamove).
    - `phpmailer/phpmailer`: Secure SMTP email transport.
    - `google/apiclient`: Integration with Google OAuth2 and Gmail context.

---

## 📂 Directory Structure

```text
/
├── .agent/             # AI Assistant configuration & rules
├── ajax/               # Frontend-to-Backend AJAX endpoints
├── assets/             # CSS/JS/Images
├── config/             # Database & Global configuration
├── docs/               # System documentation (Current)
├── includes/           # Reusable UI components & Init scripts
├── lang/               # Multi-language translation files (vi/en)
├── process/            # Business logic handlers (Controllers)
├── storage/            # Internal document storage (Private)
├── vendor/             # Composer dependencies
└── .htaccess           # Security & URL routing
```

---

## 🔒 Security Architecture

### 1. Unified Initialization
Every entry point begins with `require_once 'includes/init.php'`. This script:
- Establishes the `$pdo` database connection.
- Resumes the secure `PHP_SESSION`.
- Loads the appropriate `$lang` array based on user preference.
- Invokes `require_login()` for protected routes.

### 2. File Access Security
Sensitive files (PDFs, signatures, uploads) are stored in directories protected by `.htaccess` (Denied to public). All access is proxied through `file.php?path=...`, which verifies:
- Is the user currently logged in?
- Does the user have permission to access this specific file?

### 3. Data Integrity
- **SQL Injection**: All queries use **PDO Prepared Statements** with named or positional parameters.
- **XSS**: All dynamic UI rendering uses `htmlspecialchars` or templating escaping.
- **CSRF**: (Work in progress) Session-based token verification for critical state changes.

---

## 🔄 Request Lifecycle
1. User clicks a button in the UI (e.g., `inventory.php`).
2. Frontend script (`inventory.js`) sends an AJAX request to `process/inventory_serverside.php`.
3. Handler performs logic, interacts with `$pdo`, and returns a JSON payload.
4. Frontend updates the DOM or shows a Toast notification via `SweetAlert2`.
