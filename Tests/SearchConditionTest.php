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
use Rollerworks\Component\Search\Exception\UnsupportedFieldSetException;
use Rollerworks\Component\Search\FieldSet;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\SearchPrimaryCondition;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class SearchConditionTest extends TestCase
{
    /** @test */
    public function it_can_check_if_field_set_is_supported(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->assertFieldSetName('test');
        $condition->assertFieldSetName('test', 'foo');

        // Dummy tests, no error means it works.
        self::assertEquals(new ValuesGroup(), $condition->getValuesGroup());
    }

    /** @test */
    public function it_gives_an_exception_when_checked_field_set_is_not_supported(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());

        $this->expectException(UnsupportedFieldSetException::class);
        $this->expectExceptionMessage((new UnsupportedFieldSetException(['bar', 'foo'], 'test'))->getMessage());

        $condition->assertFieldSetName('bar', 'foo');
    }

    /** @test */
    public function it_allows_setting_a_primary_condition(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $primaryCondition = new SearchPrimaryCondition((new ValuesGroup())->addField('id', new ValuesBag()));

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->setPrimaryCondition($primaryCondition);

        self::assertEquals($primaryCondition, $condition->getPrimaryCondition());
    }

    /** @test */
    public function it_allows_unsetting_the_primary_condition(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $primaryCondition = new SearchPrimaryCondition((new ValuesGroup())->addField('id', new ValuesBag()));

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->setPrimaryCondition($primaryCondition);
        $condition->setPrimaryCondition(null);

        self::assertNull($condition->getPrimaryCondition());
    }

    /** @test */
    public function it_gives_whether_condition_is_empty(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        // Empty condition
        self::assertTrue((new SearchCondition($fieldSet, new ValuesGroup()))->isEmpty());

        // With primary condition (not part of the cached condition)
        self::assertTrue(
            (new SearchCondition($fieldSet, new ValuesGroup()))
                ->setPrimaryCondition(
                    new SearchPrimaryCondition((new ValuesGroup())->addField('id', new ValuesBag())
                ),
            )->isEmpty(),
        );

        // -- None empty --

        // Field
        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->getValuesGroup()->addField('id', new ValuesBag());
        self::assertFalse($condition->isEmpty());

        // Nested group
        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->getValuesGroup()->addGroup((new ValuesGroup()));
        self::assertFalse($condition->isEmpty());

        // Ordering
        self::assertFalse(
            (new SearchCondition($fieldSet, new ValuesGroup()))
                ->setOrder(new SearchOrder(((new ValuesGroup())->addField('id', (new ValuesBag())->addSimpleValue('desc')))),
            )->isEmpty(),
        );
    }
}
