<?php
use App\Core\Auth;
use App\Core\Security;
use App\Services\PermissionService;
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=Security::e(APP_NAME)?></title><link href="/pricing/public/assets/vendor/bootstrap/bootstrap.rtl.min.css" rel="stylesheet"><link rel="stylesheet" href="/pricing/assets/css/app.css"></head><body>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark sticky-top"><div class="container-fluid"><a class="navbar-brand" href="/pricing/public/prices">Pricing</a><?php if(Auth::check()):$uid=Auth::userId();?>
<div class="d-flex gap-2 align-items-center flex-wrap">
<?php
$menus=[
 'عملیات قیمت'=>[
  ['prices.view','لیست قیمت‌ها','/pricing/public/prices'],
  ['prices.create','ثبت قیمت','/pricing/public/prices/create'],
  ['prices.queue.view','لیستی','/pricing/public/prices/queue'],
  ['products.bulk','شرکتی','/pricing/public/products/bulk'],
  ['search.view','جستجوی پیشرفته','/pricing/public/search'],
  ['workflow.view','گردش کار','/pricing/public/workflow'],
 ],
 'اطلاعات پایه'=>[
  ['suppliers.view','تأمین‌کنندگان','/pricing/public/admin/suppliers'],
  ['categories.view','دسته‌بندی محصولات','/pricing/public/admin/categories'],
  ['suppliers.reports','گزارش تأمین‌کنندگان','/pricing/public/admin/supplier-reports'],
  ['admin.units.view','واحدها','/pricing/public/admin/units'],
 ],
 'گزارش‌ها'=>[
  ['dashboard.view','داشبورد','/pricing/public/dashboard'],
  ['reports.view','گزارش قیمت‌ها','/pricing/public/reports'],
  ['audit.view','گزارش فعالیت','/pricing/public/admin/audit'],
 ],
 'اعلان‌ها'=>[
  ['notifications.view','اعلان‌ها','/pricing/public/notifications'],
  ['notifications.manage','تنظیم اعلان‌ها','/pricing/public/admin/notification-settings'],
 ],
 'ورود اطلاعات'=>[
  ['imports.view','Import محصولات','/pricing/public/imports/products'],
  ['imports.manage','Import پیشرفته','/pricing/public/imports/advanced'],
 ],
 'سیستم'=>[
  ['settings.view','تنظیمات','/pricing/public/settings'],
  ['queue.settings','تنظیمات صف','/pricing/public/admin/queue-settings'],
  ['system.health','سلامت سیستم','/pricing/public/admin/health'],
  ['security.manage','Sessionها','/pricing/public/admin/sessions'],
  ['api.tokens.manage','API Token','/pricing/public/api-tokens'],
  ['api.docs.view','مستندات API','/pricing/public/api-docs'],
 ],
 'مدیریت دسترسی'=>[
  ['admin.users.view','کاربران','/pricing/public/admin/users'],
  ['admin.groups.view','گروه‌ها و دسترسی‌ها','/pricing/public/admin/groups'],
 ],
];
foreach($menus as $label=>$items){
 $visible=array_values(array_filter($items,fn($m)=>PermissionService::allows($uid,$m[0])));
 if(!$visible)continue;
 ?><div class="dropdown">
 <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown"><?=$label?></button>
 <ul class="dropdown-menu dropdown-menu-end">
 <?php foreach($visible as $m):?><li><a class="dropdown-item" href="<?=$m[2]?>"><?=$m[1]?></a></li><?php endforeach;?>
 </ul></div>
<?php }
?>
<div class="dropdown"><button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">حساب من</button><ul class="dropdown-menu dropdown-menu-end"><?php if(PermissionService::allows($uid,'account.password.change')):?><li><a class="dropdown-item" href="/pricing/public/password">تغییر رمز</a></li><?php endif;?><li><hr class="dropdown-divider"></li><li><form method="post" action="/pricing/public/logout" class="px-2"><input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>"><button class="btn btn-sm btn-outline-danger w-100">خروج</button></form></li></ul></div>
</div><?php endif;?></div></nav>
<main class="container-fluid py-3"><?php if(!empty($success)):?><div class="alert alert-success"><?=Security::e($success)?></div><?php endif;?><?php if(!empty($error)):?><div class="alert alert-danger"><?=Security::e($error)?></div><?php endif;?><?php require $viewFile;?></main>
<script src="/pricing/public/assets/vendor/jquery/jquery.min.js"></script><script src="/pricing/public/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script><script src="/pricing/assets/js/app.js?v=20261031-1"></script><script src="/pricing/assets/js/price-approvals.js?v=20261012-2"></script></body></html>