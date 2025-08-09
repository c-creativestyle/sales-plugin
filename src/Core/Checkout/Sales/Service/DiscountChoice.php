<?php

/**
 * Free to use, modify, distribute. No warranty. Include license.
 */

declare(strict_types=1);

namespace Creativestyle\Sales\Core\Checkout\Sales\Service;

final class DiscountChoice
{
    public function __construct(
        public readonly DiscountKind $kind,
        public readonly float $amount
    ) {
    }
}
