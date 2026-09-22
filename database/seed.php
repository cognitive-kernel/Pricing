<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $permissionGroups = [
        'عملیات قیمت'=>[
            'prices.view'=>'مشاهده لیست قیمت','prices.create'=>'ثبت قیمت','prices.edit'=>'ویرایش قیمت','prices.delete'=>'حذف قیمت','prices.history.view'=>'مشاهده تاریخچه قیمت',
            'prices.queue.view'=>'مشاهده صف استعلام','prices.queue.manage'=>'مدیریت صف قیمت','search.view'=>'جستجوی پیشرفته','search.filters.manage'=>'مدیریت فیلترهای ذخیره‌شده','products.bulk'=>'عملیات گروهی محصولات','workflow.view'=>'مشاهده گردش کار','workflow.manage'=>'مدیریت گردش کار','product.sources.view'=>'مشاهده منابع محصول','product.sources.manage'=>'مدیریت منابع محصول'
        ],
        'اطلاعات پایه'=>[
            'admin.units.view'=>'مشاهده واحدها','admin.units.create'=>'ثبت واحد','admin.units.edit'=>'ویرایش واحد','admin.units.delete'=>'غیرفعال‌سازی واحد',
            'categories.view'=>'مشاهده دسته محصولات','categories.manage'=>'مدیریت دسته محصولات','suppliers.reports'=>'گزارش عملکرد تأمین‌کنندگان','suppliers.view'=>'مشاهده تأمین‌کنندگان','suppliers.create'=>'ثبت تأمین‌کننده','suppliers.edit'=>'ویرایش تأمین‌کننده','suppliers.delete'=>'غیرفعال‌سازی تأمین‌کننده'
        ],
        'گزارش‌ها'=>[
            'dashboard.view'=>'مشاهده داشبورد','reports.view'=>'مشاهده گزارش‌ها','reports.export'=>'خروجی گزارش‌ها','audit.view'=>'مشاهده گزارش فعالیت','reports.advanced'=>'گزارش‌های تحلیلی'
        ],
        'اعلان‌ها'=>[
            'notifications.view'=>'مشاهده اعلان‌ها','notifications.manage'=>'مدیریت اعلان‌ها','notifications.email'=>'ارسال اعلان ایمیلی',
            'api.docs.view'=>'مشاهده مستندات API'
        ],
        'ورود اطلاعات'=>[
            'imports.view'=>'مشاهده Import','imports.manage'=>'اجرای Import','imports.preview'=>'پیش‌نمایش Import'
        ],
        'سیستم'=>[
            'account.password.change'=>'تغییر رمز عبور',
            'settings.view'=>'مشاهده تنظیمات','settings.manage'=>'مدیریت تنظیمات','queue.settings'=>'تنظیمات موتور صف','system.health'=>'سلامت سیستم','security.manage'=>'مدیریت امنیت و Session','security.policy'=>'سیاست امنیتی',
            'api.tokens.manage'=>'مدیریت API Token','api.products.view'=>'دسترسی API محصولات','api.prices.view'=>'دسترسی API قیمت‌ها','api.queue.view'=>'دسترسی API صف'
        ],
        'مدیریت دسترسی'=>[
            'admin.groups.view'=>'مشاهده گروه‌ها','admin.groups.create'=>'ثبت گروه','admin.groups.edit'=>'ویرایش گروه','admin.groups.permissions'=>'مدیریت دسترسی گروه',
            'admin.users.view'=>'مشاهده کاربران','admin.users.create'=>'ثبت کاربر','admin.users.edit'=>'ویرایش اطلاعات کاربر','admin.users.status'=>'فعال/غیرفعال‌سازی کاربر','admin.users.permissions'=>'مدیریت دسترسی کاربر'
        ]
    ];
    $permissions = [];
    foreach ($permissionGroups as $group => $items) foreach ($items as $code => $name) $permissions[$code] = ['name'=>$name,'group'=>$group];

    foreach ($permissions as $code => $meta) {
        $s = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
        $s->execute(['code' => $code]);
        if ($s->fetchColumn() === false) {
            $s = $pdo->prepare('INSERT INTO permissions(code,name,type,permission_group,created_at,updated_at) VALUES(:code,:name,:type,:grp,GETDATE(),GETDATE())');
            $s->execute(['code'=>$code,'name'=>$meta['name'],'type'=>str_starts_with($code,'admin.')?'admin':'form','grp'=>$meta['group']]);
        } else {
            $s = $pdo->prepare('UPDATE permissions SET name=:name,type=:type,permission_group=:grp,updated_at=GETDATE() WHERE code=:code');
            $s->execute(['code'=>$code,'name'=>$meta['name'],'type'=>str_starts_with($code,'admin.')?'admin':'form','grp'=>$meta['group']]);
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

    foreach ($permissions as $code => $meta) {
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
