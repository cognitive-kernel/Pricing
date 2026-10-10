<?php use App\\Core\\Security; ?>
<div class="mb-3"><h4 class="mb-1">لیستی</h4></div>
<div class="card card-body mb-3">
    <label for="list-selection" class="form-label">انتخاب لیست</label>
    <form method="get" action="/pricing/public/prices/queue" data-auto-submit-on-change>
        <select id="list-selection" name="code" class="form-select">
            <option value="">یک لیست انتخاب کنید</option>
            <?php foreach ($lists as $list): ?>
                <option value="<?= Security::e((string) $list['listcode']) ?>" <?= $selectedCode === trim((string) $list['listcode']) ? 'selected' : '' ?>><?= Security::e($list['listname']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-primary mt-2">نمایش اقلام</button></noscript>
    </form>
</div>

<?php if (!empty($loadError)): ?>
    <div class="alert alert-danger mt-3"><?= Security::e($loadError) ?></div>
<?php endif; ?>

<?php if ($selectedCode !== ''): ?>
    <div class="card mb-3">
        <div class="card-header">تنظیم قیمت نهایی</div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="first-adjustment" class="form-label">مرحله اول</label>
                    <select id="first-adjustment" class="form-select">
                        <option value="increase" <?= $firstAdjustment === 'increase' ? 'selected' : '' ?>>افزایش</option>
                        <option value="decrease" <?= $firstAdjustment === 'decrease' ? 'selected' : '' ?>>کاهش</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="first-value" class="form-label">درصد افزایش یا کاهش</label>
                    <input id="first-value" type="number" min="0" step="any" inputmode="decimal" class="form-control" value="<?= Security::e($firstValue) ?>">
                </div>
                <div class="col-md-6">
                    <label for="queue-payment-agreement" class="form-label">توافق پرداخت</label>
                    <select id="queue-payment-agreement" class="form-select">
                        <option value="">انتخاب توافق پرداخت</option>
                        <?php foreach (($paymentAgreements ?? []) as $agreement): ?>
                            <option value="<?= (int) $agreement['PaymentAgreementID'] ?>" <?= (int) $selectedPaymentAgreementId === (int) $agreement['PaymentAgreementID'] ? 'selected' : '' ?>>
                                <?= Security::e((string) $agreement['Title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="second-adjustment" class="form-label">مرحله دوم</label>
                    <select id="second-adjustment" class="form-select">
                        <option value="increase" <?= $secondAdjustment === 'increase' ? 'selected' : '' ?>>افزایش</option>
                        <option value="decrease" <?= $secondAdjustment === 'decrease' ? 'selected' : '' ?>>کاهش</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="second-value" class="form-label">درصد افزایش یا کاهش</label>
                    <input id="second-value" type="number" min="0" step="any" inputmode="decimal" class="form-control" value="<?= Security::e($secondValue) ?>">
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">محاسبات فقط برای پیش‌نمایش هستند؛ در این مرحله چیزی در پایگاه داده ثبت نمی‌شود.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">اقلام لیست انتخاب‌شده</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" id="queue-items-table">
                <thead>
                    <tr><th>کد</th><th>نام کالا</th><th>قیمت مبنا</th><th>منبع قیمت</th><th>تاریخ قیمت</th><th>قیمت پس از مرحله اول</th><th>قیمت نهایی</th></tr>
                </thead>
                <tbody>
                <?php if (!$members): ?>
                    <tr><td colspan="7" class="text-center">موردی برای نمایش وجود ندارد.</td></tr>
                <?php else: foreach ($members as $member): ?>
                    <tr class="queue-price-row" data-base-price="<?= $member['latest_price'] !== null ? Security::e((string) $member['latest_price']) : '' ?>">
                        <td><?= Security::e((string) ($member['code'] ?? '')) ?></td>
                        <td><?= Security::e((string) ($member['name'] ?? '')) ?></td>
                        <td class="queue-base-price"><?= $member['latest_price'] !== null ? number_format((float) $member['latest_price'], 2) : '—' ?></td>
                        <td><?= Security::e((string) ($member['latest_price_source'] ?? '')) ?: '—' ?></td>
                        <td><?= Security::e((string) ($member['latest_price_date'] ?? '')) ?: '—' ?></td>
                        <td class="queue-first-price">—</td>
                        <td class="queue-final-price fw-bold">—</td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>