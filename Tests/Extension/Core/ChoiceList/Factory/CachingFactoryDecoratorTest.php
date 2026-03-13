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

namespace Rollerworks\Component\Search\Tests\Extension\Core\ChoiceList\Factory;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ArrayChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\Factory\CachingFactoryDecorator;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\Factory\DefaultChoiceListFactory;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\LazyChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceListView;
use Rollerworks\Component\Search\Tests\Extension\Core\ChoiceList\ChoiceListAssertionTrait;
use Rollerworks\Component\Search\Tests\Fixtures\ArrayChoiceLoader;

/**
 * @see Symfony\Component\Form\Tests\ChoiceList\Factory\CachingFactoryDecoratorTest
 *
 * @internal
 */
final class CachingFactoryDecoratorTest extends TestCase
{
    use ChoiceListAssertionTrait;

    private CachingFactoryDecorator $factory;

    protected function setUp(): void
    {
        $this->factory = new CachingFactoryDecorator(new DefaultChoiceListFactory());
    }

    /**
     * @test
     */
    public function create_from_choices_empty(): void
    {
        $list1 = $this->factory->createListFromChoices([]);
        $list2 = $this->factory->createListFromChoices([]);

        self::assertSame($list1, $list2);

        self::assertEqualsArrayChoiceList(new ArrayChoiceList([]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([]), $list2);
    }

    /**
     * @test
     */
    public function create_from_choices_compares_traversable_choices_as_array(): void
    {
        // The top-most traversable is converted to an array
        $choices1 = new \ArrayIterator(['A' => 'a']);
        $choices2 = ['A' => 'a'];

        $list1 = $this->factory->createListFromChoices($choices1);
        $list2 = $this->factory->createListFromChoices($choices2);

        self::assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['A' => 'a']), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList(['A' => 'a']), $list2);
    }

    /**
     * @dataProvider provideSameChoices
     *
     * @test
     */
    public function create_from_choices_same_choices($choice1, $choice2): void
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        self::assertSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice1]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice2]), $list2);
    }

    public static function provideSameChoices(): iterable
    {
        $object = (object) ['foo' => 'bar'];

        return [
            [0, 0],
            ['a', 'a'],
            // https://github.com/symfony/symfony/issues/10409
            [\chr(181) . 'meter', \chr(181) . 'meter'], // UTF-8
            [$object, $object],
        ];
    }

    /**
     * @dataProvider provideDistinguishedChoices
     *
     * @test
     */
    public function create_from_choices_different_choices($choice1, $choice2): void
    {
        $list1 = $this->factory->createListFromChoices([$choice1]);
        $list2 = $this->factory->createListFromChoices([$choice2]);

        self::assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice1]), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList([$choice2]), $list2);
    }

    public static function provideDistinguishedChoices(): iterable
    {
        return [
            [0, false],
            [0, null],
            [0, '0'],
            [0, ''],
            [1, true],
            [1, '1'],
            [1, 'a'],
            ['', false],
            ['', null],
            [false, null],
            // Same properties, but not identical
            [(object) ['foo' => 'bar'], (object) ['foo' => 'bar']],
        ];
    }

    /**
     * @test
     */
    public function create_from_choices_same_value_closure(): void
    {
        $choices = [1];
        $closure = static function (): void {};

        $list1 = $this->factory->createListFromChoices($choices, $closure);
        $list2 = $this->factory->createListFromChoices($choices, $closure);

        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure), $list2);
    }

    /**
     * @test
     */
    public function create_from_choices_different_value_closure(): void
    {
        $choices = [1];
        $closure1 = static function (): void {};
        $closure2 = static function (): void {};
        $list1 = $this->factory->createListFromChoices($choices, $closure1);
        $list2 = $this->factory->createListFromChoices($choices, $closure2);

        self::assertNotSame($list1, $list2);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure1), $list1);
        self::assertEqualsArrayChoiceList(new ArrayChoiceList($choices, $closure2), $list2);
    }

    /**
     * @test
     */
    public function create_from_loader_same_loader(): void
    {
        $loader = new ArrayChoiceLoader();
        $list1 = $this->factory->createListFromLoader($loader);
        $list2 = $this->factory->createListFromLoader($loader);

        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader), $list2);
    }

    /**
     * @test
     */
    public function create_from_loader_different_loader(): void
    {
        self::assertNotSame($this->factory->createListFromLoader(new ArrayChoiceLoader()), $this->factory->createListFromLoader(new ArrayChoiceLoader()));
    }

    /**
     * @test
     */
    public function create_from_loader_same_value_closure(): void
    {
        $loader = new ArrayChoiceLoader();
        $closure = static function (): void {};
        $list1 = $this->factory->createListFromLoader($loader, $closure);
        $list2 = $this->factory->createListFromLoader($loader, $closure);

        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader, $closure), $list1);
        self::assertEqualsLazyChoiceList(new LazyChoiceList($loader, $closure), $list2);
    }

    /**
     * @test
     */
    public function create_view_different_preferred_choices(): void
    {
        $preferred1 = ['a'];
        $preferred2 = ['b'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred1);
        $view2 = $this->factory->createView($list, $preferred2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_preferred_choices(): void
    {
        $preferred = ['a'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_preferred_choices_closure(): void
    {
        $preferred1 = static function (): void {};
        $preferred2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred1);
        $view2 = $this->factory->createView($list, $preferred2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_label_closure(): void
    {
        $labels = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels);
        $view2 = $this->factory->createView($list, null, $labels);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_label_closure(): void
    {
        $labels1 = static function (): void {};
        $labels2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, $labels1);
        $view2 = $this->factory->createView($list, null, $labels2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_index_closure(): void
    {
        $index = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index);
        $view2 = $this->factory->createView($list, null, null, $index);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_preferred_choices_closure(): void
    {
        $preferred = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, $preferred);
        $view2 = $this->factory->createView($list, $preferred);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_index_closure(): void
    {
        $index1 = static function (): void {};
        $index2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, $index1);
        $view2 = $this->factory->createView($list, null, null, $index2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_group_by_closure(): void
    {
        $groupBy = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_group_by_closure(): void
    {
        $groupBy1 = static function (): void {};
        $groupBy2 = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, $groupBy1);
        $view2 = $this->factory->createView($list, null, null, null, $groupBy2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_attributes(): void
    {
        $attr = ['class' => 'foobar'];
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_attributes(): void
    {
        $attr1 = ['class' => 'foobar1'];
        $attr2 = ['class' => 'foobar2'];
        $list = new ArrayChoiceList([]);

        $view1 = $this->factory->createView($list, null, null, null, null, $attr1);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_same_attributes_closure(): void
    {
        $attr = static function (): void {};
        $list = new ArrayChoiceList([]);
        $view1 = $this->factory->createView($list, null, null, null, null, $attr);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr);

        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    /**
     * @test
     */
    public function create_view_different_attributes_closure(): void
    {
        $attr1 = static function (): void {};
        $attr2 = static function (): void {};
        $list = new ArrayChoiceList([]);

        $view1 = $this->factory->createView($list, null, null, null, null, $attr1);
        $view2 = $this->factory->createView($list, null, null, null, null, $attr2);

        self::assertNotSame($view1, $view2);
        self::assertEquals(new ChoiceListView(), $view1);
        self::assertEquals(new ChoiceListView(), $view2);
    }

    public static function provideSameKeyChoices(): iterable
    {
        // Only test types here that can be used as array keys
        return [
            [0, 0],
            [0, '0'],
            ['a', 'a'],
            [\chr(181) . 'meter', \chr(181) . 'meter'],
        ];
    }

    public static function provideDistinguishedKeyChoices(): iterable
    {
        // Only test types here that can be used as array keys
        return [
            [0, ''],
            [1, 'a'],
            ['', 'a'],
        ];
    }
}
