<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\PermissionService;
use App\Support\Jalali;
use PDO;

final class PriceController
{
    private function latestPricesSql(): string
    {
        return "SELECT * FROM (
            SELECT pp.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY pp.product_id, pp.source_no
                       ORDER BY pp.price_received_at DESC, pp.id DESC
                   ) AS rn
            FROM product_prices pp
        ) ranked_prices WHERE rn = 1";
    }

    public function index(): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.view');

        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'registered_at');
        $dir = ($_GET['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $allowed = [
            'product_code' => 'p.product_code',
            'product_name' => 'p.product_name',
            'unit_name' => 'u.name',
            'registered_at' => 'p.registered_at',
            'latest_price_date' => 'latest_price_date',
        ];
        $order = $allowed[$sort] ?? $allowed['registered_at'];
        $where = '';
        $params = [];

        if ($q !== '') {
            $where = ' WHERE p.product_code LIKE :q OR p.product_name LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $pdo = Database::connection();
        $latestSql = $this->latestPricesSql();

        $sql = "SELECT p.id,p.product_code,p.product_name,p.registered_at,u.name unit_name,
                (SELECT MAX(lp.price_received_at) FROM ({$latestSql}) lp WHERE lp.product_id=p.id) latest_price_date
                FROM products p
                INNER JOIN units u ON u.id=p.unit_id{$where}
                ORDER BY {$order} {$dir}";

        $s = $pdo->prepare($sql);
        $s->execute($params);
        $products = $s->fetchAll();

        $ids = array_column($products, 'id');
        $prices = [];

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT * FROM ({$latestSql}) lp WHERE lp.product_id IN ({$in}) ORDER BY product_id,source_no");
            $s->execute($ids);

            foreach ($s->fetchAll() as $r) {
                $prices[$r['product_id']][] = $r;
            }
        }

        $data = compact('products', 'prices', 'q', 'sort', 'dir');

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

        $pdo = Database::connection();
        $latestSql = $this->latestPricesSql();

        $sql = "WITH latest AS ({$latestSql}),
        stats AS (
            SELECT
                p.id,
                p.product_code,
                p.product_name,
                u.name AS unit_name,
                MAX(l.price_received_at) AS latest_price_date,
                COUNT(l.id) AS source_count,
                MIN(l.unit_price) AS min_price,
                MAX(l.unit_price) AS max_price,
                AVG(l.unit_price) AS avg_price
            FROM products p
            INNER JOIN units u ON u.id=p.unit_id
            LEFT JOIN latest l ON l.product_id=p.id
            GROUP BY p.id,p.product_code,p.product_name,u.name
        )
        SELECT *,
            CASE
                WHEN latest_price_date IS NULL THEN 100
                WHEN DATEDIFF(DAY, latest_price_date, GETDATE()) >= 30 THEN 80
                WHEN DATEDIFF(DAY, latest_price_date, GETDATE()) >= 15 THEN 60
                WHEN DATEDIFF(DAY, latest_price_date, GETDATE()) >= 7 THEN 35
                ELSE 5
            END
            + CASE WHEN source_count < 3 THEN (3-source_count)*15 ELSE 0 END
            + CASE
                WHEN avg_price > 0 AND (max_price-min_price)/avg_price >= 0.20 THEN 25
                WHEN avg_price > 0 AND (max_price-min_price)/avg_price >= 0.10 THEN 15
                WHEN avg_price > 0 AND (max_price-min_price)/avg_price >= 0.05 THEN 8
                ELSE 0
              END AS priority_score
        FROM stats
        WHERE latest_price_date IS NULL
           OR DATEDIFF(DAY, latest_price_date, GETDATE()) >= 7
           OR source_count < 3
           OR (avg_price > 0 AND (max_price-min_price)/avg_price >= 0.10)
        ORDER BY priority_score DESC, latest_price_date ASC, product_code ASC";

        $rows = $pdo->query($sql)->fetchAll();
        View::render('prices/queue', compact('rows'));
    }

    public function form(?int $id = null): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), $id ? 'prices.edit' : 'prices.create');

        $pdo = Database::connection();
        $units = $pdo->query('SELECT id,name FROM units WHERE is_active=1 ORDER BY name')->fetchAll();
        $product = null;
        $prices = [];

        if ($id) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id=:id');
            $s->execute(['id' => $id]);
            $product = $s->fetch();

            if (!$product) {
                http_response_code(404);
                exit('رکورد یافت نشد.');
            }

            $latestSql = $this->latestPricesSql();
            $s = $pdo->prepare("SELECT * FROM ({$latestSql}) lp WHERE product_id=:id ORDER BY source_no");
            $s->execute(['id' => $id]);
            $prices = $s->fetchAll();
        }

        View::render($id ? 'prices/edit' : 'prices/create', compact('units', 'product', 'prices'));
    }

    public function save(?int $id = null): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), $id ? 'prices.edit' : 'prices.create');
        Security::verifyCsrf();

        $code = trim((string) ($_POST['product_code'] ?? ''));
        $name = trim((string) ($_POST['product_name'] ?? ''));
        $unit = (int) ($_POST['unit_id'] ?? 0);
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
                http_response_code(422);
                exit('هر منبع باید کامل باشد.');
            }

            if (!is_numeric($package) || !is_numeric($unitPrice) || $package < 0 || $unitPrice < 0) {
                http_response_code(422);
                exit('قیمت نامعتبر است.');
            }

            $valid[] = [$supplier, (float) $package, (float) $unitPrice, Jalali::toGregorian($date)];
        }

        if (!$valid) {
            http_response_code(422);
            exit('حداقل یک قیمت کامل باید ثبت شود.');
        }

        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT id FROM units WHERE id=:id AND is_active=1');
        $s->execute(['id' => $unit]);

        if (!$s->fetchColumn()) {
            http_response_code(422);
            exit('واحد انتخاب‌شده معتبر نیست.');
        }

        Database::transaction(function (PDO $pdo) use ($id, $code, $name, $unit, $valid): void {
            if ($id) {
                $s = $pdo->prepare('UPDATE products SET product_code=:code,product_name=:name,unit_id=:unit,updated_at=GETDATE() WHERE id=:id');
                $s->execute(['code' => $code, 'name' => $name, 'unit' => $unit, 'id' => $id]);
                $productId = $id;
            } else {
                $s = $pdo->prepare('INSERT INTO products(product_code,product_name,unit_id,registered_at,created_at,updated_at) OUTPUT INSERTED.id VALUES(:code,:name,:unit,GETDATE(),GETDATE(),GETDATE())');
                $s->execute(['code' => $code, 'name' => $name, 'unit' => $unit]);
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
                    'date' => $r[3],
                ]);
            }
        });

        Security::redirect('/prices');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        PermissionService::require(Auth::userId(), 'prices.delete');
        Security::verifyCsrf();

        $s = Database::connection()->prepare('DELETE FROM products WHERE id=:id');
        $s->execute(['id' => $id]);
        Security::redirect('/prices');
    }
}
