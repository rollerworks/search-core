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

namespace Rollerworks\Component\Search\Exception;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class UnsupportedFieldSetException extends InvalidArgumentException
{
    public function __construct(array $expected, string $provided)
    {
        parent::__construct(
            \sprintf('FieldSet "%s" was not expected, expected one of "%s"', $provided, implode('", "', $expected))
        );
    }
}
