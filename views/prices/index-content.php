<div class="d-flex justify-content-between mb-3"><h4>لیست قیمت‌ها</h4><a href="/pricing/public/prices/export-green" class="btn btn-success">خروجی اکسل</a></div><form id="filter" class="row g-2 mb-3"><div class="col-md-4"><input id="q" name="q" value="<?=\App\Core\Security::e($q)?>" class="form-control" placeholder="کد یا نام کالا"></div><div class="col-md-3"><select id="status-filter" name="status_filter" class="form-select"><option value="all" <?=($statusFilter ?? 'all') === 'all' ? 'selected' : ''?>>فیلتر</option><option value="green" <?=($statusFilter ?? '') === 'green' ? 'selected' : ''?>>سبز</option><option value="yellow" <?=($statusFilter ?? '') === 'yellow' ? 'selected' : ''?>>زرد</option><option value="orange" <?=($statusFilter ?? '') === 'orange' ? 'selected' : ''?>>نارنجی</option><option value="red" <?=($statusFilter ?? '') === 'red' ? 'selected' : ''?>>قرمز</option></select></div><div class="col-auto d-flex align-items-center"><span class="text-muted">تعداد رکورد: <strong><?=number_format((int) ($total ?? 0))?></strong></span></div><div class="col-auto"><button class="btn btn-secondary">جستجو</button></div></form><div id="price-table"><?php $viewFile=BASE_PATH.'/views/prices/table.php';require $viewFile;?></div><?php if ($pages > 1): ?>
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
        <a class="page-link" href="?q=<?=urlencode($q)?>&status_filter=<?=urlencode($statusFilter ?? 'all')?>&page=<?=$i?>&per_page=100"><?=$i?></a>
    </li>
<?php $last = $i; endforeach; ?>
</ul>
</nav>
<?php endif; ?>