<?php use App\Core\Security; ?>
<div class="mb-3"><h4 class="mb-1">لیستی</h4></div>
<div class="card card-body">
    <label for="list-selection" class="form-label">انتخاب لیست</label>
    <form method="get" action="/pricing/public/prices/queue">
        <select id="list-selection" name="code" class="form-select" onchange="this.form.submit()">
            <option value="">یک لیست انتخاب کنید</option>
            <?php foreach ($lists as $list): ?>
                <option value="<?= Security::e((string) $list['listcode']) ?>" <?= $selectedCode === trim((string) $list['listcode']) ? 'selected' : '' ?>><?= Security::e($list['listname']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php if (!empty($loadError)): ?><div class="alert alert-danger mt-3"><?= Security::e($loadError) ?></div><?php endif; ?>
<?php if ($selectedCode !== ''): ?>
    <div class="card mt-3">
        <div class="card-header">اقلام لیست انتخاب‌شده</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead><tr><th>کد</th><th>نام</th></tr></thead>
                <tbody>
                <?php if (!$members): ?>
                    <tr><td colspan="2" class="text-center">موردی برای نمایش وجود ندارد.</td></tr>
                <?php else: foreach ($members as $member): ?>
                    <tr><td><?= Security::e((string) ($member['code'] ?? '')) ?></td><td><?= Security::e((string) ($member['name'] ?? '')) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>