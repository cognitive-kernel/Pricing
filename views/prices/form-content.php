<?php use App\Core\Security; use App\Support\Jalali; ?>
<?php $todayJalali = Jalali::fromGregorian(date('Y-m-d')); ?>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">رصد قیمت بازار</h4>
                <div class="text-muted">
                    <?=Security::e((string) ($product['product_name'] ?? ''))?>
                    <span class="mx-2">|</span>
                    کد کالا:
                    <strong><?=Security::e((string) ($product['product_code'] ?? ''))?></strong>
                </div>
            </div>
            <?php if ($editing && (int) $requiredSources > 0): ?>
                <span class="badge text-bg-warning">حداقل <?= (int) $requiredSources ?> رصد جدید لازم است</span>
            <?php endif; ?>
        </div>            <form data-required-sources="<?= (int) $requiredSources ?>" method="post"
                  action="/pricing/public/prices/<?= (int) $product['id'] ?>" id="price-form">
                <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">کد کالا</label>
                        <input class="form-control" value="<?=Security::e((string) $product['product_code'])?>" readonly>
                        <input type="hidden" name="product_code" value="<?=Security::e((string) $product['product_code'])?>">
                <input type="hidden" name="product_name" value="<?=Security::e((string) ($product['product_name'] ?? ''))?>">
                <input type="hidden" name="product_unit_id" value="<?= (int) ($product['unit_id'] ?? 0) ?>">
                <input type="hidden" name="product_unit_name" value="<?=Security::e((string) ($product['unit_name'] ?? ''))?>">
                <input type="hidden" name="required_sources" value="<?= (int) $requiredSources ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">نام کالا</label>
                        <input class="form-control" value="<?=Security::e((string) ($product['product_name'] ?? ''))?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">واحد سنجش</label>
                        <input class="form-control" value="<?=Security::e((string) ($product['unit_name'] ?? ('کد ' . (int) ($product['unit_id'] ?? 0))))?>" readonly>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">توافق پرداخت</label>
                        <select name="payment_agreement_id" class="form-select" required>
                            <option value="">انتخاب توافق پرداخت</option>
                            <?php foreach (($paymentAgreements ?? []) as $agreement): ?>
                                <option value="<?= (int) $agreement['PaymentAgreementID'] ?>" <?= (int) $selectedPaymentAgreementId === (int) $agreement['PaymentAgreementID'] ? 'selected' : '' ?>>
                                    <?= Security::e((string) $agreement['Title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">درصد توافق پرداخت</label>
                        <input type="number" name="payment_agreement_percent" id="payment-agreement-percent"
                               class="form-control" min="0" step="0.01" value="0" inputmode="decimal">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th>ردیف</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>درصد تخفیف</th><th>قیمت ناخالص</th><th>عملیات</th><th>مبلغ نهایی</th><th>واحد</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><input name="prices[<?= $i ?>][inquiry_date]" value="<?= Security::e($todayJalali) ?>" class="form-control jalali-date" data-jalali-picker="true" autocomplete="off" inputmode="numeric" placeholder="1405/01/01" <?= $i < (int) $requiredSources ? 'required' : '' ?>></td>
                                <td><input name="prices[<?= $i ?>][source]" class="form-control" maxlength="255" placeholder="نام منبع" <?= $i < (int) $requiredSources ? 'required' : '' ?>></td>
                                <td><input name="prices[<?= $i ?>][price]" type="text" inputmode="decimal" class="form-control price-input" autocomplete="off" placeholder="قیمت" <?= $i < (int) $requiredSources ? 'required' : '' ?>></td>
                                <td><input name="prices[<?= $i ?>][discount_percent]" type="number" inputmode="decimal" class="form-control discount-percent" min="0" step="0.01" value="0"></td>
                                <td><input type="text" class="form-control gross-price" readonly></td>
                                <td>
                                    <select name="prices[<?= $i ?>][payment_operation]" class="form-select payment-operation">
                                        <option value="1">جمع</option>
                                        <option value="0">منها</option>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control final-price" readonly></td>
                                <td>
                                    <input type="hidden" name="prices[<?= $i ?>][unit_id]" value="<?= (int) ($product['unit_id'] ?? 0) ?>">
                                    <input class="form-control" value="<?=Security::e((string) ($product['unit_name'] ?? ('کد ' . (int) ($product['unit_id'] ?? 0))))?>" readonly>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary" type="submit" name="save_action" value="back">ثبت و بازگشت به صفحه اصلی</button>
                    <button class="btn btn-success" type="submit" name="save_action" value="stay">ثبت و ماندن در صفحه</button>
                    <a href="/pricing/public/prices/list" class="btn btn-secondary">انصراف</a>
                </div>
            </form>

            <hr class="my-4">
            <h5 class="mb-3">سوابق رصد قیمت</h5>

            <?php if ($prices): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th>شناسه</th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>درصد تخفیف</th><th>قیمت ناخالص</th><th>عملیات</th><th>مبلغ نهایی</th><th>واحد</th><th>توافق پرداخت</th><th>تاریخ ایجاد</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prices as $price): ?>
                            <?php $isAddOperation = $price['opration'] === true || $price['opration'] === 1 || $price['opration'] === '1' || strtolower((string) $price['opration']) === 'true'; ?>
                            <tr>
                                <td><?= (int) $price['PriceMonitorID'] ?></td>
                                <td><?=Security::e((string) $price['ProductCode'])?></td>
                                <td><?=Security::e(Jalali::fromGregorian($price['InquiryDate']))?></td>
                                <td><?=Security::e((string) ($price['Source'] ?? ''))?></td>
                                <td><?=Security::e(number_format((float) $price['Price'], 2))?></td>
                                <td><?=Security::e((string) ($price['discountperc'] ?? 0))?>%</td>
                                <td><?=Security::e(number_format((float) ($price['grossprice'] ?? 0), 2))?></td>
                                <td><?=$isAddOperation ? 'جمع' : 'منها'?></td>
                                <td><?=Security::e(number_format((float) ($price['netamount'] ?? 0), 2))?></td>
                                <td><?=Security::e((string) ($product['unit_name'] ?? ''))?></td>
                                <td><?=Security::e((string) ($price['PaymentAgreementTitle'] ?? ''))?></td>
                                <td><?=Security::e(Jalali::fromGregorian($price['CreatedDate']))?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary mb-0">برای این کالا هنوز رکوردی ثبت نشده است.</div>
            <?php endif; ?>
    </div>
</div>


<link rel="stylesheet" href="/pricing/public/assets/price-input.css">
<script src="/pricing/public/assets/price-input.js?v=3" defer></script>