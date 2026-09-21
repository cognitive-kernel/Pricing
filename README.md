# Pricing

Native PHP pricing observation and permission management system.

Stack: PHP 8.2+, PDO_SQLSRV, SQL Server, Bootstrap 5 RTL, jQuery 3.7, JavaScript and CSS.

Security: prepared statements, CSRF protection, output escaping, secure session cookies, session regeneration, server-side validation and permission checks.

Setup:
1. Configure database values in .env.
2. Point Apache to public/ or use /pricing/public.
3. Enable PDO_SQLSRV.
4. Run php database/seed.php when initial permissions/admin data are needed.
