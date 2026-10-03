<?php use App\Core\Security; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4>تاریخچه رصد قیمت</h4>
        <div><?=Security::e($product['product_code'])?> - <?=Security::e($product['product_name'])?></div>
    </div>
    <a class="btn btn-outline-secondary" href="/pricing/public/prices/0/edit?product_code=<?= rawurlencode((string)$product['product_code']) ?>&product_name=<?= rawurlencode((string)$product['product_name']) ?>&unit_id=<?= (int)$product['unit_id'] ?>&unit_name=<?= rawurlencode((string)$product['unit_name']) ?>">رصد بازار</a>
</div>

<div class="table-responsive">
<table class="table table-bordered align-middle text-center">
<thead><tr><th>شناسه</th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>تاریخ ایجاد</th></tr></thead>
<tbody>
<?php if (!$history): ?>
<tr><td colspan="7" class="text-center">سابقه‌ای برای این کالا ثبت نشده است.</td></tr>
<?php endif; ?>
<?php foreach($history as $r):?>
<tr>
<td><?= (int)$r['PriceMonitorID']?></td>
<td><?=Security::e($r['ProductCode'])?></td>
<td><?=Security::e(\App\Support\Jalali::fromGregorian($r['InquiryDate']))?></td>
<td><?=Security::e($r['Source'])?></td>
<td><?=number_format((float)$r['Price'],2)?></td>
<td><?=Security::e((string)($r['UnitID'] ?? ''))?></td>
<td><?=Security::e(\App\Support\Jalali::fromGregorian($r['CreatedDate']))?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<nav aria-label="صفحه‌بندی تاریخچه" class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="/pricing/public/prices/0/history?product_code=<?= rawurlencode((string)$product['product_code']) ?>&product_name=<?= rawurlencode((string)$product['product_name']) ?>&unit_id=<?= (int)$product['unit_id'] ?>&unit_name=<?= rawurlencode((string)$product['unit_name']) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<div class="mt-4">
    <h5>نمودار سوابق قیمت</h5>
    <div class="history-chart-wrap">
        <canvas id="price-history-chart" aria-label="نمودار سوابق قیمت"></canvas>
        <div id="price-history-chart-empty" class="text-muted text-center py-4 d-none">داده‌ای برای نمایش نمودار وجود ندارد.</div>
    </div>
</div>

<script src="/pricing/public/assets/price-history-chart.js" defer></script>
