# 🤖 Workspace Rules: PHP Quanlykho (Warehouse Management)

> [!IMPORTANT]
> These rules are **ALWAYS ON**. All AI actions, code changes, and documentation must adhere to these standards to maintain project integrity and security.

---

## 🏗️ 1. Project Identity & Context
- **Name**: Quanlykho (Warehouse Management System)
- **Stack**: Vanilla PHP 8.x, MariaDB/MySQL, XAMPP.
- **Frontend**: Bootstrap 5, jQuery, DataTables, Chart.js.
- **Goal**: Provide a lightweight, secure, and multi-language (VI/EN) warehouse tracking system.

---

## 💾 2. Save & Documentation Policy
- **Primary Storage**: All project-related documentation, technical analysis, research notes, and walkthroughs **MUST** be saved as `.md` files directly in the workspace.
- **Directory**: Use the `./docs/` folder for all Markdown documentation.
- **Redundancy**: Never save critical analysis only in the transient "Brain" folder. If it is important for the project's understanding, it belongs in `./docs/`.
- **Naming**: Use descriptive, lowercase filenames with hyphens (e.g., `inventory-logic-analysis.md`).

---

## 💻 3. Coding Standards
- **DB Interaction**: Always use **PDO** with prepared statements. Direct `mysql_*` or `mysqli_*` calls are prohibited.
- **Project Structure**:
    - Centralized initialization via `require_once 'includes/init.php'`.
    - Handlers and controllers reside in the `process/` directory.
    - Reusable UI elements in `includes/`.
- **Naming Conventions**:
    - **Variables/Functions**: `snake_case` (consistent with existing codebase).
    - **Classes**: `PascalCase`.
- **Comments**: Code comments should be clear and professional, preferably in **Vietnamese** to match the core developer context, or English for external libraries.
- **I18n**: Use the `$lang` array for all UI strings. Hardcoded text in HTML/PHP files should be avoided.

---

## 🔒 4. Security Mandates
- **SQL Injection**: Use placeholder parameters (`:name` or `?`) for all user-provided data in SQL queries.
- **XSS Prevention**: Always wrap dynamic output in `htmlspecialchars()` before rendering to the DOM.
- **Authentication**: Every protected page must invoke `require_login()` (from `auth_check.php`) at the very top.
- **File Safety**: 
    - Never serve files directly from `uploads/`. 
    - All file access must go through `file.php` for session-based authorization checks.
- **Directory Protection**: Maintain `.htaccess` blocks for `config/`, `includes/`, and `vendor/` directories.

---

## 🌟 5. Best Practices
- **Error Handling**: Use `try-catch` blocks for database operations and critical API calls. Log errors to `user_logs` table or system logs using `write_user_log()`.
- **Modular Logic**: Keep UI and logic separated. Perform heavy data processing in `process/` files and return JSON or redirect back to view files.
- **Performance**: Use SQL Views (e.g., `v_inventory_atp`) for complex stock calculations to keep PHP code clean and performant.
- **Automatic Maintenance**: Support and respect the system's auto-cleanup procedures for logs and temporary files.

---

## 🤖 6. Agent Behavior & Feature Updates
- **Validation First**: Before proposing changes, analyze the impact on existing SQL Views and the `$lang` localized mapping.
- **Documentation Updates**: After implementing a new feature or fixing a bug, update the corresponding documentation in `./docs/`.
- **Consistency**: Maintain the "Professional Modern Redesign" aesthetic (vibrant colors, clean cards, shadow-sm) for all new UI components.
- **State Awareness**: Always verify `$_SESSION` and `is_logged_in()` status when developing features that interact with user data.
