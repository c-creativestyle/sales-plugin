<?php

/**
 * Free to use, modify, distribute. No warranty. Include license.
 */

declare(strict_types=1);

namespace Creativestyle\Sales\Core\Checkout\Sales\Service;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class DiscountDecision
{
    public function __construct(
        private readonly CashRounding $cashRounding,
        private readonly SystemConfigService $config
    ) {
    }

    public function calculateEveryNthFree(
        LineItemCollection $products,
        CashRoundingConfig $config,
        SalesChannelContext $context
    ): float {
        $step = max(1, (int) ($this->get('nthStep', $context) ?? 5));

        $groupBy = (string) ($this->get('nthGroupBy', $context) ?? 'name');

        $total = 0.0;
        $groups = [];

        foreach ($products->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $item) {
            $key = $this->groupKey($item, $groupBy);

            $groups[$key]['qty'] = ($groups[$key]['qty'] ?? 0) + $item->getQuantity();

            $unitPrice = $item->getPrice()?->getUnitPrice();

            $groups[$key]['unit'] = $unitPrice;
        }

        foreach ($groups as $g) {
            $freeUnits = intdiv($g['qty'], $step);
            if ($freeUnits > 0) {
                $total += $freeUnits * (float) $g['unit'];
            }
        }

        return $this->cashRounding->cashRound($total, $config);
    }

    public function calculatePercentageOverThreshold(
        float $subtotal,
        CashRoundingConfig $config,
        SalesChannelContext $context
    ): float {
        $threshold = (float) ($this->get('percentThreshold', $context) ?? 100.0);
        $percent = (float) ($this->get('percentValue', $context) ?? 10.0);

        if ($subtotal <= $threshold) {
            return 0.0;
        }

        return $this->cashRounding->cashRound($subtotal * ($percent / 100.0), $config);
    }

    public function chooseBest(float $absoluteAmount, float $percentageAmount): DiscountChoice
    {
        return ($absoluteAmount >= $percentageAmount)
            ? new DiscountChoice(DiscountKind::ABSOLUTE, $absoluteAmount)
            : new DiscountChoice(DiscountKind::PERCENTAGE, $percentageAmount);
    }

    public function percentValue(SalesChannelContext $context): float
    {
        return (float) ($this->get('percentValue', $context) ?? 10.0);
    }

    private function groupKey(LineItem $item, string $groupBy): string
    {
        return match ($groupBy) {
            'refId' => (string) $item->getReferencedId(),
            'type' => $item->getType(),
            default => (string) $item->getLabel(),
        };
    }

    private function get(string $name, SalesChannelContext $context): mixed
    {
        return $this->config->get('CreativestyleSales.config.' . $name, $context->getSalesChannelId());
    }
}
