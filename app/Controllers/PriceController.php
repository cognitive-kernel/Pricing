<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RahkaranPriceService;
use App\Support\Jalali;
use PDO;

final class PriceController
{
    private function latestPricesSql(): string
    {
        return "SELECT * FROM (SELECT pp.*,ROW_NUMBER() OVER(PARTITION BY pp.product_id,pp.source_no ORDER BY pp.price_received_at DESC,pp.id DESC) rn FROM product_prices pp) ranked_prices WHERE rn=1";
    }

    private function rowStatus(?string $marketDate, ?array $rahkaran): array
    {
        if ($marketDate) {
            $days = max(0, (int) ((time() - strtotime($marketDate)) / 86400));
            if ($days < 7) {
                return ['status' => 'green', 'required_sources' => 0, 'market_days' => $days];
            }
        }

        if (!$marketDate && $rahkaran) {
            $days = max(0, (int) ($rahkaran['Announcement_diff'] ?? 999));
            if ($days < 7) {
                return ['status' => 'yellow', 'required_sources' => 1, 'market_days' => null];
            }
            if ($days <= 15) {
                return ['status' => 'orange', 'required_sources' => 2, 'market_days' => null];
            }
            return ['status' => 'red', 'required_sources' => 3, 'market_days' => null];
        }

        return ['status' => 'neutral', 'required_sources' => 0, 'market_days' => null];
    }

    private function currentRequiredSources(array $product): int
    {
        $pdo = Database::connection();
        $latest = $this->latestPricesSql();
        $s = $pdo->prepare("SELECT TOP 1 price_received_at FROM ({$latest}) lp WHERE lp.product_id=:id ORDER BY price_received_at DESC");
        $s->execute(['id' => (int) $product['id']]);
        $marketDate = $s->fetchColumn();

        $rahkaran = RahkaranPriceService::instance()->latestByCodes([(string) $product['product_code']]);
        $row = $this->rowStatus($marketDate ?: null, $rahkaran[(string) $product['product_code']] ?? null);

        return $row['required_sources'];
    }

    public function index(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');

        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'registered_at');
        $dir = ($_GET['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $allowed = [
            'product_code' => 'p.product_code',
            'product_name' => 'p.product_name',
            'unit_name' => 'u.name',
            'registered_at' => 'p.registered_at',
            'latest_price_date' => 'latest_price_date'
        ];
        $order = $allowed[$sort] ?? $allowed['registered_at'];
        $where = " WHERE p.status='active'";
        $params = [];

        if ($q !== '') {
            $where .= ' AND (p.product_code LIKE :q OR p.product_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $pdo = Database::connection();
        $latest = $this->latestPricesSql();

        $s = $pdo->prepare("SELECT COUNT(*) FROM products p INNER JOIN units u ON u.id=p.unit_id{$where}");
        $s->execute($params);
        $total = (int) $s->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.id,p.product_code,p.product_name,p.registered_at,p.importance,p.min_sources,p.max_sources,u.name unit_name,(SELECT MAX(lp.price_received_at) FROM ({$latest}) lp WHERE lp.product_id=p.id) latest_price_date FROM products p INNER JOIN units u ON u.id=p.unit_id{$where} ORDER BY {$order} {$dir} OFFSET :off ROWS FETCH NEXT :take ROWS ONLY";
        $s = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $s->bindValue(':' . $k, $v);
        }
        $s->bindValue(':off', $offset, PDO::PARAM_INT);
        $s->bindValue(':take', $perPage, PDO::PARAM_INT);
        $s->execute();
        $products = $s->fetchAll();

        $ids = array_column($products, 'id');
        $prices = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT * FROM ({$latest}) lp WHERE lp.product_id IN ({$in}) ORDER BY product_id,source_no");
            $s->execute($ids);
            foreach ($s->fetchAll() as $r) {
                $prices[$r['product_id']][] = $r;
            }
        }

        $codes = array_column($products, 'product_code');
        $rahkaran = [];
        if ($codes) {
            try {
                $rahkaran = RahkaranPriceService::instance()->latestByCodes($codes);
            } catch (\Throwable $e) {
                error_log('Rahkaran lookup skipped on prices page: ' . $e->getMessage());
            }
        }

