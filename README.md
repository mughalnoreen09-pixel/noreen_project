# SalonDiva PHP Authentication & Access Control (Starter Backend)

A small PHP 8.1+ / MySQL backend for the SalonDiva login interface. It supports:
- Username or email + password login
- Server-side role lookup (admin, receptionist, stylist)
- Password hashing with PHP's `password_hash()` / `password_verify()`
- Session cookie hardening and session ID rotation
- CSRF tokens for state-changing forms
- Public staff registration for receptionist/stylist accounts with pending admin approval
- Admin approval/rejection of pending staff accounts via the admin users API
- Role-protected example dashboard endpoints
- Basic login throttling using session state (use a shared database/rate limiter for production)

## Requirements
- PHP 8.1 or newer with PDO MySQL enabled
- MySQL 8+ or MariaDB
- Apache/Nginx or PHP's local development server

## Setup
1. Create a MySQL database and user. For local development, import `database/schema.sql`.
2. Configure environment variables (recommended) or edit `app/config.php`.
3. Point the web server document root to the `public/` directory. Do **not** expose `app/` or `database/` to the web.
4. Run the one-time admin creation script from the terminal:
   ```bash
   php app/create_admin.php "Salon Admin" "admin@example.com" "Choose-A-Long-Unique-Password"
   ```
   This script creates an admin account. Don't use the sample password in production.
5. Set your database values as environment variables:
   - `SALONDIVA_DB_HOST` (default `127.0.0.1`)
   - `SALONDIVA_DB_NAME` (default `salondiva`)
   - `SALONDIVA_DB_USER` (default `salondiva_app`)
   - `SALONDIVA_DB_PASS` (default empty; set a strong password)
   - `SALONDIVA_SESSION_SECURE=1` when serving the site over HTTPS
6. Connect your existing HTML login form to `public/login.php`:
   - Set form `action="/login.php"` and `method="post"`.
   - Ensure fields are named `username` and `password`.
   - Include a hidden CSRF input named `_csrf` whose value is from `csrf_token()`. See the form integration example below.
7. For logout, POST to `public/logout.php` with `_csrf`.
8. Example protected endpoint:
   - `GET /dashboard.php` redirects unauthenticated users to `index.php`.
   - `GET /api/me.php` returns current user and role as JSON.
   - `GET /api/admin/users.php` is admin-only and lists users without password hashes.
   - `POST /api/admin/users.php` is admin-only and creates staff accounts (JSON body: `name`, `email`, `password`, `role`).

## Integrating the existing login form
The HTML prototype currently has a JavaScript submit handler that prevents real submission. Remove that handler and add the following hidden field inside `#loginForm`:
```html
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
```
Because PHP must render that value, save/serve the login page as a PHP template (for example `public/index.php`) and include `app/bootstrap.php`, or add a small PHP-rendered login page. Don't put a hardcoded CSRF token in HTML.

Then update the form:
```html
<form id="loginForm" action="login.php" method="post">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input name="username" autocomplete="username" required>
  <input name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Sign in securely</button>
</form>
```

## Important security notes
- Role selection in the browser is only a UI preview. The backend ignores any submitted role and loads the role from the database.
- `create_admin.php` is CLI-only and refuses to run from a web request.
- Login throttling in this starter is per PHP session, so it is not sufficient against distributed attacks. Use a shared rate-limit store and monitoring in production.
- Add HTTPS, secure secret management, email verification / password reset, audit logging, backups, and deployment-specific security review before going live.
- Protect every sensitive route with `require_role()` or an equivalent server-side authorization check.


## Existing database upgrade
If you already imported the previous version of `schema.sql`, run this SQL once in phpMyAdmin:
```sql
ALTER TABLE users
  ADD COLUMN account_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';
UPDATE users SET account_status = 'approved' WHERE role = 'admin';
```
After upgrading, ensure your existing admin account has `account_status='approved'`; otherwise it cannot log in. New public registrations are pending and cannot sign in until approved.
