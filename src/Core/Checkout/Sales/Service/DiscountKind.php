<?php

/**
 * Free to use, modify, distribute. No warranty. Include license.
 */

declare(strict_types=1);

namespace Creativestyle\Sales\Core\Checkout\Sales\Service;

enum DiscountKind: string
{
    case ABSOLUTE = 'absolute';
    case PERCENTAGE = 'percentage';
}
