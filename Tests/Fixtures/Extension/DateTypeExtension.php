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

namespace Rollerworks\Component\Search\Tests\Fixtures\Extension;

use Lifthill\Component\Datagrid\Column\AbstractTypeExtension;
use Lifthill\Component\Datagrid\Extension\Core\Type\DateTimeType;

final class DateTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DateTimeType::class];
    }
}
