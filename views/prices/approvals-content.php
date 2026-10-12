<?php use App\Core\Security; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">تأیید رصد قیمت‌ها</h4>
    <a href="/pricing/public/prices/approvals/export" class="btn btn-success">خروجی اکسل قیمت‌های تأییدشده</a>
</div>
<form method="get" action="/pricing/public/prices/approvals" class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= Security::e($q) ?>" placeholder="جستجو بر اساس کد کالا یا منبع"></div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit">جستجو</button></div>
    <div class="col-auto d-flex align-items-center text-muted">تعداد در انتظار تأیید: <?= number_format(count($approvalGroups)) ?></div>
</form>
<form method="post" action="/pricing/public/prices/approvals/decision">
    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-success" type="submit" name="decision" value="approve" onclick="return confirm('ردیف‌های انتخاب‌شده تأیید شوند؟')">تأیید موارد انتخاب‌شده</button>
        <button class="btn btn-danger" type="submit" name="decision" value="reject" onclick="return confirm('ردیف‌های انتخاب‌شده رد شوند؟')">رد موارد انتخاب‌شده</button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead><tr><th><input type="checkbox" id="approval-select-all" aria-label="انتخاب همه"></th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>توافق پرداخت</th><th>قیمت ناخالص</th><th>مبلغ نهایی</th><th>کاربر ثبت‌کننده</th></tr></thead>
            <tbody>
            <?php if (!$approvalGroups): ?><tr><td colspan="10" class="text-center">موردی در انتظار تأیید نیست.</td></tr>
            <?php else: foreach ($approvalGroups as $group): $r = $group['row']; ?>
                <tr class="<?= $group['warning'] ? 'table-danger' : '' ?>">
                    <td><?php foreach ($group['ids'] as $id): ?><input class="approval-select" type="checkbox" name="ids[]" value="<?= (int)$id ?>" aria-label="انتخاب رکورد <?= (int)$id ?>"><?php endforeach; ?></td>
                    <td><?= Security::e((string)$r['ProductCode']) ?></td>
                    <td><?= Security::e((string)($r['InquiryDateJalali'] ?? '')) ?></td>
                    <td><?= Security::e((string)$r['Source']) ?><?= count($group['ids']) > 1 ? ' ('.count($group['ids']).' قیمت)' : '' ?></td>
                    <td>
                        <?php if ($group['warning']): ?>
                            <span class="text-danger fw-bold">اختلاف فاحش: <?= Security::e(implode('، ', array_map(static fn($p) => number_format((float)$p, 2), $group['prices']))) ?></span>
                        <?php else: ?>
                            <?= number_format((float)($group['average_price'] ?? $r['Price']), 2) ?>
                            <?php if (count($group['ids']) > 1): ?><small class="text-muted d-block">میانگین <?= count($group['ids']) ?> قیمت</small><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= Security::e((string)($r['UnitName'] ?? '')) ?></td>
                    <td><?= Security::e((string)($r['PaymentAgreementTitle'] ?? '')) ?></td>
                    <td><?= number_format((float)($r['grossprice'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($r['netamount'] ?? 0), 2) ?></td>
                    <td><?= Security::e((string)($r['CreatorUsername'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const master = document.getElementById('approval-select-all');
    if (!master) return;
    const updateMaster = function () {
        const items = Array.from(document.querySelectorAll('.approval-select'));
        master.checked = items.length > 0 && items.every(function (item) { return item.checked; });
        master.indeterminate = items.some(function (item) { return item.checked; }) && !master.checked;
    };
    master.addEventListener('change', function () {
        document.querySelectorAll('.approval-select').forEach(function (item) {
            item.checked = master.checked;
        });
        master.indeterminate = false;
    });
    document.querySelectorAll('.approval-select').forEach(function (item) {
        item.addEventListener('change', updateMaster);
    });
    updateMaster();
});
</script>
