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

namespace Rollerworks\Component\Search\Tests;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\Value\Range;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class SearchOrderTest extends TestCase
{
    /**
     * @test
     *
     * @group legacy
     */
    public function construct_with_values_group(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('desc'));

        $order = new SearchOrder($valuesGroup);

        self::assertSame(['@id' => 'desc'], $order->getFields());
        self::assertEquals($valuesGroup, $order->getValuesGroup());
    }

    /**
     * @test
     */
    public function construct_with_multiple_fields_at_value_group(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('desc'));
        $valuesGroup->addField('@name', (new ValuesBag())->addSimpleValue('asc'));
        $order = new SearchOrder($valuesGroup);

        self::assertSame(['@id' => 'desc', '@name' => 'asc'], $order->getFields());
        self::assertEquals($valuesGroup, $order->getValuesGroup());
    }

    /**
     * @test
     */
    public function construct_with_array(): void
    {
        $order = new SearchOrder(['@id' => 'desc']);

        self::assertEquals((new ValuesGroup())->addField('@id', (new ValuesBag())->addSimpleValue('desc')), $order->getValuesGroup());
        self::assertSame(['@id' => 'desc'], $order->getFields());
    }

    /**
     * @test
     */
    public function construct_with_multiple_fields_array(): void
    {
        $order = new SearchOrder(['@id' => 'desc', '@name' => 'asc']);

        self::assertEquals(
            (new ValuesGroup())
                ->addField('@id', (new ValuesBag())->addSimpleValue('desc'))
                ->addField('@name', (new ValuesBag())->addSimpleValue('asc')),
            $order->getValuesGroup(),
        );
        self::assertSame(['@id' => 'desc', '@name' => 'asc'], $order->getFields());
    }

    /**
     * @test
     */
    public function construct_with_uppercase_direction(): void
    {
        $order = new SearchOrder(['@id' => 'DESC', '@name' => 'ASC']);

        self::assertSame(['@id' => 'desc', '@name' => 'asc'], $order->getFields());
        self::assertEquals(
            (new ValuesGroup())
                ->addField('@id', (new ValuesBag())->addSimpleValue('desc'))
                ->addField('@name', (new ValuesBag())->addSimpleValue('asc')),
            $order->getValuesGroup(),
        );
    }

    /**
     * @test
     */
    public function fail_with_invalid_field_name(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('id', (new ValuesBag())->addSimpleValue('desc'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "id" is not a valid ordering field. Expected either "@id".');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_invalid_value_type(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue(['up']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "@id" direction must be a string.');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_invalid_direction(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('up'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid direction provided "up" for field "@id", must be either "asc" or "desc" (case insensitive).');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_nested_groups(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('up'));
        $valuesGroup->addGroup(new ValuesGroup());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A SearchOrder must have a single-level structure. Only fields with single values are accepted.');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_multi_value(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('desc')->addSimpleValue('asc'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "@id" must have a single value only.');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_multi_value_types(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addSimpleValue('desc')->add(new Range('10', '20')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "@id" must have a single value only.');

        new SearchOrder($valuesGroup);
    }

    /**
     * @test
     */
    public function fail_with_invalid_type_value(): void
    {
        $valuesGroup = new ValuesGroup();
        $valuesGroup->addField('@id', (new ValuesBag())->addExcludedSimpleValue('desc'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "@id" must have a single value only.');

        new SearchOrder($valuesGroup);
    }
}
