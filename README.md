# Evidence Manager Dashboard

A secure, mobile-friendly web application designed to assist first responders and law enforcement in recording, managing, and tracking evidence collected at crime scenes. The system provides structured data entry, evidence categorisation, chain-of-custody tracking, and integrated guidance on correct evidence handling procedures.

## Project Purpose

Evidence management is critical in law enforcement. This dashboard aims to:

- **Reduce errors** — structured forms guide officers through required fields
- **Improve consistency** — standardised categories and status workflows
- **Maintain chain of custody** — every action (create, update, view) is logged with timestamp and user
- **Ensure security** — role-based access, authentication, and encrypted storage prevent unauthorised access
- **Support compliance** — audit trails and procedural guidance ensure adherence to UK standards and best practices

## Features

- **Role-based access** — separate privileges for administrators and officers
- **Evidence dashboard** — search, filter, and view all logged evidence records
- **Evidence submission** — structured form with photo upload and validation
- **Evidence categorisation** — Physical Evidence, Digital Media, Documents, Biological, Firearms, Narcotics, Vehicle, Other
- **Chain of custody tracking** — immutable audit log showing who created/updated/viewed each record
- **Integrated help** — guidance on correct evidence handling, contamination prevention, and digital evidence best practices
- **User management** — admin can create and manage officer accounts
- **Secure file storage** — uploaded photos/videos are protected from direct HTTP access

## Quick Start

### 1. Prerequisites

- **PHP 8.0+** (with PDO MySQL extension)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Apache** with `mod_rewrite` and `mod_access` enabled
- A local development environment (e.g., XAMPP, MAMP, or Docker)

### 2. Database Setup

Create a new database and import the schema:

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE evidence_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import the schema
mysql -u root -p evidence_manager < sql/schema.sql
```

The schema includes a default admin user:
- **Username:** `admin`
- **Password:** `Admin@1234`

⚠️ **IMPORTANT:** Change the admin password immediately after first login. See [Changing the Admin Password](#changing-the-admin-password) below.

### 3. Configure Database Credentials

Edit [config/db.php](config/db.php) and update these values to match your MySQL setup:

```php
define('DB_HOST',    'localhost');       // MySQL hostname
define('DB_PORT',    '3306');            // MySQL port (default 3306)
define('DB_NAME',    'evidence_manager'); // Database name (as created above)
define('DB_USER',    'root');            // MySQL username — CHANGE THIS
define('DB_PASS',    '');                // MySQL password — CHANGE THIS
```

### 4. Configure Application URL

Edit [config/constants.php](config/constants.php):

```php
// If the app is at http://localhost/ (web root):
define('BASE_URL', '');

// If the app is in a subfolder, e.g. http://localhost/evidence-manager:
define('BASE_URL', '/evidence-manager');
```

### 5. Set Upload Directory Permissions

The web server must be able to write to the uploads directory:

```bash
chmod 755 uploads/evidence/
```

If you're using Docker or a containerised setup, ensure the directory is owned by the web server user (typically `www-data` on Apache):

```bash
chown -R www-data:www-data uploads/evidence/
chmod 755 uploads/evidence/
```

### 6. Test the Installation

Open your browser and navigate to:

```
http://localhost/
```

If `BASE_URL` is set, use:

```
http://localhost/evidence-manager/
```

You should be redirected to the login page. Log in with:
- Username: `admin`
- Password: `Admin@1234`

## Changing the Admin Password

After first login, change the admin password immediately:

### Method 1: Using PHP (Command Line)

```bash
php -r "echo password_hash('YourNewSecurePassword', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Take the output hash (a long string starting with `$2y$`), then update the database:

```sql
UPDATE users 
SET password_hash = 'PASTE_THE_HASH_HERE'
WHERE username = 'admin';
```

### Method 2: Programmatically (Once Admin Interface Exists)

In Phase 2+, an admin user management interface will allow password changes via the web UI.

## Project Structure

