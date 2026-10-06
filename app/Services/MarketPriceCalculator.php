<?php
declare(strict_types=1);

namespace App\Services;

final class MarketPriceCalculator
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function groupByPaymentAgreement(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $agreementId = (int) ($row['PaymentAgreementID'] ?? 0);
            $key = (string) $agreementId;
            $groups[$key][] = $row;
        }

        $result = [];
        foreach ($groups as $rowsInGroup) {
            $prices = array_values(array_filter(
                array_map(static fn(array $row): float => (float) ($row['Price'] ?? 0), $rowsInGroup),
                static fn(float $price): bool => $price >= 0
            ));

            if (!$prices) {
                continue;
            }

            sort($prices, SORT_NUMERIC);
            $count = count($prices);
            $warning = false;
            $usedPrices = $prices;

            if ($count === 2) {
                $low = $prices[0];
                $high = $prices[1];
                if ($low > 0 && $high > (2 * $low)) {
                    $warning = true;
                }
            } elseif ($count === 3) {
                $low = $prices[0];
                $middle = $prices[1];
                $high = $prices[2];

                if ($middle > 0 && $high > (2 * $middle)) {
                    $usedPrices = [$low, $middle];
                } elseif ($low > 0 && $middle > (2 * $low)) {
                    $usedPrices = [$middle, $high];
                }
            }

            $result[] = [
                'PaymentAgreementID' => (int) ($rowsInGroup[0]['PaymentAgreementID'] ?? 0),
                'PaymentAgreementTitle' => (string) ($rowsInGroup[0]['PaymentAgreementTitle'] ?? ''),
                'count' => $count,
                'prices' => $prices,
                'used_prices' => $usedPrices,
                'average_price' => array_sum($usedPrices) / count($usedPrices),
                'warning' => $warning,
                'rows' => $rowsInGroup,
            ];
        }

        usort($result, static function (array $a, array $b): int {
            return strcmp((string) $a['PaymentAgreementTitle'], (string) $b['PaymentAgreementTitle']);
        });

        return $result;
    }
}
