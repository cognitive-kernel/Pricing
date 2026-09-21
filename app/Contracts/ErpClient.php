<?php
declare(strict_types=1);
namespace App\Contracts;
interface ErpClient{public function products():array;public function prices(int $productId):array;public function pushPrice(array $payload):bool;}