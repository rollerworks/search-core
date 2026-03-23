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

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\BirthdayValueComparator;

/**
 * @internal
 */
final class BirthdayValueComparatorTest extends TestCase
{
    private BirthdayValueComparator $comparison;

    protected function setUp(): void
    {
        $this->comparison = new BirthdayValueComparator();
    }

    /**
     * @test
     */
    public function value_equals(): void
    {
        self::assertTrue($this->comparison->isEqual(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-21 12:46:00'), []));
        self::assertTrue($this->comparison->isEqual(1, 1, []));
    }

    /**
     * @test
     */
    public function value_not_equals(): void
    {
        self::assertFalse($this->comparison->isEqual(1, 2, []));
        self::assertFalse($this->comparison->isEqual(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-22 12:46:00'), []));
        self::assertFalse($this->comparison->isEqual(new \DateTimeImmutable('2013-09-21 12:46:00'), 0, []));
    }

    /**
     * @test
     */
    public function first_value_is_higher(): void
    {
        self::assertTrue($this->comparison->isHigher(5, 1, []));
        self::assertTrue($this->comparison->isHigher(new \DateTimeImmutable('2013-09-23 12:46:00'), new \DateTimeImmutable('2013-09-21 12:46:00'), []));

        // Difference types cannot be compared
        self::assertFalse($this->comparison->isHigher(new \DateTimeImmutable('2013-09-23 12:46:00'), 1, []));
        self::assertFalse($this->comparison->isHigher(1, new \DateTimeImmutable('2013-09-23 12:46:00'), []));
    }

    /**
     * @test
     */
    public function it_returns_true_when_first_value_is_lower(): void
    {
        self::assertTrue($this->comparison->isLower(2, 5, []));
        self::assertTrue($this->comparison->isLower(new \DateTimeImmutable('2013-09-21 12:46:00'), new \DateTimeImmutable('2013-09-23 12:46:00'), []));

        // Difference types cannot be compared
        self::assertFalse($this->comparison->isLower(new \DateTimeImmutable('2013-09-23 12:46:00'), 1, []));
        self::assertFalse($this->comparison->isLower(1, new \DateTimeImmutable('2013-09-23 12:46:00'), []));
    }
}
