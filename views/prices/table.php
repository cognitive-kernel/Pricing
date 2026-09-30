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
    <th>کد</th>
    <th>نام کالا</th>
    <th>موجودی راهکاران</th>
    <th>اعلامیه راهکاران</th>
    <th>فاکتور راهکاران</th>
    <th>رصد بازار</th>
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
    $marketPrices = $prices[$p['id']] ?? [];
?>
<tr class="<?= Security::e($cls) ?> <?= $clickable ? 'price-action-row' : '' ?>"
    <?php if ($clickable): ?>data-href="/pricing/public/prices/<?= (int) $p['id'] ?>/edit" title="برای ثبت رصد بازار کلیک کنید"<?php endif; ?>>
    <td><?= Security::e((string) $p['product_code']) ?></td>
    <td><?= Security::e((string) $p['product_name']) ?></td>

    <td>
        <?php if ($rahkaran): ?>
            <?= number_format((float) ($rahkaran['MajorUnit_Remaining'] ?? 0), 2) ?>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

    <td>
        <?php if ($rahkaran): ?>
            <div><strong><?= number_format((float) ($rahkaran['Announcement_price'] ?? 0), 2) ?></strong> <?= Security::e((string) ($rahkaran['Announcement_unit'] ?? '')) ?></div>
            <div class="small text-muted">
                <?= Security::e((string) ($rahkaran['Announcement_date'] ?? '')) ?>
                · <?= (int) ($rahkaran['Announcement_diff'] ?? 0) ?> روز
            </div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

    <td>
        <?php if ($rahkaran && $rahkaran['invoice_price'] !== null): ?>
            <div><strong><?= number_format((float) $rahkaran['invoice_price'], 2) ?></strong> <?= Security::e((string) ($rahkaran['invoice_unit'] ?? '')) ?></div>
            <div class="small text-muted">
                <?= Security::e((string) ($rahkaran['invoice_date'] ?? '')) ?>
                <?php if ($rahkaran['invoice_diff'] !== null): ?> · <?= (int) $rahkaran['invoice_diff'] ?> روز<?php endif; ?>
            </div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

    <td>
        <?php if ($marketPrices): ?>
            <?php foreach ($marketPrices as $pr): ?>
                <div class="mb-1">
                    منبع <?= (int) $pr['source_no'] ?>:
                    <?= Security::e((string) $pr['supplier']) ?>
                    · <?= number_format((float) $pr['unit_price'], 2) ?>
                    · <?= Security::e(Jalali::fromGregorian($pr['price_received_at'])) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>

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
        <?php if (PermissionService::allows(Auth::userId(), 'prices.edit')): ?>
            <a class="btn btn-sm btn-outline-primary" href="/pricing/public/prices/<?= (int) $p['id'] ?>/edit">رصد بازار</a>
            <a class="btn btn-sm btn-outline-secondary" href="/pricing/public/prices/<?= (int) $p['id'] ?>/history">تاریخچه</a>
        <?php endif; ?>
        <?php if (PermissionService::allows(Auth::userId(), 'prices.delete')): ?>
            <form method="post" action="/pricing/public/prices/<?= (int) $p['id'] ?>/delete" class="d-inline" onsubmit="return confirm('حذف شود؟')">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
                <button class="btn btn-sm btn-outline-danger">حذف</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
