<section class="container py-3">
    <div class="mb-4">
        <h1 class="h3 mb-2">صفحه اصلی</h1>
        <p class="text-muted mb-0">برای ورود به هر بخش، کاشی مربوط را انتخاب کنید.</p>
    </div>

    <?php if (!$tiles): ?>
        <div class="alert alert-warning mb-0">برای نمایش بخش‌ها، دسترسی لازم برای حساب شما تعریف نشده است.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($tiles as $tile): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <a class="card h-100 text-decoration-none shadow-sm border-0" href="<?= \App\Core\Security::e($tile['url']) ?>">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <span class="badge text-bg-<?= \App\Core\Security::e($tile['tone']) ?> px-3 py-2">
                                    <?= \App\Core\Security::e($tile['symbol']) ?>
                                </span>
                                <span class="text-muted" aria-hidden="true">←</span>
                            </div>
                            <h2 class="h5 text-dark mb-2"><?= \App\Core\Security::e($tile['title']) ?></h2>
                            <p class="text-muted mb-0"><?= \App\Core\Security::e($tile['description']) ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
