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
 *
 * @psalm-type Sorting = array<string, 'asc'|'desc'>
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
    public function construct_with_prepend(): void
    {
        // Prepend should be placed before any user sorting
        self::assertSortingSame(
            finalSorting: ['@date' => 'asc', '@id' => 'desc', '@name' => 'asc'],
            prepend: ['@date' => 'asc'],
            append: [],
            fields: ['@id' => 'desc', '@name' => 'asc'],
            actual: new SearchOrder(['@id' => 'DESC', '@name' => 'ASC'], prepend: ['@date' => 'ASC']),
        );

        // Prepend should keep the original position
        self::assertSortingSame(
            finalSorting: ['@name' => 'asc', '@id' => 'desc'],
            prepend: ['@name' => 'desc'],
            append: [],
            fields: ['@id' => 'desc', '@name' => 'asc'],
            actual: new SearchOrder(['@id' => 'DESC', '@name' => 'ASC'], prepend: ['@name' => 'DESC']),
        );
    }

    /**
     * @test
     */
    public function construct_with_append(): void
    {
        // Append should be placed after the user sorting
        self::assertSortingSame(
            finalSorting: ['@id' => 'desc', '@name' => 'asc', '@date' => 'asc'],
            prepend: [],
            append: ['@date' => 'asc'],
            fields: ['@id' => 'desc', '@name' => 'asc'],
            actual: new SearchOrder(['@id' => 'DESC', '@name' => 'ASC'], append: ['@date' => 'ASC']),
        );

        // Append should ignore already existing sorting
        self::assertSortingSame(
            finalSorting: ['@id' => 'desc', '@name' => 'asc', '@date' => 'desc'],
            prepend: [],
            append: ['@id' => 'asc', '@date' => 'desc'],
            fields: ['@id' => 'desc', '@name' => 'asc'],
            actual: new SearchOrder(['@id' => 'DESC', '@name' => 'ASC'], append: ['@id' => 'ASC', '@date' => 'desc']),
        );

        // Prepend and append
        self::assertSortingSame(
            finalSorting: ['@name' => 'asc', '@group' => 'desc', '@id' => 'desc', '@date' => 'desc'],
            prepend: ['@name' => 'desc', '@group' => 'desc'],
            append: ['@id' => 'asc', '@date' => 'desc'],
            fields: ['@id' => 'desc', '@name' => 'asc'],
            actual: new SearchOrder(
                values: ['@id' => 'DESC', '@name' => 'ASC'],
                prepend: ['@name' => 'DESC', '@group' => 'DESC'],
                append: ['@id' => 'ASC', '@date' => 'desc']
            ),
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
        $this->expectExceptionMessage('Field "@id" direction must be a string, "array" given.');

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

    /**
     * @param Sorting $finalSorting
     * @param Sorting $prepend
     * @param Sorting $append
     * @param Sorting $fields
     */
    private static function assertSortingSame(array $finalSorting, array $prepend, array $append, array $fields, SearchOrder $actual): void
    {
        $valuesGroup = new ValuesGroup();

        foreach ($finalSorting as $field => $direction) {
            $valuesGroup->addField($field, (new ValuesBag())->addSimpleValue($direction));
        }

        self::assertEquals($valuesGroup, $actual->getValuesGroup(), 'The values group does not match the expected values group.');
        self::assertSame(array_keys($finalSorting), array_keys($actual->getValuesGroup()->getFields()), 'The fields do not match the expected fields order.');

        self::assertSame($finalSorting, $actual->getSorting(), 'The final sorting does not match the expected sorting.');
        self::assertSame($prepend, $actual->getPrepend(), 'The prepend does not match.');
        self::assertSame($fields, $actual->getFields(), 'The fields do not match.');
        self::assertSame($append, $actual->getAppend(), 'The append does not match.');
    }
}
