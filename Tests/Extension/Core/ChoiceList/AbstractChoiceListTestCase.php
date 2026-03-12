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

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
abstract class AbstractChoiceListTestCase extends TestCase
{
    protected ChoiceList $list;

    /** @var array<string, mixed> */
    protected array $choices;

    /** @var array<string, mixed> */
    protected array $values;

    /** @var array<string, mixed> */
    protected array $structuredValues;

    /** @var array<string, mixed> */
    protected array $keys;

    protected mixed $choice1;
    protected mixed $choice2;
    protected mixed $choice3;
    protected mixed $choice4;
    protected string $value1;
    protected string $value2;
    protected string $value3;
    protected string $value4;
    protected string $key1;
    protected string $key2;
    protected string $key3;
    protected string $key4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->list = $this->createChoiceList();

        $choices = $this->getChoices();

        $this->values = $this->getValues();
        $this->structuredValues = array_combine(array_keys($choices), $this->values);
        $this->choices = array_combine($this->values, $choices);
        $this->keys = array_combine($this->values, array_keys($choices));

        // allow access to the individual entries without relying on their indices
        reset($this->choices);
        reset($this->values);
        reset($this->keys);

        for ($i = 1; $i <= 4; ++$i) {
            $this->{'choice' . $i} = current($this->choices);
            $this->{'value' . $i} = current($this->values);
            $this->{'key' . $i} = current($this->keys);

            next($this->choices);
            next($this->values);
            next($this->keys);
        }
    }

    /** @test */
    public function get_choices(): void
    {
        self::assertSame($this->choices, $this->list->getChoices());
    }

    /** @test */
    public function get_values(): void
    {
        self::assertSame($this->values, $this->list->getValues());
    }

    /** @test */
    public function get_structured_values(): void
    {
        self::assertSame($this->values, $this->list->getStructuredValues());
    }

    /** @test */
    public function get_original_keys(): void
    {
        self::assertSame($this->keys, $this->list->getOriginalKeys());
    }

    /** @test */
    public function get_choices_for_values(): void
    {
        $values = [$this->value1, $this->value2];
        self::assertSame([$this->choice1, $this->choice2], $this->list->getChoicesForValues($values));
    }

    /** @test */
    public function get_choices_for_values_preserves_keys(): void
    {
        $values = [5 => $this->value1, 8 => $this->value2];
        self::assertSame([5 => $this->choice1, 8 => $this->choice2], $this->list->getChoicesForValues($values));
    }

    /** @test */
    public function get_choices_for_values_preserves_order(): void
    {
        $values = [$this->value2, $this->value1];
        self::assertSame([$this->choice2, $this->choice1], $this->list->getChoicesForValues($values));
    }

    /** @test */
    public function get_choices_for_values_ignores_non_existing_values(): void
    {
        $values = [$this->value1, $this->value2, 'foobar'];
        self::assertSame([$this->choice1, $this->choice2], $this->list->getChoicesForValues($values));
    }

    // https://github.com/symfony/symfony/issues/3446

    /** @test */
    public function get_choices_for_values_empty(): void
    {
        self::assertSame([], $this->list->getChoicesForValues([]));
    }

    /** @test */
    public function get_values_for_choices(): void
    {
        $choices = [$this->choice1, $this->choice2];
        self::assertSame([$this->value1, $this->value2], $this->list->getValuesForChoices($choices));
    }

    /** @test */
    public function get_values_for_choices_preserves_keys(): void
    {
        $choices = [5 => $this->choice1, 8 => $this->choice2];
        self::assertSame([5 => $this->value1, 8 => $this->value2], $this->list->getValuesForChoices($choices));
    }

    /** @test */
    public function get_values_for_choices_preserves_order(): void
    {
        $choices = [$this->choice2, $this->choice1];
        self::assertSame([$this->value2, $this->value1], $this->list->getValuesForChoices($choices));
    }

    /** @test */
    public function get_values_for_choices_ignores_non_existing_choices(): void
    {
        $choices = [$this->choice1, $this->choice2, 'foobar'];
        self::assertSame([$this->value1, $this->value2], $this->list->getValuesForChoices($choices));
    }

    /** @test */
    public function get_values_for_choices_empty(): void
    {
        self::assertSame([], $this->list->getValuesForChoices([]));
    }

    /** @test */
    public function get_choices_for_values_with_null(): void
    {
        $values = $this->list->getValuesForChoices([null]);

        self::assertNotEmpty($this->list->getChoicesForValues($values));
    }

    abstract protected function createChoiceList(): ChoiceList;

    abstract protected function getChoices();

    abstract protected function getValues();
}
