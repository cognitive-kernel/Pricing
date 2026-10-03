<?php use App\Core\Security; use App\Support\Jalali; ?>

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

                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th>ردیف</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><input name="prices[<?= $i ?>][inquiry_date]" class="form-control jalali-date" data-jalali-picker="true" autocomplete="off" inputmode="numeric" placeholder="1405/01/01"></td>
                                <td><input name="prices[<?= $i ?>][source]" class="form-control" maxlength="255" placeholder="نام منبع"></td>
                                <td><input name="prices[<?= $i ?>][price]" type="text" inputmode="decimal" class="form-control price-input" autocomplete="off" placeholder="قیمت"></td>
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
                    <button class="btn btn-primary" type="submit">ثبت رصدهای جدید</button>
                    <a href="/pricing/public/prices" class="btn btn-secondary">انصراف</a>
                </div>
            </form>

            <hr class="my-4">
            <h5 class="mb-3">سوابق رصد قیمت</h5>

            <?php if ($prices): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th>شناسه</th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>تاریخ ایجاد</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prices as $price): ?>
                            <tr>
                                <td><?= (int) $price['PriceMonitorID'] ?></td>
                                <td><?=Security::e((string) $price['ProductCode'])?></td>
                                <td><?=Security::e(Jalali::fromGregorian($price['InquiryDate']))?></td>
                                <td><?=Security::e((string) ($price['Source'] ?? ''))?></td>
                                <td><?=Security::e(number_format((float) $price['Price'], 2))?></td>
                                <td><?=Security::e((string) ($price['UnitID'] ?? ''))?></td>
                                <td><?=Security::e(Jalali::fromGregorian($price['CreatedDate']))?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary mb-0">برای این کالا هنوز رکوردی در ProductPriceMonitor ثبت نشده است.</div>
            <?php endif; ?>
    </div>
</div>


<link rel="stylesheet" href="/pricing/public/assets/price-input.css">
<script src="/pricing/public/assets/price-input.js" defer></script>