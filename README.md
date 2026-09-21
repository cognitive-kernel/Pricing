# Pricing

Native PHP pricing observation and permission management system.

Stack: PHP 8.2+, PDO_SQLSRV, SQL Server, Bootstrap 5 RTL, jQuery 3.7, JavaScript and CSS.

Security: prepared statements, CSRF protection, output escaping, secure session cookies, session regeneration, server-side validation and permission checks.

Setup:
1. Configure database values in .env.
2. Point Apache to public/ or use /pricing/public.
3. Enable PDO_SQLSRV.
4. Run php database/seed.php when initial permissions/admin data are needed.


## راه‌اندازی قابلیت‌های جدید

1. فایل `database/schema.sql` را روی دیتابیس SQL Server اجرا کنید.
2. سپس `database/seed.php` را با PHP پروژه اجرا کنید تا Permissionهای جدید ثبت شوند.
3. برای بازسازی صف قیمت می‌توان `database/maintenance.php` را با PHP اجرا کرد.
4. گزارش‌ها، داشبورد، تأمین‌کنندگان، Import و API در مسیرهای ثبت‌شده در `routes/web.php` قرار دارند.
5. CI پروژه در `.github/workflows/ci.yml` بررسی Syntax فایل‌های PHP را انجام می‌دهد.

اتصال واقعی به ERP یا منابع خارجی قیمت از طریق قراردادهای `App\\Contracts\\ErpClient` و `App\\Contracts\\PriceSource` انجام می‌شود و نیازمند پیاده‌سازی Adapter متناسب با سرویس مقصد است.
