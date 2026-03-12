<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Extension\Core\DataTransformer;

/**
 * Transforms between an integer and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class IntegerToStringTransformer extends NumberToStringTransformer
{
    /**
     * @param self::ROUND_* $roundingMode
     */
    public function __construct(?int $roundingMode = self::ROUND_DOWN, bool $grouping = false)
    {
        if ($roundingMode === null) {
            trigger_deprecation('rollerworks/search', '2.0-BETA13', 'Passing null as the first argument to "%s()" is deprecated, pass IntegerToStringTransformer::ROUND_DOWN instead. This will fail 3.0', __CLASS__);

            $roundingMode = self::ROUND_DOWN;
        }

        parent::__construct(0, $grouping, $roundingMode);
    }

    public function reverseTransform($value): ?int
    {
        $result = parent::reverseTransform($value);

        return $result !== null ? (int) $result : null;
    }
}
