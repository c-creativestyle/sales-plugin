<?php

declare(strict_types=1);

namespace Creativestyle\Sales\Test\Unit\Service;

use Creativestyle\Sales\Core\Checkout\Sales\Service\DiscountDecision;
use Creativestyle\Sales\Core\Checkout\Sales\Service\DiscountKind;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class DiscountDecisionTest extends TestCase
{
    private CashRoundingConfig $cashRoundingConfig;
    private DiscountDecision $decision;
    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->cashRoundingConfig = new CashRoundingConfig(2, 2, false);

        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnCallback(function (string $key): mixed {
            return match (true) {
                str_ends_with($key, 'nthGroupBy') => 'refId',
                str_ends_with($key, 'nthStep') => 5,
                str_ends_with($key, 'percentThreshold') => 100.0,
                str_ends_with($key, 'percentValue') => 10.0,
                default => null,
            };
        });

        $this->decision = new DiscountDecision(new CashRounding(), $config);

        $this->context = $this->createMock(SalesChannelContext::class);
        $this->context->method('getSalesChannelId')->willReturn('test-channel');
    }

    public function testEveryFifthFree(): void
    {
        $items = new LineItemCollection();
        $items->add($this->product('A', 5, 2.00));
        $items->add($this->product('B', 4, 10.00));
        $items->add($this->product('A', 5, 2.00));

        self::assertSame(
            4.00,
            $this->decision->calculateEveryNthFree($items, $this->cashRoundingConfig, $this->context)
        );
    }

    public function testTenPercentOverThreshold(): void
    {
        self::assertSame(
            0.0,
            $this->decision->calculatePercentageOverThreshold(100.0, $this->cashRoundingConfig, $this->context)
        );
        self::assertSame(
            20.0,
            $this->decision->calculatePercentageOverThreshold(200.0, $this->cashRoundingConfig, $this->context)
        );
        self::assertSame(
            20.0,
            $this->decision->calculatePercentageOverThreshold(100.0 + 0.0 + 100.0, $this->cashRoundingConfig, $this->context)
        );
    }

    public function testChooseBest(): void
    {
        $choice = $this->decision->chooseBest(15.0, 10.0);
        self::assertSame(DiscountKind::ABSOLUTE, $choice->kind);
        self::assertSame(15.0, $choice->amount);

        $choice = $this->decision->chooseBest(3.0, 12.0);
        self::assertSame(DiscountKind::PERCENTAGE, $choice->kind);
        self::assertSame(12.0, $choice->amount);
    }

    private function product(string $refId, int $qty, float $unitPrice): LineItem
    {
        static $i = 0;
        $li = new LineItem('test-' . $refId . '-' . $qty . '-' . (++$i), LineItem::PRODUCT_LINE_ITEM_TYPE, $refId, $qty);
        $li->setPrice(new CalculatedPrice($unitPrice, $unitPrice, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $li;
    }
}
