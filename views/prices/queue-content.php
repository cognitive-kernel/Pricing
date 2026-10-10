<?php use App\\Core\\Security; use App\\Support\\Jalali; $todayJalali = Jalali::fromGregorian(date('Y-m-d')); ?>
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
        <!-- Existing queue form fields remain in this form. -->
        <div class="d-flex gap-2 p-3"><button type="submit" class="btn btn-primary">ثبت</button><a href="/pricing/public/prices/list" class="btn btn-secondary">انصراف</a></div>
    </form>
<?php endif; ?>