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

namespace Extension\Core\ValueComparison;

use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\DateTimeIntervalValueComparator;

/**
 * @internal
 */
final class DateTimeIntervalValueComparatorTest extends TestCase
{
    private DateTimeIntervalValueComparator $comparison;

    protected function setUp(): void
    {
        $this->comparison = new DateTimeIntervalValueComparator();
    }

    /**
     * @test
     */
    public function true_when_equal(): void
    {
        self::assertTrue($this->comparison->isEqual(CarbonInterval::fromString('32m'), CarbonInterval::fromString('32m'), []));
        self::assertTrue($this->comparison->isEqual(CarbonInterval::fromString('1w 3d 4h 32m 23s'), CarbonInterval::fromString('1w 3d 4h 32m 23s'), []));
        self::assertTrue($this->comparison->isEqual(new \DateTimeImmutable('2013-09-22 12:46:00'), new \DateTimeImmutable('2013-09-22 12:46:00'), []));
    }

    /**
     * @test
     */
    public function false_when_not_equal(): void
    {
        self::assertFalse($this->comparison->isEqual(CarbonInterval::fromString('2w'), CarbonInterval::fromString('1w'), []));
        self::assertFalse($this->comparison->isEqual(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-22 12:46:00'), []));
        self::assertFalse($this->comparison->isEqual(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-21 12:40:00'), []));

        // Difference types cannot be compared
        self::assertFalse($this->comparison->isEqual(CarbonInterval::fromString('2w'), new \DateTimeImmutable('2013-09-21 12:40:00'), []));
    }

    /**
     * @test
     */
    public function first_higher(): void
    {
        self::assertTrue($this->comparison->isHigher(new \DateTimeImmutable('2013-09-23 12:46:00'), new \DateTimeImmutable('2013-09-21 12:46:00'), []));
        self::assertTrue($this->comparison->isHigher(CarbonInterval::fromString('35m'), new \DateTimeImmutable('2013-09-21 12:46:00'), []));
        self::assertTrue($this->comparison->isHigher(CarbonInterval::fromString('35m'), CarbonInterval::fromString('32m'), []));
    }

    /**
     * @test
     */
    public function is_lower(): void
    {
        self::assertTrue($this->comparison->isLower(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-23 12:46:00'), []));
        self::assertTrue($this->comparison->isLower(new \DateTimeImmutable('2013-09-21 12:46:00'), CarbonInterval::fromString('30m'), []));
        self::assertTrue($this->comparison->isLower(CarbonInterval::fromString('30m'), CarbonInterval::fromString('32m'), []));
    }
}
