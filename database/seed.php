<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$permissions=['prices.view'=>'مشاهده لیست قیمت','prices.create'=>'ثبت قیمت','prices.edit'=>'ویرایش قیمت','prices.delete'=>'حذف قیمت','admin.units.view'=>'مشاهده واحدها','admin.units.create'=>'ثبت واحد','admin.units.edit'=>'ویرایش واحد','admin.units.delete'=>'حذف واحد','admin.groups.view'=>'مشاهده گروه‌ها','admin.groups.create'=>'ثبت گروه','admin.groups.permissions'=>'مدیریت دسترسی گروه','admin.users.view'=>'مشاهده کاربران','admin.users.create'=>'ثبت کاربر','admin.users.permissions'=>'مدیریت دسترسی کاربر'];
foreach($permissions as $code=>$name){$s=$pdo->prepare('IF NOT EXISTS (SELECT 1 FROM permissions WHERE code=:code) INSERT INTO permissions(code,name,type,created_at,updated_at) VALUES(:code,:name,:type,GETDATE(),GETDATE())');$s->execute(['code'=>$code,'name'=>$name,'type'=>str_starts_with($code,'admin.')?'admin':'form']);}
$s=$pdo->prepare('SELECT id FROM groups WHERE name=:name');$s->execute(['name'=>'مدیر سیستم']);$gid=$s->fetchColumn();
if(!$gid){$pdo->prepare('INSERT INTO groups(name,description,created_at,updated_at) VALUES(:n,:d,GETDATE(),GETDATE())')->execute(['n'=>'مدیر سیستم','d'=>'دسترسی کامل']);$gid=(int)$pdo->lastInsertId();}
foreach($permissions as $code=>$name){$s=$pdo->prepare('IF NOT EXISTS (SELECT 1 FROM group_permissions WHERE group_id=:gid AND permission_code=:code) INSERT INTO group_permissions(group_id,permission_code,created_at,updated_at) VALUES(:gid,:code,GETDATE(),GETDATE())');$s->execute(['gid'=>$gid,'code'=>$code]);}
$initial=trim((string)env('ADMIN_INITIAL_PASSWORD',''));
if($initial!==''){$s=$pdo->prepare('SELECT id FROM users WHERE username=:u');$s->execute(['u'=>'admin']);$uid=$s->fetchColumn();if(!$uid){$pdo->prepare('INSERT INTO users(username,name,password,is_active,created_at,updated_at) VALUES(:u,:n,:p,1,GETDATE(),GETDATE())')->execute(['u'=>'admin','n'=>'مدیر سیستم','p'=>password_hash($initial,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();}$s=$pdo->prepare('IF NOT EXISTS (SELECT 1 FROM group_user WHERE group_id=:gid AND user_id=:uid) INSERT INTO group_user(group_id,user_id) VALUES(:gid,:uid)');$s->execute(['gid'=>$gid,'uid'=>$uid]);}
$pdo->prepare('IF NOT EXISTS (SELECT 1 FROM units WHERE name=:n) INSERT INTO units(name,code,is_active,created_at,updated_at) VALUES(:n,:c,1,GETDATE(),GETDATE())')->execute(['n'=>'عدد','c'=>'EA']);
echo "Seed completed.\n";
