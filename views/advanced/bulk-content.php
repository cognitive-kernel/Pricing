<?php use App\Core\Security; use App\Support\Jalali; $todayJalali = Jalali::fromGregorian(date('Y-m-d')); ?>
<h4 class="mb-3">شرکتی</h4>
<div class="card card-body">
    <label for="company-list" class="form-label">انتخاب شرکت</label>
    <form method="get" action="/pricing/public/products/bulk" data-auto-submit-on-change>
        <select id="company-list" name="code" class="form-select">
            <option value="">یک شرکت انتخاب کنید</option>
            <?php foreach ($companies as $company): ?>
                <option value="<?= Security::e((string) $company['listcode']) ?>" <?= $selectedCode === trim((string) $company['listcode']) ? 'selected' : '' ?>><?= Security::e($company['listname']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-primary mt-2">نمایش اقلام</button></noscript>
    </form>
</div>
<?php if (!empty($loadError)): ?><div class="alert alert-danger mt-3"><?= Security::e($loadError) ?></div><?php endif; ?>
<?php if ($selectedCode !== ''): ?>
    <div class="card mt-3">
        <div class="card-header">اقلام شرکت انتخاب‌شده</div>
        <div class="p-3 pb-0">
            <label for="company-items-search" class="form-label">جستجو در اقلام بارگذاری‌شده</label>
            <input type="search" id="company-items-search" class="form-control" placeholder="جستجو بر اساس کد، نام یا واحد..." autocomplete="off">
            <div class="form-text" id="company-items-search-count" aria-live="polite"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" id="company-items-table">
                <thead><tr><th>کد</th><th>نام</th><th>علامت کالا</th><th>کارشناس</th><th>تاریخ رصد</th><th>منبع</th><th>توافق پرداخت</th><th>واحد</th><th>مبلغ نهایی</th><th>ثبت</th></tr></thead>
                <tbody>
                <?php if (!$members): ?>
                    <tr><td colspan="10" class="text-center">موردی برای نمایش وجود ندارد.</td></tr>
                <?php else: foreach ($members as $member): ?>
                    <tr class="company-item-row" data-product-code="<?= Security::e((string) ($member['code'] ?? '')) ?>">
                        <td><?= Security::e((string) ($member['code'] ?? '')) ?></td>
                        <td><?= Security::e((string) ($member['name'] ?? '')) ?></td>
                        <td><?= Security::e((string) ($member['secondcode'] ?? '')) ?></td>
                        <td><?= Security::e((string) ($member['expert'] ?? '')) ?></td>
                        <td><input class="form-control form-control-sm" value="<?= Security::e($todayJalali) ?>" readonly aria-label="تاریخ رصد"></td>
                        <td class="company-source"><?= Security::e($selectedCompanyName ?? '') ?></td>
                        <td>
                            <div class="company-agreement-list" role="group" aria-label="توافق‌های پرداخت">
                                <?php foreach (($paymentAgreements ?? []) as $agreement): ?>
                                    <?php $agreementId = (int) $agreement['PaymentAgreementID']; ?>
                                    <div class="form-check">
                                        <input class="form-check-input company-agreement" type="checkbox"
                                               id="company-agreement-<?= Security::e((string) ($member['code'] ?? '')) ?>-<?= $agreementId ?>"
                                               value="<?= $agreementId ?>">
                                        <label class="form-check-label small" for="company-agreement-<?= Security::e((string) ($member['code'] ?? '')) ?>-<?= $agreementId ?>">
                                            <?= Security::e((string) $agreement['Title']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($paymentAgreements)): ?>
                                    <span class="text-muted small">توافقی موجود نیست.</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= Security::e((string) ($member['unit_name'] ?? '')) ?: '—' ?></td>
                        <td><input type="number" min="0.01" step="any" inputmode="decimal" class="form-control form-control-sm company-final-price" placeholder="مبلغ نهایی" required aria-label="مبلغ نهایی"></td>
                        <td><button type="button" class="btn btn-sm btn-primary company-row-save">ثبت</button> <span class="small company-row-message" role="status"></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <input type="hidden" id="company-row-csrf" value="<?= Security::e(Security::csrf()) ?>">
        <input type="hidden" id="company-row-code" value="<?= Security::e($selectedCode) ?>">
    </div>
<?php endif; ?>
