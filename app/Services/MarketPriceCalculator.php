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

            if ($count >= 2) {
                // Flag the group if any pair differs by more than 2x.
                // Do not silently discard a price: approvals and the price list
                // must use the same data-quality rule.
                for ($i = 1; $i < $count; $i++) {
                    $low = $prices[$i - 1];
                    $high = $prices[$i];
                    if ($low > 0 && $high > (2 * $low)) {
                        $warning = true;
                        break;
                    }
                }
            }

            $result[] = [
                'PaymentAgreementID' => (int) ($rowsInGroup[0]['PaymentAgreementID'] ?? 0),
                'PaymentAgreementTitle' => (string) ($rowsInGroup[0]['PaymentAgreementTitle'] ?? ''),
                'count' => $count,
                'prices' => $prices,
                'used_prices' => $usedPrices,
                'average_price' => $warning ? null : (array_sum($usedPrices) / count($usedPrices)),
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
