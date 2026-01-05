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

namespace Rollerworks\Component\Search\Tests\Extension\Core\ChoiceList;

use Rollerworks\Component\Search\Extension\Core\ChoiceList\ArrayChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\LazyChoiceList;

trait ChoiceListAssertionTrait
{
    private static function assertEqualsArrayChoiceList(ArrayChoiceList $expected, mixed $actual): void
    {
        self::assertInstanceOf(ArrayChoiceList::class, $actual);
        self::assertEquals($expected->getChoices(), $actual->getChoices());
        self::assertEquals($expected->getStructuredValues(), $actual->getStructuredValues());
        self::assertEquals($expected->getOriginalKeys(), $actual->getOriginalKeys());
    }

    private static function assertEqualsLazyChoiceList(LazyChoiceList $expected, mixed $actual): void
    {
        self::assertInstanceOf(LazyChoiceList::class, $actual);
        self::assertEquals($expected->getChoices(), $actual->getChoices());
        self::assertEquals($expected->getValues(), $actual->getValues());
        self::assertEquals($expected->getOriginalKeys(), $actual->getOriginalKeys());
    }
}
