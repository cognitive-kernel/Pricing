<?php use App\Core\Security; use App\Support\Jalali; $todayJalali = Jalali::fromGregorian(date('Y-m-d')); ?>
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

<?php if (isset($_GET['saved']) && (int) $_GET['saved'] > 0): ?><div class="alert alert-success mt-3"><?= (int) $_GET['saved'] ?> کالا ثبت شد.</div><?php endif; ?>
<?php if (!empty($loadError)): ?>
    <div class="alert alert-danger mt-3"><?= Security::e($loadError) ?></div>
<?php endif; ?>

<?php if ($selectedCode !== ''): ?>
    <form method="post" action="/pricing/public/prices/queue-save" id="queue-price-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
        <input type="hidden" name="list_code" value="<?= Security::e($selectedCode) ?>">
        <input type="hidden" name="list_name" value="<?= Security::e($selectedListName ?? '') ?>">
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
                <div class="col-md-3">
                    <label for="queue-payment-agreement" class="form-label">توافق پرداخت</label>
                    <select id="queue-payment-agreement" name="payment_agreement_id" class="form-select" required>
                        <option value="">انتخاب توافق پرداخت</option>
                        <?php foreach (($paymentAgreements ?? []) as $agreement): ?>
                            <option value="<?= (int) $agreement['PaymentAgreementID'] ?>" <?= (int) $selectedPaymentAgreementId === (int) $agreement['PaymentAgreementID'] ? 'selected' : '' ?>>
                                <?= Security::e((string) $agreement['Title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="queue-master-inquiry-date" class="form-label">تاریخ رصد همه کالاها</label>
                    <input id="queue-master-inquiry-date" type="text" class="form-control form-control-sm jalali-date" style="max-width: 180px;" data-jalali-picker="true" autocomplete="off" inputmode="numeric" value="<?= Security::e($todayJalali) ?>" aria-describedby="queue-master-date-help">
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">ثبت فقط برای کالاهای انتخاب‌شده انجام می‌شود.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">اقلام لیست انتخاب‌شده</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" id="queue-items-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="queue-select-all" aria-label="انتخاب همه کالاها" title="انتخاب همه کالاها"></th>
                        <th>کد کالا</th><th>نام کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>منبع قیمت</th><th>درصد تخفیف</th><th>قیمت ناخالص</th><th>عملیات</th><th>مبلغ نهایی</th><th>واحد</th><th>توافق پرداخت</th><th>تاریخ ایجاد</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$members): ?>
                    <tr><td colspan="13" class="text-center">موردی برای نمایش وجود ندارد.</td></tr>
                <?php else: foreach ($members as $member): ?>
                    <tr class="queue-price-row" data-base-price="<?= ($member['latest_price'] ?? null) !== null ? Security::e((string) $member['latest_price']) : '' ?>" data-product-code="<?= Security::e((string) ($member['code'] ?? '')) ?>">
                        <td><input type="checkbox" class="queue-item-select" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][selected]" value="1" aria-label="انتخاب کالا <?= Security::e((string) ($member['code'] ?? '')) ?>"></td>
                        <input type="hidden" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][has_base_price]" value="<?= ($member['latest_price'] ?? null) !== null && (float) $member['latest_price'] > 0 ? '1' : '0' ?>">
                        <td><?= Security::e((string) ($member['code'] ?? '')) ?></td>
                        <td><?= Security::e((string) ($member['name'] ?? '')) ?></td>
                        <td><input class="form-control jalali-date" data-jalali-picker="true" autocomplete="off" inputmode="numeric" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][inquiry_date]" value="<?= Security::e($todayJalali) ?>" required></td>
                        <td><?= Security::e((string) ($selectedListName ?? '')) ?></td>
                        <td><input class="form-control queue-saved-price" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][price]" value="<?= ($member['latest_price'] ?? null) !== null ? Security::e((string) $member['latest_price']) : '' ?>" inputmode="decimal" required></td>
                        <td><?= Security::e((string) ($member['latest_price_source'] ?? '')) ?: '—' ?></td>
                        <td><input class="form-control queue-discount" type="number" min="0" max="100" step="0.01" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][discount_percent]" value="0"></td>
                        <td class="queue-gross-price">—</td>
                        <td><select class="form-select queue-operation" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][operation]"><option value="1">جمع</option><option value="0">منها</option></select></td>
                        <td class="queue-final-price fw-bold">—<input type="hidden" class="queue-agreement-percent" name="items[<?= Security::e((string) ($member['code'] ?? '')) ?>][agreement_percent]" value="0"></td>
                        <td><?= Security::e((string) ($member['unit_name'] ?? '')) ?: (int) ($member['unit_id'] ?? 0) ?></td>
                        <td class="queue-agreement-title">—</td>
                        <td><?= Security::e($todayJalali) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
        <div class="d-flex gap-2 p-3"><button type="submit" class="btn btn-primary">ثبت</button><a href="/pricing/public/prices" class="btn btn-secondary">انصراف</a></div>
    </form>
<?php endif; ?>