<?php use App\Core\Security; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4>تاریخچه رصد قیمت</h4>
        <div><?=Security::e($product['product_code'])?> - <?=Security::e($product['product_name'])?></div>
    </div>
    <a class="btn btn-outline-secondary" href="/pricing/public/prices/<?= (int)$product['id']?>/edit">رصد بازار</a>
</div>
<div class="table-responsive">
<table class="table table-bordered align-middle text-center">
<thead><tr><th>شناسه</th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>تاریخ ایجاد</th></tr></thead>
<tbody>
<?php foreach($history as $r):?>
<tr>
<td><?= (int)$r['PriceMonitorID']?></td>
<td><?=Security::e($r['ProductCode'])?></td>
<td><?=Security::e(\App\Support\Jalali::fromGregorian($r['InquiryDate']))?></td>
<td><?=Security::e($r['Source'])?></td>
<td><?=number_format((float)$r['Price'],2)?></td>
<td><?=Security::e((string)($r['unit_name'] ?? $r['UnitID']))?></td>
<td><?=Security::e(\App\Support\Jalali::fromGregorian($r['CreatedDate']))?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>