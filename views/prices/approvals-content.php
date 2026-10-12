<?php use App\Core\Security; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">تأیید رصد قیمت‌ها</h4>
    <button type="button" id="approval-export-btn" class="btn btn-success">خروجی اکسل قیمت‌های تأییدشده</button>
</div>
<form method="get" action="/pricing/public/prices/approvals" class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= Security::e($q) ?>" placeholder="جستجو بر اساس کد کالا یا منبع"></div>
    <div class="col-auto"><button class="btn btn-secondary" type="submit">جستجو</button></div>
    <div class="col-auto d-flex align-items-center text-muted">تعداد در انتظار تأیید: <?= number_format(count($approvalGroups)) ?></div>
</form>
<div id="approval-message" class="alert d-none mb-3" role="alert" aria-live="polite"></div>
<form id="approval-decision-form" method="post" action="/pricing/public/prices/approvals/decision">
    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrf()) ?>">
    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-success" type="submit" name="decision" value="approve">تأیید موارد انتخاب‌شده</button>
        <button class="btn btn-danger" type="submit" name="decision" value="reject">رد موارد انتخاب‌شده</button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead><tr><th><input type="checkbox" id="approval-select-all" aria-label="انتخاب همه" onchange="document.querySelectorAll('.approval-select').forEach(function(box){box.checked=this.checked;}, this)"></th><th>کد کالا</th><th>تاریخ رصد</th><th>منبع</th><th>قیمت</th><th>واحد</th><th>توافق پرداخت</th><th>قیمت ناخالص</th><th>مبلغ نهایی</th><th>کاربر ثبت‌کننده</th></tr></thead>
            <tbody>
            <?php if (!$approvalGroups): ?><tr><td colspan="10" class="text-center">موردی در انتظار تأیید نیست.</td></tr>
            <?php else: foreach ($approvalGroups as $group): $r = $group['row']; ?>
                <tr class="<?= $group['warning'] ? 'table-danger' : '' ?>">
                    <td><?php foreach ($group['ids'] as $id): ?><input class="approval-select" type="checkbox" name="ids[]" value="<?= (int)$id ?>" aria-label="انتخاب رکورد <?= (int)$id ?>"><?php endforeach; ?></td>
                    <td><?= Security::e((string)$r['ProductCode']) ?></td>
                    <td><?= Security::e((string)($r['InquiryDateJalali'] ?? '')) ?></td>
                    <td><?= Security::e((string)$r['Source']) ?><?= count($group['ids']) > 1 ? ' ('.count($group['ids']).' قیمت)' : '' ?></td>
                    <td>
                        <?php if ($group['warning']): ?>
                            <span class="text-danger fw-bold">اختلاف فاحش: <?= Security::e(implode('، ', array_map(static fn($p) => number_format((float)$p, 2), $group['prices']))) ?></span>
                        <?php else: ?>
                            <?= number_format((float)($group['average_price'] ?? $r['Price']), 2) ?>
                            <?php if (count($group['ids']) > 1): ?><small class="text-muted d-block">میانگین <?= count($group['ids']) ?> قیمت</small><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= Security::e((string)($r['UnitName'] ?? '')) ?></td>
                    <td><?= Security::e((string)($r['PaymentAgreementTitle'] ?? '')) ?></td>
                    <td><?= number_format((float)($r['grossprice'] ?? 0), 2) ?></td>
                    <td><?= number_format((float)($r['netamount'] ?? 0), 2) ?></td>
                    <td><?= Security::e((string)($r['CreatorUsername'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>



<script>
(function () {
    const messageBox = document.getElementById('approval-message');
    function showMessage(message, success) {
        if (!messageBox) return;
        messageBox.textContent = message || (success ? 'عملیات انجام شد.' : 'خطایی رخ داد.');
        messageBox.className = 'alert mb-3 ' + (success ? 'alert-success' : 'alert-danger');
        messageBox.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
    async function parseResponse(response) {
        const type = (response.headers.get('content-type') || '').toLowerCase();
        if (type.includes('application/json')) {
            const data = await response.json();
            if (!response.ok || data.ok === false) throw new Error(data.message || 'عملیات انجام نشد.');
            return data;
        }
        const text = await response.text();
        if (!response.ok) {
            const cleaned = text.replace(/<[^>]*>/g, ' ').replace(/\\s+/g, ' ').trim();
            throw new Error(cleaned || 'خطایی در سرور رخ داد.');
        }
        return {text};
    }

    const form = document.getElementById('approval-decision-form');
    if (form) form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = event.submitter;
        if (!button) return;
        if (!form.querySelector('.approval-select:checked')) {
            showMessage('ابتدا حداقل یک ردیف را انتخاب کنید.', false);
            return;
        }
        if (!window.confirm(button.value === 'approve' ? 'ردیف‌های انتخاب‌شده تأیید شوند؟' : 'ردیف‌های انتخاب‌شده رد شوند؟')) return;
        const data = new FormData(form);
        data.set('decision', button.value);
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            const result = await parseResponse(response);
            showMessage(result.message || 'تصمیم ثبت شد.', true);
            window.setTimeout(function () { window.location.reload(); }, 600);
        } catch (error) {
            showMessage(error.message, false);
        } finally {
            button.disabled = false;
        }
    });

    const exportButton = document.getElementById('approval-export-btn');
    if (exportButton) exportButton.addEventListener('click', async function () {
        exportButton.disabled = true;
        try {
            const response = await fetch('/pricing/public/prices/approvals/export', {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/vnd.ms-excel, application/json'}
            });
            const type = (response.headers.get('content-type') || '').toLowerCase();
            if (!response.ok || type.includes('application/json') || type.includes('text/html')) {
                await parseResponse(response);
                throw new Error('فایل اکسل از سرور دریافت نشد.');
            }
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'approved-market-prices.xls';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            showMessage('خروجی اکسل آماده شد.', true);
        } catch (error) {
            showMessage(error.message, false);
        } finally {
            exportButton.disabled = false;
        }
    });
})();
</script>
