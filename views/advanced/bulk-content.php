<?php use App\Core\Security; ?>
<h4 class="mb-3">شرکتی</h4>
<div class="card card-body">
    <label for="company-list" class="form-label">انتخاب شرکت</label>
    <form method="get" action="/pricing/public/products/bulk">
        <select id="company-list" name="id" class="form-select" onchange="this.form.submit()">
            <option value="0">یک شرکت انتخاب کنید</option>
            <?php foreach ($companies as $company): ?>
                <option value="<?= (int) $company['id'] ?>" <?= $selectedId === (int) $company['id'] ? 'selected' : '' ?>><?= Security::e($company['listname']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>