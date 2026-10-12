<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\PermissionService;

final class HomeController
{
    public function index(): void
    {
        Auth::requireLogin();
        $userId = (int) Auth::userId();

        $allTiles = [
            [
                'permission' => 'prices.view',
                'title' => 'لیست قیمت‌ها',
                'description' => 'مشاهده قیمت‌های ثبت‌شده و وضعیت رصد کالاها',
                'url' => '/pricing/public/prices/list',
                'symbol' => 'قیمت',
                'tone' => 'primary',
            ],
            [
                'permission' => 'prices.queue.view',
                'title' => 'فرم لیستی',
                'description' => 'ثبت قیمت اقلام از طریق فهرست لیستی',
                'url' => '/pricing/public/prices/queue',
                'symbol' => 'لیستی',
                'tone' => 'success',
            ],
            [
                'permission' => 'prices.view',
                'title' => 'تأیید قیمت‌ها',
                'description' => 'بررسی و تأیید یا رد آخرین رصد ثبت‌شده برای هر کالا',
                'url' => '/pricing/public/prices/approvals',
                'symbol' => 'تأیید',
                'tone' => 'warning',
            ],
            [
                'permission' => 'products.bulk',
                'title' => 'فرم شرکتی',
                'description' => 'ثبت قیمت کالاها بر اساس شرکت انتخاب‌شده',
                'url' => '/pricing/public/products/bulk',
                'symbol' => 'شرکتی',
                'tone' => 'info',
            ],
        ];

        $tiles = array_values(array_filter(
            $allTiles,
            static fn(array $tile): bool => PermissionService::allows($userId, $tile['permission']) && ($tile['permission'] !== 'prices.queue.view' || PermissionService::allows($userId, 'prices.view'))
        ));

        View::render('home/index', compact('tiles'));
    }
}