```
evidence-manager/
├── config/                    # Application configuration
│   ├── db.php                # Database connection (PDO)
│   └── constants.php         # App name, roles, categories, upload settings
├── includes/                 # Reusable includes
│   ├── auth.php              # Authentication functions (login/logout guards)
│   ├── functions.php         # Utility functions (flash messages, CSRF, validation)
│   ├── header.php            # HTML header, nav bar, security headers
│   └── footer.php            # HTML footer, script includes
├── assets/                   # Static files
│   ├── css/
│   │   └── style.css         # Dashboard theme (light gray, white cards, red buttons)
│   └── js/
│       └── app.js            # Client-side interactions (form validation, nav toggle)
├── auth/                     # Authentication pages (Phase 2)
│   ├── login.php
│   └── logout.php
├── dashboard/                # Evidence dashboard
│   └── index.php
├── evidence/                 # Evidence management
│   ├── submit.php            # Submit new evidence
│   ├── view.php              # View single record
│   └── edit.php              # Edit existing record (admin/owner only)
├── users/                    # User management (admin only)
│   ├── index.php
│   ├── create.php
│   └── edit.php
├── audit/                    # Chain of custody audit log (admin only)
│   └── index.php
├── help/                     # Integrated help & guidance
│   └── index.php
├── uploads/                  # User-uploaded evidence files
│   ├── evidence/
│   ├── .htaccess             # Blocks direct HTTP access to files
│   └── .gitkeep
├── sql/
│   └── schema.sql            # Full database schema with comments
├── index.php                 # Application entry point (redirects to login or dashboard)
└── README.md                 # This file
```

## Security Features

The application includes multiple security layers:

- **Authentication** — bcrypt password hashing, session-based login
- **Authorization** — role-based access control (admin vs. officer)
- **CSRF Protection** — tokens on all forms
- **File Upload Validation** — MIME type verification + file extension checks
- **Prepared Statements** — all database queries use PDO with bound parameters
- **Secure Session Cookies** — HttpOnly, SameSite=Lax, secure flag for HTTPS
- **Security Headers** — X-Frame-Options, X-Content-Type-Options, Content-Security-Policy
- **Audit Logging** — immutable chain of custody with IP address and user agent capture

## Database Schema

The application uses five tables:

| Table | Purpose |
|---|---|
| `users` | Officer and admin accounts with bcrypt password hashes |
| `evidence` | Main evidence records (case number, category, status, timestamps) |
| `evidence_notes` | Free-text notes attached to evidence records |
| `evidence_files` | Uploaded photos, videos, documents (stored with randomised filenames) |
| `audit_log` | Immutable action log (created, updated, viewed, file_uploaded, deleted) |

See [sql/schema.sql](sql/schema.sql) for the full schema with detailed comments.

## Development Roadmap

### Phase 1 — Foundation (Complete)
- Database schema ✓
- Configuration files ✓
- Shared utility functions ✓
- CSS dashboard theme ✓
- Security foundations ✓

### Phase 2 — Authentication
- Login page and POST handler
- Logout page
- Session management

### Phase 3 — Evidence Core
- Submit evidence form
- Evidence dashboard with search/filter
- View single evidence record

### Phase 4 — Admin Features
- Edit evidence records
- User management interface
- Audit log viewer

### Phase 5 — Help & Polish
- Help page with procedures
- Chain of custody guidance
- Responsive polish and testing

### Phase 6 — Security Hardening
- CSRF token integration
- Rate limiting on login
- File upload controller
- Production deployment guide

## Production Deployment

Before deploying to production:

1. **Enable HTTPS** — set `secure` to `true` in [includes/functions.php](includes/functions.php#L17)
2. **Use strong database password** — avoid default credentials
3. **Disable debug output** — remove any error logging to stdout
4. **Use environment variables** — store secrets outside the codebase
5. **Set up regular backups** — database and uploaded evidence files
6. **Enable audit logging review** — regularly check [audit/index.php](audit/index.php) for suspicious activity
7. **Remove deployment files** — delete this README, sql/, and any .git directories from production

See [sql/schema.sql](sql/schema.sql) for instructions on removing the `DROP TABLE` statements once in production.

## Support & Contributing

For issues, questions, or feature requests, please open an issue on the project repository.

## License

This project is provided as-is for first responder and law enforcement use.
