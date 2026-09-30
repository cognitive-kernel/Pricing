<?php
use App\Core\Auth;
use App\Core\Security;
use App\Services\PermissionService;
use App\Support\Jalali;
?>
<div class="table-wrap">
<table class="table table-bordered table-hover align-middle">
<thead>
<tr>
<th>کد کالا</th>
<th>نام کالا</th>
<th>قیمت‌های سه منبع</th>
<th>راهکاران</th>
<th>واحد</th>
<th>تاریخ ثبت</th>
<th>وضعیت</th>
<th>عملیات</th>
</tr>
</thead>
<tbody>
<?php if (!$products): ?>
<tr><td colspan="8" class="text-center">رکوردی یافت نشد.</td></tr>
<?php endif; ?>

<?php foreach ($products as $p):
    $status = (string) ($p['row_status'] ?? 'neutral');
    $clickable = in_array($status, ['yellow', 'orange', 'red'], true) && PermissionService::allows(Auth::userId(), 'prices.edit');
    $cls = 'status-' . $status;
    $rahkaran = $p['rahkaran'] ?? null;
    $required = (int) ($p['required_sources'] ?? 0);
?>
<tr class="<?=Security::e($cls)?> <?=$clickable?'price-action-row':''?>"
    <?php if ($clickable): ?>data-href="/pricing/public/prices/<?= (int) $p['id'] ?>/edit" title="برای ثبت رصد بازار کلیک کنید"<?php endif; ?>>
    <td><?=Security::e($p['product_code'])?></td>
    <td><?=Security::e($p['product_name'])?></td>
    <td>
        <?php foreach (($prices[$p['id']] ?? []) as $pr): ?>
        <div>
            منبع <?= (int) $pr['source_no'] ?>:
            <?=Security::e($pr['supplier'])?> |
            بسته <?=number_format((float) $pr['package_price'], 2)?> |
            واحد <?=number_format((float) $pr['unit_price'], 2)?> |
            <?=Security::e(Jalali::fromGregorian($pr['price_received_at']))?>
        </div>
        <?php endforeach; ?>
    </td>
    <td>
        <?php if ($rahkaran): ?>
            <div><strong>اعلامیه:</strong> <?=number_format((float) $rahkaran['Announcement_price'], 2)?> <?=Security::e((string) $rahkaran['Announcement_unit'])?></div>
            <div class="small text-muted"><?=Security::e((string) $rahkaran['Announcement_date'])?> · <?= (int) $rahkaran['Announcement_diff'] ?> روز</div>
            <div><strong>فاکتور:</strong> <?= $rahkaran['invoice_price'] !== null ? number_format((float) $rahkaran['invoice_price'], 2) : '—' ?> <?=Security::e((string) ($rahkaran['invoice_unit'] ?? ''))?></div>
            <div class="small text-muted"><?=Security::e((string) ($rahkaran['invoice_date'] ?? ''))?><?= $rahkaran['invoice_diff'] !== null ? ' · ' . (int) $rahkaran['invoice_diff'] . ' روز' : '' ?></div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
    <td><?=Security::e($p['unit_name'])?></td>
    <td><?=Security::e(Jalali::fromGregorian($p['registered_at']))?></td>
    <td>
        <?php if ($status === 'green'): ?>
            سبز
        <?php elseif ($status === 'yellow'): ?>
            زرد — حداقل <?= $required ?> قیمت
        <?php elseif ($status === 'orange'): ?>
            نارنجی — حداقل <?= $required ?> قیمت
        <?php elseif ($status === 'red'): ?>
            قرمز — <?= $required ?> قیمت الزامی
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
    <td class="text-nowrap">
        <?php if (PermissionService::allows(Auth::userId(), 'product.sources.view')): ?>
            <a class="btn btn-sm btn-outline-info" href="/pricing/public/admin/product-sources/<?= (int) $p['id'] ?>">منابع</a>
        <?php endif; ?>
        <?php if (PermissionService::allows(Auth::userId(), 'prices.edit')): ?>
            <a class="btn btn-sm btn-outline-primary" href="/pricing/public/prices/<?= (int) $p['id'] ?>/edit">ویرایش</a>
            <a class="btn btn-sm btn-outline-secondary" href="/pricing/public/prices/<?= (int) $p['id'] ?>/history">تاریخچه</a>
        <?php endif; ?>
        <?php if (PermissionService::allows(Auth::userId(), 'prices.delete')): ?>
            <form method="post" action="/pricing/public/prices/<?= (int) $p['id'] ?>/delete" class="d-inline" onsubmit="return confirm('حذف شود؟')">
                <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
                <button class="btn btn-sm btn-outline-danger">حذف</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<script>
document.querySelectorAll('.price-action-row[data-href]').forEach(function (row) {
    row.addEventListener('click', function (event) {
        if (event.target.closest('a,button,input,form,select,textarea')) return;
        window.location.href = row.dataset.href;
    });
});
</script>