        foreach ($products as &$product) {
            $productPrices = $prices[$product['id']] ?? [];
            $marketDate = null;
            foreach ($productPrices as $price) {
                if (!$marketDate || strtotime($price['price_received_at']) > strtotime($marketDate)) {
                    $marketDate = $price['price_received_at'];
                }
            }
            $product['market_latest_date'] = $marketDate;
            $product['rahkaran'] = $rahkaran[(string) $product['product_code']] ?? null;
            $state = $this->rowStatus($marketDate, $product['rahkaran']);
            $product['row_status'] = $state['status'];
            $product['required_sources'] = $state['required_sources'];
            $product['market_days'] = $state['market_days'];
        }
        unset($product);

        $pages = max(1, (int) ceil($total / $perPage));
        $data = compact('products', 'prices', 'q', 'sort', 'dir', 'page', 'pages', 'perPage', 'total');

        if (isset($_GET['ajax'])) {
            View::render('prices/table', $data);
            return;
        }

        View::render('prices/index', $data);
    }

    public function queue(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');
        $rows = Database::connection()->query("SELECT TOP 500 q.id,q.product_id,q.score,q.priority_level,q.status,q.assigned_to,q.due_at,q.started_at,q.completed_at,q.result_note,q.reason,p.product_code,p.product_name,u.name unit_name,p.importance FROM price_queue q JOIN products p ON p.id=q.product_id JOIN units u ON u.id=p.unit_id WHERE q.status IN('new','in_progress','needs_followup') ORDER BY q.score DESC,q.updated_at ASC,p.product_code ASC")->fetchAll();
        View::render('prices/queue', compact('rows'));
    }

    public function history(int $id): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.history.view');
        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT p.*,u.name unit_name FROM products p JOIN units u ON u.id=p.unit_id WHERE p.id=:id');
        $s->execute(['id' => $id]);
        $product = $s->fetch();
        if (!$product) {
            http_response_code(404);
            exit('رکورد یافت نشد.');
        }
        $s = $pdo->prepare('SELECT * FROM product_prices WHERE product_id=:id ORDER BY price_received_at DESC,id DESC');
        $s->execute(['id' => $id]);
        $history = $s->fetchAll();
        View::render('prices/history', compact('product', 'history'));
    }

    public function form(?int $id = null): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), $id ? 'prices.edit' : 'prices.create');
        $pdo = Database::connection();
        $units = $pdo->query('SELECT id,name FROM units WHERE is_active=1 ORDER BY name')->fetchAll();
        $categories = $pdo->query('SELECT id,name FROM product_categories WHERE is_active=1 ORDER BY name')->fetchAll();
        $product = null;
        $prices = [];
        $requiredSources = 0;

        if ($id) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id=:id');
            $s->execute(['id' => $id]);
            $product = $s->fetch();
            if (!$product) {
                http_response_code(404);
                exit('رکورد یافت نشد.');
            }

            $latest = $this->latestPricesSql();
            $s = $pdo->prepare("SELECT * FROM ({$latest}) lp WHERE product_id=:id ORDER BY source_no");
            $s->execute(['id' => $id]);
            $prices = $s->fetchAll();
            $requiredSources = $this->currentRequiredSources($product);
        }

        View::render($id ? 'prices/edit' : 'prices/create', compact('units', 'categories', 'product', 'prices', 'requiredSources'));
    }

    public function save(?int $id = null): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), $id ? 'prices.edit' : 'prices.create');
        Security::verifyCsrf();

        $code = trim((string) ($_POST['product_code'] ?? ''));
        $name = trim((string) ($_POST['product_name'] ?? ''));
        $unit = (int) ($_POST['unit_id'] ?? 0);
        $importance = max(1, min(3, (int) ($_POST['importance'] ?? 1)));
        $category = (int) ($_POST['category_id'] ?? 0);
        $minSources = max(1, min(3, (int) ($_POST['min_sources'] ?? 2)));
        $maxSources = max($minSources, min(3, (int) ($_POST['max_sources'] ?? 3)));
        $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'inactive', 'draft'], true) ? (string) $_POST['status'] : 'active';
        $rows = $_POST['prices'] ?? [];
        $valid = [];

        if ($code === '' || mb_strlen($code) > 100 || $name === '' || mb_strlen($name) > 255 || $unit < 1) {
            http_response_code(422);
            exit('اطلاعات کالا نامعتبر است.');
        }
        if (!is_array($rows) || count($rows) > 3) {
            http_response_code(422);
            exit('حداکثر سه منبع مجاز است.');
        }

        foreach ($rows as $row) {
            $supplier = trim((string) ($row['supplier'] ?? ''));
            $package = $row['package_price'] ?? '';
            $unitPrice = $row['unit_price'] ?? '';
            $date = trim((string) ($row['price_received_at'] ?? ''));
            if ($supplier === '' && $package === '' && $unitPrice === '' && $date === '') {
                continue;
            }
            if ($supplier === '' || $package === '' || $unitPrice === '' || $date === '') {
                exit('هر منبع باید کامل باشد.');
            }
            if (!is_numeric($package) || !is_numeric($unitPrice) || $package < 0 || $unitPrice < 0) {
                exit('قیمت نامعتبر است.');
            }
            $valid[] = [$supplier, (float) $package, (float) $unitPrice, Jalali::toGregorian($date)];
        }

        if ($id) {
            $s = Database::connection()->prepare('SELECT id,product_code FROM products WHERE id=:id');
            $s->execute(['id' => $id]);
            $existing = $s->fetch();
            if (!$existing) {
                http_response_code(404);
                exit('رکورد یافت نشد.');
            }

            $requiredSources = $this->currentRequiredSources([
                'id' => $id,
                'product_code' => (string) $existing['product_code'],
            ]);
            if ($requiredSources > 0 && count($valid) < $requiredSources) {
                http_response_code(422);
                exit('برای این وضعیت حداقل ' . $requiredSources . ' قیمت جدید بازار باید ثبت شود.');
            }
        }

        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT id FROM units WHERE id=:id AND is_active=1');
        $s->execute(['id' => $unit]);
        if (!$s->fetchColumn()) {
            exit('واحد انتخاب‌شده معتبر نیست.');
        }

        $category = $category > 0 ? (int) $category : null;
        $before = null;

        Database::transaction(function (PDO $pdo) use ($id, $code, $name, $unit, $importance, $category, $minSources, $maxSources, $status, $valid, &$before): void {
            if ($id) {
                $s = $pdo->prepare('SELECT * FROM products WHERE id=:id');
                $s->execute(['id' => $id]);
                $before = $s->fetch();

                $s = $pdo->prepare('UPDATE products SET product_code=:code,product_name=:name,unit_id=:unit,importance=:importance,category_id=:category,min_sources=:min_sources,max_sources=:max_sources,expected_sources=:max_sources,status=:status,is_active=:is_active,updated_at=GETDATE() WHERE id=:id');
                $s->execute([
                    'code' => $code,
                    'name' => $name,
                    'unit' => $unit,
                    'importance' => $importance,
                    'category' => $category,
                    'min_sources' => $minSources,
                    'max_sources' => $maxSources,
                    'status' => $status,
                    'is_active' => $status === 'active' ? 1 : 0,
                    'id' => $id
                ]);
                $productId = $id;
            } else {
                $s = $pdo->prepare('INSERT INTO products(product_code,product_name,unit_id,importance,category_id,min_sources,max_sources,expected_sources,status,is_active,registered_at,created_at,updated_at) OUTPUT INSERTED.id VALUES(:code,:name,:unit,:importance,:category,:min_sources,:max_sources,:expected_sources,:status,:is_active,GETDATE(),GETDATE(),GETDATE())');
                $s->execute([
                    'code' => $code,
                    'name' => $name,
                    'unit' => $unit,
                    'importance' => $importance,
                    'category' => $category,
                    'min_sources' => $minSources,
                    'max_sources' => $maxSources,
                    'expected_sources' => $maxSources,
                    'status' => $status,
                    'is_active' => $status === 'active' ? 1 : 0
                ]);
                $productId = (int) $s->fetchColumn();
            }

            $s = $pdo->prepare('INSERT INTO product_prices(product_id,source_no,supplier,package_price,unit_price,price_received_at,created_at,updated_at) VALUES(:pid,:no,:supplier,:package,:unit,:date,GETDATE(),GETDATE())');
            foreach ($valid as $i => $r) {
                $s->execute([
                    'pid' => $productId,
                    'no' => $i + 1,
                    'supplier' => $r[0],
                    'package' => $r[1],
                    'unit' => $r[2],
                    'date' => $r[3]
                ]);
            }
        });

        AuditService::log(Auth::userId(), $id ? 'product.update' : 'product.create', 'product', (string) ($id ?? $productId), $before, [
            'code' => $code,
            'name' => $name,
            'importance' => $importance,
            'category_id' => $category
        ]);

        Security::redirect('/prices');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.delete');
        Security::verifyCsrf();
        $s = Database::connection()->prepare("UPDATE products SET status='inactive',is_active=0,updated_at=GETDATE() WHERE id=:id");
        $s->execute(['id' => $id]);
        AuditService::log(Auth::userId(), 'product.archive', 'product', (string) $id);
        Security::redirect('/prices');
    }
}
