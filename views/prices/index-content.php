<div class="d-flex justify-content-between mb-3"><h4>لیست قیمت‌ها</h4><?php if(\App\Services\PermissionService::allows(\App\Core\Auth::userId(),'prices.create')):?><div class="d-flex gap-2"><a href="/pricing/public/prices/queue" class="btn btn-warning">صف استعلام</a><a href="/pricing/public/prices/create" class="btn btn-primary">ثبت رصد قیمت</a></div><?php endif;?></div><form id="filter" class="row g-2 mb-3"><div class="col-md-4"><input id="q" name="q" value="<?=\App\Core\Security::e($q)?>" class="form-control" placeholder="کد یا نام کالا"></div><div class="col-md-3"><select id="status-filter" name="status_filter" class="form-select"><option value="all">فیلتر</option><option value="green">سبز</option><option value="yellow">زرد</option><option value="orange">نارنجی</option><option value="red">قرمز</option></select></div><div class="col-auto"><button class="btn btn-secondary">جستجو</button></div></form><div id="price-table"><?php $viewFile=BASE_PATH.'/views/prices/table.php';require $viewFile;?></div><?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="صفحه‌بندی">
<ul class="pagination flex-wrap">
<?php
$pagination = [];
$pagination[] = 1;
for ($i = max(2, $page - 2); $i <= min($pages - 1, $page + 2); $i++) {
    $pagination[] = $i;
}
if ($pages > 1) {
    $pagination[] = $pages;
}
$pagination = array_values(array_unique($pagination));
$last = 0;
foreach ($pagination as $i):
    if ($last && $i > $last + 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
    <?php endif; ?>
    <li class="page-item <?=$i === $page ? 'active' : ''?>">
        <a class="page-link" href="?q=<?=urlencode($q)?>&page=<?=$i?>&per_page=100"><?=$i?></a>
    </li>
<?php $last = $i; endforeach; ?>
</ul>
</nav>
<?php endif; ?>