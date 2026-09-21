<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $permissions = [
        'prices.view' => 'مشاهده لیست قیمت',
        'prices.create' => 'ثبت قیمت',
        'prices.edit' => 'ویرایش قیمت',
        'prices.delete' => 'حذف قیمت',
        'admin.units.view' => 'مشاهده واحدها',
        'admin.units.create' => 'ثبت واحد',
        'admin.units.edit' => 'ویرایش واحد',
        'admin.units.delete' => 'حذف واحد',
        'admin.groups.view' => 'مشاهده گروه‌ها',
        'admin.groups.create' => 'ثبت گروه',
        'admin.groups.permissions' => 'مدیریت دسترسی گروه',
        'admin.users.view' => 'مشاهده کاربران',
        'admin.users.create' => 'ثبت کاربر',
        'admin.users.permissions' => 'مدیریت دسترسی کاربر',
        'dashboard.view' => 'مشاهده داشبورد',
        'prices.queue.manage' => 'مدیریت صف قیمت',
        'suppliers.view' => 'مشاهده تأمین‌کنندگان',
        'suppliers.create' => 'ثبت تأمین‌کننده',
        'notifications.view' => 'مشاهده اعلان‌ها',
        'audit.view' => 'مشاهده گزارش فعالیت',
        'reports.view' => 'مشاهده گزارش‌ها',
        'reports.export' => 'خروجی گزارش‌ها',
        'settings.view' => 'مشاهده تنظیمات',
        'settings.manage' => 'مدیریت تنظیمات',
        'imports.view' => 'مشاهده Import',
        'imports.manage' => 'اجرای Import',
        'api.tokens.manage' => 'مدیریت API Token',
        'categories.view' => 'مشاهده دسته محصولات',
        'categories.manage' => 'مدیریت دسته محصولات',
        'suppliers.edit' => 'ویرایش تأمین‌کننده',
        'suppliers.delete' => 'غیرفعال‌سازی تأمین‌کننده',
        'prices.history.view' => 'مشاهده تاریخچه قیمت',
        'search.view' => 'جستجوی پیشرفته',
        'products.bulk' => 'عملیات گروهی محصولات',
        'workflow.view' => 'مشاهده گردش کار',
        'workflow.manage' => 'مدیریت گردش کار',
        'queue.settings' => 'تنظیمات موتور صف',
        'system.health' => 'سلامت سیستم',
        'notifications.manage' => 'مدیریت اعلان‌ها',
    ];

    foreach ($permissions as $code => $name) {
        $s = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
        $s->execute(['code' => $code]);

        if ($s->fetchColumn() === false) {
            $s = $pdo->prepare('INSERT INTO permissions(code,name,type,created_at,updated_at) VALUES(:code,:name,:type,GETDATE(),GETDATE())');
            $s->execute([
                'code' => $code,
                'name' => $name,
                'type' => str_starts_with($code, 'admin.') ? 'admin' : 'form',
            ]);
        }
    }

    $s = $pdo->prepare('SELECT id FROM groups WHERE name = :name');
    $s->execute(['name' => 'مدیر سیستم']);
    $gid = $s->fetchColumn();

    if ($gid === false) {
        $s = $pdo->prepare('INSERT INTO groups(name,description,created_at,updated_at) OUTPUT INSERTED.id VALUES(:name,:description,GETDATE(),GETDATE())');
        $s->execute(['name' => 'مدیر سیستم', 'description' => 'دسترسی کامل']);
        $gid = $s->fetchColumn();
    }
    $gid = (int) $gid;

    foreach ($permissions as $code => $name) {
        $s = $pdo->prepare('SELECT 1 FROM group_permissions WHERE group_id = :gid AND permission_code = :code');
        $s->execute(['gid' => $gid, 'code' => $code]);

        if ($s->fetchColumn() === false) {
            $s = $pdo->prepare('INSERT INTO group_permissions(group_id,permission_code,created_at,updated_at) VALUES(:gid,:code,GETDATE(),GETDATE())');
            $s->execute(['gid' => $gid, 'code' => $code]);
        }
    }

    $initial = trim((string) env('ADMIN_INITIAL_PASSWORD', ''));

    if ($initial !== '') {
        $s = $pdo->prepare('SELECT id FROM users WHERE username = :username');
        $s->execute(['username' => 'admin']);
        $uid = $s->fetchColumn();

        if ($uid === false) {
            $s = $pdo->prepare('INSERT INTO users(username,name,password,is_active,created_at,updated_at) OUTPUT INSERTED.id VALUES(:username,:name,:password,1,GETDATE(),GETDATE())');
            $s->execute([
                'username' => 'admin',
                'name' => 'مدیر سیستم',
                'password' => password_hash($initial, PASSWORD_DEFAULT),
            ]);
            $uid = $s->fetchColumn();
        }

        $uid = (int) $uid;

        $s = $pdo->prepare('SELECT 1 FROM group_user WHERE group_id = :gid AND user_id = :uid');
        $s->execute(['gid' => $gid, 'uid' => $uid]);

        if ($s->fetchColumn() === false) {
            $s = $pdo->prepare('INSERT INTO group_user(group_id,user_id) VALUES(:gid,:uid)');
            $s->execute(['gid' => $gid, 'uid' => $uid]);
        }
    }

    $s = $pdo->prepare('SELECT id FROM units WHERE name = :name');
    $s->execute(['name' => 'عدد']);

    if ($s->fetchColumn() === false) {
        $s = $pdo->prepare('INSERT INTO units(name,code,is_active,created_at,updated_at) VALUES(:name,:code,1,GETDATE(),GETDATE())');
        $s->execute(['name' => 'عدد', 'code' => 'EA']);
    }

    $pdo->commit();
    echo "Seed completed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
