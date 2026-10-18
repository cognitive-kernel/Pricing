<?php use App\Core\Security; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">تأیید رصد قیمت‌ها</h4>
    <button type="button" id="approval-export-btn" class="btn btn-success">خروجی اکسل قیمت‌های تأییدشده</button>
</div>
<form method="get" action="/pricing/public/prices/approvals" class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= Security::e($q) ?>" placeholder="جستجو بر اساس کد کالا یا منبع"></div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit">جستجو</button></div>
    <div class="col-auto d-flex align-items-center text-muted">تعداد در انتظار تأیید: <?= number_format(count($approvalGroups)) ?></div>
</form>
<div id="approval-message" class="alert d-none mb-3" role="alert" aria-live="polite"></div>
<form id="approval-decision-form" method="post" action="/pricing/public/prices/approvals/decision">
    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-success" type="submit" name="decision" value="approve">تأیید موارد انتخاب‌شده</button>
        <button class="btn btn-danger" type="submit" name="decision" value="reject">رد موارد انتخاب‌شده</button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead><tr><th><input type="checkbox" id="approval-select-all" aria-label="انتخاب همه"></th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>توافق پرداخت</th><th>قیمت ناخالص</th><th>مبلغ نهایی</th><th>کاربر ثبت‌کننده</th></tr></thead>
            <tbody>
            <?php if (!$approvalGroups): ?><tr><td colspan="10" class="text-center">موردی در انتظار تأیید نیست.</td></tr>
            <?php else: foreach ($approvalGroups as $group): $r = $group['row'];
                $grossPrice = (float)($r['grossprice'] ?? 0);
                $netAmount = (float)($r['netamount'] ?? 0);
                $discountPercent = (float)($r['discountperc'] ?? 0);
                $agreementPercent = (float)($r['payagrementperc'] ?? 0);
                // Some legacy rows only contain Price; calculated columns are blank or zero.
                // In that case use the same group average shown in the Price column.
                if ($netAmount <= 0 && $grossPrice <= 0 && $discountPercent <= 0 && $agreementPercent <= 0) {
                    $netAmount = (float)($group['average_price'] ?? $r['Price'] ?? 0);
                } elseif ($netAmount <= 0 && $grossPrice > 0) {
                    $netAmount = $grossPrice;
                }
            ?>
                <tr class="<?= $group['warning'] ? 'table-danger' : '' ?>">
                    <td>
                        <input class="approval-select-group" type="checkbox" aria-label="انتخاب گروه قیمت‌های کالا <?= Security::e((string)$r['ProductCode']) ?>" <?= $group['warning'] ? 'disabled title="به دلیل اختلاف فاحش قیمت‌ها قابل تأیید نیست"' : '' ?>>
                        <?php foreach ($group['ids'] as $id): ?><input class="approval-record-id" type="hidden" name="ids[]" value="<?= (int)$id ?>" disabled><?php endforeach; ?>
                        <?php if ($group['warning']): ?><small class="text-danger d-block">ابتدا اختلاف قیمت‌ها را بررسی کنید.</small><?php endif; ?>
                    </td>
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
                    <td><?= number_format($netAmount, 2) ?></td>
                    <td><?= Security::e((string)($r['CreatorUsername'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>
