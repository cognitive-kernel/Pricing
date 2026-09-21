<?php
declare(strict_types=1);
namespace App\Contracts;
interface PriceSource{public function fetch(array $product):array;}