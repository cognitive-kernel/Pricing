<?php use App\Core\Security;?>
<div class="card mb-3">
  <div class="card-body">
    <h5>ثبت کاربر جدید</h5>
    <form method="post" action="/pricing/public/admin/users" class="row g-2">
      <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
      <div class="col-md-3"><input name="username" class="form-control" placeholder="نام کاربری" required></div>
      <div class="col-md-3"><input name="name" class="form-control" placeholder="نام" required></div>
      <div class="col-md-3"><input name="password" type="password" minlength="8" class="form-control" placeholder="رمز اولیه" required></div><div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="ایمیل"></div>
      <div class="col-md-3">
        <select name="groups[]" multiple class="form-select" size="3">
          <?php foreach($groups as $g):?>
            <option value="<?=(int)$g['id']?>"><?=Security::e($g['name'])?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="col-12"><button class="btn btn-primary">ثبت کاربر</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">کاربران ثبت‌شده</div>
  <div class="card-body">
    <?php foreach($users as $u):?>
      <?php $active=(bool)$u['is_active']; ?>
      <details class="border rounded p-3 mb-2">
        <summary class="fw-bold">
          <?=Security::e($u['name'])?> — <?=Security::e($u['username'])?>
          <span class="badge <?=$active?'bg-success':'bg-secondary'?> ms-2"><?=$active?'فعال':'غیرفعال'?></span>
        </summary>

        <div class="mt-3">
          <form method="post" action="/pricing/public/admin/users/<?=(int)$u['id']?>/status" class="mb-3">
            <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
            <button class="btn btn-sm <?=$active?'btn-outline-danger':'btn-outline-success'?>">
              <?=$active?'غیرفعال کردن کاربر':'فعال کردن کاربر'?>
            </button>
          </form>

          <form method="post" action="/pricing/public/admin/users/<?=(int)$u['id']?>/groups" class="mb-3">
            <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
            <label class="form-label">گروه‌های کاربر</label>
            <div class="row">
              <?php foreach($groups as $g):?>
                <div class="col-md-3 mb-2">
                  <label>
                    <input type="checkbox" name="groups[]" value="<?=(int)$g['id']?>" <?=in_array((int)$g['id'],$u['groups'],true)?'checked':''?>>
                    <?=Security::e($g['name'])?>
                  </label>
                </div>
              <?php endforeach;?>
            </div>
            <button class="btn btn-sm btn-outline-primary">ذخیره گروه‌ها</button>
          </form>

          <div class="alert alert-light border">
            <strong>دسترسی نهایی</strong>
            <div class="small text-muted">مجموع دسترسی گروه‌ها با Override مستقیم کاربر؛ Override روی دسترسی گروه اولویت دارد.</div>
          </div>

          <form method="post" action="/pricing/public/admin/users/<?=(int)$u['id']?>/permissions">
            <input type="hidden" name="_csrf" value="<?=Security::e(Security::csrf())?>">
            <div class="row">
              <?php foreach($permissions as $p):
                $has=array_key_exists($p['code'],$u['overrides']);
                $effective=in_array($p['code'],$u['effective'],true);
              ?>
                <div class="col-md-6 col-lg-4 mb-3">
                  <label class="small fw-bold"><?=Security::e($p['name'])?></label>
                  <select name="permissions[<?=Security::e($p['code'])?>]" class="form-select form-select-sm">
                    <option value="" <?=$has?'':'selected'?>>بدون Override — نهایی: <?=$effective?'مجاز':'غیرمجاز'?></option>
                    <option value="1" <?=$has&&$u['overrides'][$p['code']]===1?'selected':''?>>Override: اجازه</option>
                    <option value="0" <?=$has&&$u['overrides'][$p['code']]===0?'selected':''?>>Override: لغو</option>
                  </select>
                  <div class="small mt-1">نهایی: <strong><?=$effective?'مجاز':'غیرمجاز'?></strong></div>
                </div>
              <?php endforeach;?>
            </div>
            <button class="btn btn-sm btn-warning">ذخیره دسترسی نهایی</button>
          </form>
        </div>
      </details>
    <?php endforeach;?>
  </div>
</div>
