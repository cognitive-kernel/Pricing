# Backup و Restore

## Backup

در Windows/XAMPP اجرای `database/backup.ps1` یک فایل Backup از دیتابیس Pricing ایجاد می‌کند. مسیر خروجی را قبل از استفاده در محیط Production بررسی کنید.

## Restore

1. سرویس SQL Server را متوقف نکنید؛ فقط اتصال‌های فعال به دیتابیس Pricing را کنترل کنید.
2. فایل Backup معتبر را انتخاب کنید.
3. در SQL Server Database را در حالت SINGLE_USER قرار دهید.
4. Restore را انجام دهید و سپس به MULTI_USER برگردانید.
5. `php database/migrate.php` را اجرا کنید تا Migrationهای idempotent اعمال شوند.
6. `php database/seed.php` را اجرا کنید تا Permissionها و داده‌های پایه تکمیل شوند.
7. سلامت سیستم را از `/admin/health` بررسی کنید.

## نگهداری

- Backup روزانه و یک نسخه خارج از سرور نگهداری شود.
- Restore به‌صورت دوره‌ای روی محیط آزمایشی تست شود.
- تاریخچه قیمت بدون نیاز سازمانی حذف نشود.
- قبل از Deployment از دیتابیس Backup گرفته شود.
