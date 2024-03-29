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
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
final class ArrayChoiceListTest extends AbstractChoiceListTest
{
    private $object;

    protected function setUp(): void
    {
        $this->object = new \stdClass();

        parent::setUp();
    }

    protected function createChoiceList(): ChoiceList
    {
        return new ArrayChoiceList($this->getChoices());
    }

    protected function getChoices()
    {
        return [0, 1, 1.5, '1', 'a', false, true, $this->object, null];
    }

    protected function getValues()
    {
        return ['0', '1', '2', '3', '4', '5', '6', '7', '8'];
    }

    /** @test */
    public function create_choice_list_with_value_callback(): void
    {
        $callback = static fn ($choice) => ':' . $choice;

        $choiceList = new ArrayChoiceList([2 => 'foo', 7 => 'bar', 10 => 'baz'], $callback);

        self::assertSame([':foo', ':bar', ':baz'], $choiceList->getValues());
        self::assertSame([':foo' => 'foo', ':bar' => 'bar', ':baz' => 'baz'], $choiceList->getChoices());
        self::assertSame([':foo' => 2, ':bar' => 7, ':baz' => 10], $choiceList->getOriginalKeys());
        self::assertSame([1 => 'foo', 2 => 'baz'], $choiceList->getChoicesForValues([1 => ':foo', 2 => ':baz']));
        self::assertSame([1 => ':foo', 2 => ':baz'], $choiceList->getValuesForChoices([1 => 'foo', 2 => 'baz']));
    }

    /** @test */
    public function create_choice_list_without_value_callback_and_duplicate_free_to_string_choices(): void
    {
        $choiceList = new ArrayChoiceList([2 => 'foo', 7 => 'bar', 10 => 123]);

        self::assertSame(['foo', 'bar', '123'], $choiceList->getValues());
        self::assertSame(['foo' => 'foo', 'bar' => 'bar', '123' => 123], $choiceList->getChoices());
        self::assertSame(['foo' => 2, 'bar' => 7, '123' => 10], $choiceList->getOriginalKeys());
        self::assertSame([1 => 'foo', 2 => 123], $choiceList->getChoicesForValues([1 => 'foo', 2 => '123']));
        self::assertSame([1 => 'foo', 2 => '123'], $choiceList->getValuesForChoices([1 => 'foo', 2 => 123]));
    }

    /** @test */
    public function create_choice_list_without_value_callback_and_to_string_duplicates(): void
    {
        $choiceList = new ArrayChoiceList([2 => 'foo', 7 => '123', 10 => 123]);

        self::assertSame(['0', '1', '2'], $choiceList->getValues());
        self::assertSame(['0' => 'foo', '1' => '123', '2' => 123], $choiceList->getChoices());
        self::assertSame(['0' => 2, '1' => 7, '2' => 10], $choiceList->getOriginalKeys());
        self::assertSame([1 => 'foo', 2 => 123], $choiceList->getChoicesForValues([1 => '0', 2 => '2']));
        self::assertSame([1 => '0', 2 => '2'], $choiceList->getValuesForChoices([1 => 'foo', 2 => 123]));
    }

    /** @test */
    public function create_choice_list_without_value_callback_and_mixed_choices(): void
    {
        $object = new \stdClass();
        $choiceList = new ArrayChoiceList([2 => 'foo', 5 => [7 => '123'], 10 => $object]);

        self::assertSame(['0', '1', '2'], $choiceList->getValues());
        self::assertSame(['0' => 'foo', '1' => '123', '2' => $object], $choiceList->getChoices());
        self::assertSame(['0' => 2, '1' => 7, '2' => 10], $choiceList->getOriginalKeys());
        self::assertSame([1 => 'foo', 2 => $object], $choiceList->getChoicesForValues([1 => '0', 2 => '2']));
        self::assertSame([1 => '0', 2 => '2'], $choiceList->getValuesForChoices([1 => 'foo', 2 => $object]));
    }

    /** @test */
    public function create_choice_list_with_grouped_choices(): void
    {
        $choiceList = new ArrayChoiceList([
            'Group 1' => ['A' => 'a', 'B' => 'b'],
            'Group 2' => ['C' => 'c', 'D' => 'd'],
        ]);

        self::assertSame(['a', 'b', 'c', 'd'], $choiceList->getValues());
        self::assertSame([
            'Group 1' => ['A' => 'a', 'B' => 'b'],
            'Group 2' => ['C' => 'c', 'D' => 'd'],
        ], $choiceList->getStructuredValues());
        self::assertSame(['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'], $choiceList->getChoices());
        self::assertSame(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'], $choiceList->getOriginalKeys());
        self::assertSame([1 => 'a', 2 => 'b'], $choiceList->getChoicesForValues([1 => 'a', 2 => 'b']));
        self::assertSame([1 => 'a', 2 => 'b'], $choiceList->getValuesForChoices([1 => 'a', 2 => 'b']));
    }

    /** @test */
    public function compare_choices_by_identity_by_default(): void
    {
        $callback = static fn ($choice) => $choice->value;

        $obj1 = (object) ['value' => 'value1'];
        $obj2 = (object) ['value' => 'value2'];

        $choiceList = new ArrayChoiceList([$obj1, $obj2], $callback);
        self::assertSame([2 => 'value2'], $choiceList->getValuesForChoices([2 => $obj2]));
        self::assertSame([2 => 'value2'], $choiceList->getValuesForChoices([2 => (object) ['value' => 'value2']]));
    }

    /** @test */
    public function get_choices_for_values_with_containing_null(): void
    {
        $choiceList = new ArrayChoiceList(['Null' => null]);

        self::assertSame([0 => null], $choiceList->getChoicesForValues(['0']));
    }

    /** @test */
    public function get_choices_for_values_with_containing_false_and_null(): void
    {
        $choiceList = new ArrayChoiceList(['False' => false, 'Null' => null]);

        self::assertSame([0 => null], $choiceList->getChoicesForValues(['1']));
        self::assertSame([0 => false], $choiceList->getChoicesForValues(['0']));
    }

    /** @test */
    public function get_choices_for_values_with_containing_empty_string_and_null(): void
    {
        $choiceList = new ArrayChoiceList(['Empty String' => '', 'Null' => null]);

        self::assertSame([0 => ''], $choiceList->getChoicesForValues(['0']));
        self::assertSame([0 => null], $choiceList->getChoicesForValues(['1']));
    }

    /** @test */
    public function get_choices_for_values_with_containing_empty_string_and_booleans(): void
    {
        $choiceList = new ArrayChoiceList(['Empty String' => '', 'True' => true, 'False' => false]);

        self::assertSame([0 => ''], $choiceList->getChoicesForValues(['']));
        self::assertSame([0 => true], $choiceList->getChoicesForValues(['1']));
        self::assertSame([0 => false], $choiceList->getChoicesForValues(['0']));
    }

    /** @test */
    public function get_choices_for_values_with_containing_empty_string_and_floats(): void
    {
        $choiceList = new ArrayChoiceList(['Empty String' => '', '1/3' => 0.3, '1/2' => 0.5]);

        self::assertSame([0 => ''], $choiceList->getChoicesForValues(['']));
        self::assertSame([0 => 0.3], $choiceList->getChoicesForValues(['0.3']));
        self::assertSame([0 => 0.5], $choiceList->getChoicesForValues(['0.5']));
    }
}
