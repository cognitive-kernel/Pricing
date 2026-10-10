<?php use App\Core\Security; ?>
<h4 class="mb-3">شرکتی</h4>
<div class="card card-body">
    <label for="company-list" class="form-label">انتخاب شرکت</label>
    <form method="get" action="/pricing/public/products/bulk">
        <select id="company-list" name="code" class="form-select" onchange="this.form.submit()">
            <option value="">یک شرکت انتخاب کنید</option>
            <?php foreach ($companies as $company): ?>
                <option value="<?= Security::e((string) $company['listcode']) ?>" <?= $selectedCode === trim((string) $company['listcode']) ? 'selected' : '' ?>><?= Security::e($company['listname']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php if (!empty($loadError)): ?><div class="alert alert-danger mt-3"><?= Security::e($loadError) ?></div><?php endif; ?>
<?php if ($selectedCode !== ''): ?>
    <div class="card mt-3">
        <div class="card-header">اقلام شرکت انتخاب‌شده</div>
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