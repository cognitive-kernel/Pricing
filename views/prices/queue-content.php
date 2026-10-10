<?php use App\Core\Security; ?>
<div class="mb-3"><h4 class="mb-1">لیستی</h4></div>
<div class="card card-body">
    <label for="list-selection" class="form-label">انتخاب لیست</label>
    <form method="get" action="/pricing/public/prices/queue">
        <select id="list-selection" name="id" class="form-select" onchange="this.form.submit()">
            <option value="0">یک لیست انتخاب کنید</option>
            <?php foreach ($lists as $list): ?>
                <option value="<?= (int) $list['id'] ?>" <?= $selectedId === (int) $list['id'] ? 'selected' : '' ?>><?= Security::e($list['listname']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>