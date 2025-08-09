<?php

/**
 * Free to use, modify, distribute. No warranty. Include license.
 */

declare(strict_types=1);

namespace Creativestyle\Sales\Core\Checkout\Sales\Processor;

use Creativestyle\Sales\Core\Checkout\Sales\Service\DiscountDecision;
use Creativestyle\Sales\Core\Checkout\Sales\Service\DiscountKind;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class SalesDiscountProcessor implements CartProcessorInterface
{
    public const ABSOLUTE_DISCOUNT_CODE = 'ABSOLUTE_DISCOUNT';
    public const PERCENTAGE_DISCOUNT_CODE = 'PERCENTAGE_DISCOUNT';

    public function __construct(
        private readonly PercentagePriceCalculator $percentageCalculator,
        private readonly AbsolutePriceCalculator $absoluteCalculator,
        private readonly DiscountDecision $decisionService
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $products = $this->getProductItems($toCalculate);

        if (0 === $products->count()) {
            return;
        }

        $absoluteDiscountAmount = $this->decisionService->calculateEveryNthFree(
            $products,
            $context->getItemRounding(),
            $context
        );

        $subtotal = $products->getPrices()->sum()->getTotalPrice();

        $percentageDiscountAmount = $this->decisionService->calculatePercentageOverThreshold(
            $subtotal,
            $context->getTotalRounding(),
            $context
        );

        if ($absoluteDiscountAmount <= 0.0 && $percentageDiscountAmount <= 0.0) {
            return;
        }

        $choice = $this->decisionService->chooseBest($absoluteDiscountAmount, $percentageDiscountAmount);
        $percent = $this->decisionService->percentValue($context);

        match ($choice->kind) {
            DiscountKind::ABSOLUTE => $this->applyAbsoluteDiscount($toCalculate, $products, $choice->amount, $context),
            DiscountKind::PERCENTAGE => $this->applyPercentageDiscount($toCalculate, $products, $percent, $context),
        };
    }

    private function getProductItems(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filter(static function (LineItem $item) {
            return LineItem::PRODUCT_LINE_ITEM_TYPE === $item->getType();
        });
    }

    private function applyAbsoluteDiscount(
        Cart $toCalculate,
        LineItemCollection $scope,
        float $amount,
        SalesChannelContext $context
    ): void {
        if ($amount <= 0.0) {
            return;
        }

        $discount = $this->createDiscount(self::ABSOLUTE_DISCOUNT_CODE, 'sales.discount.absolute');

        $definition = new AbsolutePriceDefinition(-$amount);
        $discount->setPriceDefinition($definition);

        $discount->setPrice(
            $this->absoluteCalculator->calculate($definition->getPrice(), $scope->getPrices(), $context)
        );

        $toCalculate->add($discount);
    }

    private function applyPercentageDiscount(
        Cart $toCalculate,
        LineItemCollection $scope,
        float $percent,
        SalesChannelContext $context
    ): void {
        if ($percent <= 0.0) {
            return;
        }

        $discount = $this->createDiscount(self::PERCENTAGE_DISCOUNT_CODE, 'sales.discount.percentage');

        $definition = new PercentagePriceDefinition(
            -$percent,
            new LineItemRule(LineItemRule::OPERATOR_EQ, $scope->getKeys())
        );

        $discount->setPriceDefinition($definition);

        $discount->setPrice(
            $this->percentageCalculator->calculate($definition->getPercentage(), $scope->getPrices(), $context)
        );

        $toCalculate->add($discount);
    }

    private function createDiscount(string $id, string $label): LineItem
    {
        $discount = new LineItem($id, 'sales_discount', null, 1);

        $discount->setLabel($label);
        $discount->setGood(false);
        $discount->setStackable(false);
        $discount->setRemovable(false);

        return $discount;
    }
}
