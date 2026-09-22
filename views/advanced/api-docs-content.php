<?php use App\Core\Security; ?>
<h4>مستندات API</h4>
<div class="alert alert-secondary">تمام Endpointها با Bearer Token احراز هویت می‌شوند و بر اساس Permission مربوط به Token محدود هستند. سقف درخواست ۶۰ درخواست در دقیقه برای هر Token است.</div>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Method</th><th>Endpoint</th><th>Permission</th><th>خروجی</th></tr></thead><tbody>
<tr><td>GET</td><td><code>/pricing/public/api/products</code></td><td><code>api.products.view</code></td><td>محصولات فعال</td></tr>
<tr><td>GET</td><td><code>/pricing/public/api/products/{id}/prices</code></td><td><code>api.prices.view</code></td><td>تاریخچه قیمت محصول</td></tr>
<tr><td>GET</td><td><code>/pricing/public/api/queue</code></td><td><code>api.queue.view</code></td><td>صف فعال</td></tr>
</tbody></table></div>
<p class="text-muted mb-0">هدر نمونه: <code>Authorization: Bearer YOUR_TOKEN</code></p>