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

namespace Rollerworks\Component\Search\Value;

final readonly class Compare implements RequiresComparatorValueHolder
{
    public const OPERATORS = ['>=', '<=', '<>', '<', '>'];

    public function __construct(
        private readonly mixed $value,
        private readonly string $operator,
    ) {
        if (! \in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException(
                \sprintf('Unknown operator "%s".', $operator)
            );
        }
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue()
    {
        return $this->value;
    }
}
