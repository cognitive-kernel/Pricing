<?php use App\Core\Security; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">تأیید رصد قیمت‌ها</h4>
    <a href="/pricing/public/prices/approvals/export" class="btn btn-success">خروجی اکسل قیمت‌های تأییدشده</a>
</div>
<form method="get" action="/pricing/public/prices/approvals" class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= Security::e($q) ?>" placeholder="جستجو بر اساس کد کالا یا منبع"></div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit">جستجو</button></div>
    <div class="col-auto d-flex align-items-center text-muted">تعداد در انتظار تأیید: <?= number_format(count($records)) ?></div>
</form>
<form method="post" action="/pricing/public/prices/approvals/decision">
    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-success" type="submit" name="decision" value="approve" onclick="return confirm('ردیف‌های انتخاب‌شده تأیید شوند؟')">تأیید موارد انتخاب‌شده</button>
        <button class="btn btn-danger" type="submit" name="decision" value="reject" onclick="return confirm('ردیف‌های انتخاب‌شده رد شوند؟')">رد موارد انتخاب‌شده</button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.approval-select').forEach(e=>e.checked=this.checked)" aria-label="انتخاب همه"></th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>توافق پرداخت</th><th>قیمت ناخالص</th><th>مبلغ نهایی</th><th>کاربر ثبت‌کننده</th></tr></thead>
            <tbody>
            <?php if (!$records): ?><tr><td colspan="10" class="text-center">موردی در انتظار تأیید نیست.</td></tr>
            <?php else: foreach ($records as $r): ?>
                <tr>
                    <td><input class="approval-select" type="checkbox" name="ids[]" value="<?= (int)$r['PriceMonitorID'] ?>" aria-label="انتخاب کالا <?= Security::e((string)$r['ProductCode']) ?>"></td>
                    <td><?= Security::e((string)$r['ProductCode']) ?></td>
                    <td><?= Security::e((string)$r['InquiryDate']) ?></td>
                    <td><?= Security::e((string)$r['Source']) ?></td>
                    <td><?= number_format((float)$r['Price'], 2) ?></td>
                    <td><?= (int)($r['UnitID'] ?? 0) ?></td>
                    <td><?= Security::e((string)($r['PaymentAgreementTitle'] ?? '')) ?></td>
                    <td><?= number_format((float)($r['grossprice'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($r['netamount'] ?? 0), 2) ?></td>
                    <td><?= (int)($r['userid'] ?? 0) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>
