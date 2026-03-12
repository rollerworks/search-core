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

namespace Rollerworks\Component\Search\Tests\Field;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\Type\SearchFieldType;
use Rollerworks\Component\Search\Field\AbstractFieldType;
use Rollerworks\Component\Search\Field\AbstractFieldTypeExtension;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\FieldType;
use Rollerworks\Component\Search\Field\GenericResolvedFieldType;
use Rollerworks\Component\Search\Field\SearchFieldView;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\ConfigurableColumnType;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\FBooType;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\Foo;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\Foo1Bar2Type;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\FooBarHTMLType;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\FooType;
use Rollerworks\Component\Search\Tests\Fixtures\Extension\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @internal
 *
 * @see \Symfony\Component\Form\Tests\ResolvedFormTypeTest
 */
final class ResolvedFieldTypeTest extends TestCase
{
    /** @var array<string, mixed[]> */
    private array $calls;

    private UsageTrackingParentFieldType $parentType;
    private UsageTrackingFieldType $type;
    private UsageTrackingFieldTypeExtension $extension1;
    private UsageTrackingFieldTypeExtension $extension2;
    private GenericResolvedFieldType $parentResolvedType;
    private GenericResolvedFieldType $resolvedType;

    protected function setUp(): void
    {
        $this->calls = [];
        $this->parentType = new UsageTrackingParentFieldType($this->calls);
        $this->type = new UsageTrackingFieldType($this->calls);
        $this->extension1 = new UsageTrackingFieldTypeExtension($this->calls, ['c' => 'c_default']);
        $this->extension2 = new UsageTrackingFieldTypeExtension($this->calls, ['d' => 'd_default']);
        $this->parentResolvedType = new GenericResolvedFieldType($this->parentType);
        $this->resolvedType = new GenericResolvedFieldType(
            $this->type,
            [$this->extension1, $this->extension2],
            $this->parentResolvedType
        );
    }

    /** @test */
    public function its_resolved_options_in_correct_order(): void
    {
        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];
        $resolvedOptions = ['a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default', 'foo' => 'bar'];

        $resolver = $this->resolvedType->getOptionsResolver();

        $this->assertStructureCalled('configureOptions');

        $this->assertEquals($resolvedOptions, $resolver->resolve($givenOptions));
    }

    /** @test */
    public function it_creates_a_field(): void
    {
        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];
        $resolvedOptions = ['b' => 'b_default', 'd' => 'd_default', 'a' => 'a_custom', 'c' => 'c_custom', 'foo' => 'bar'];

        $field = $this->resolvedType->createField('name', $givenOptions);

        $this->assertStructureCalled('configureOptions');

        self::assertSame('name', $field->getName());
        self::assertSame($this->resolvedType, $field->getType());
        self::assertSame($resolvedOptions, $field->getOptions());
    }

    /** @test */
    public function it_builds_the_type(): void
    {
        $options = ['a' => 'Foo', 'b' => 'Bar'];
        $field = $this->createFieldMock();

        $this->resolvedType->buildType($field, $options);

        $this->assertStructureCalled('buildType');
    }

    /** @test */
    public function create_view(): void
    {
        $rootView = new FieldSetView();
        $rootView->vars['fieldset'] = 'users';

        $field = $this->createFieldMock();
        $view = $this->resolvedType->createFieldView($field, $rootView);

        self::assertEquals('name', $view->vars['name']);
        self::assertEquals('users', $view->vars['fieldset']);
        self::assertFalse($view->vars['accept_ranges']);
    }

    /** @test */
    public function get_block_prefix(): void
    {
        $resolvedType = new GenericResolvedFieldType($this->type);
        self::assertSame('usage_tracking_field', $resolvedType->getBlockPrefix());
    }

    /** @test */
    public function build_view(): void
    {
        $options = ['a' => '1', 'b' => '2'];
        $field = $this->createFieldMock();
        $view = $this->createSearchFieldViewMock();

        $this->resolvedType->buildFieldView($view, $field, $options);

        $this->assertStructureCalled('buildView');
    }

    /** @test */
    public function it_gets_block_prefix(): void
    {
        $resolvedType = new GenericResolvedFieldType(new ConfigurableColumnType());

        self::assertSame('configurable_form_prefix', $resolvedType->getBlockPrefix());
    }

    /**
     * @test
     * @dataProvider provideTypeClassBlockPrefixTuples
     *
     * @param class-string<FieldType> $typeClass
     */
    public function it_gets_block_prefix_defaults_to_fqcn_if_no_name(string $typeClass, string $blockPrefix): void
    {
        $resolvedType = new GenericResolvedFieldType(new $typeClass());

        self::assertSame($blockPrefix, $resolvedType->getBlockPrefix());
    }

    /** @return iterable<array{0: class-string, 1: string}> */
    public static function provideTypeClassBlockPrefixTuples(): iterable
    {
        yield [FooType::class, 'foo'];
        yield [Foo::class, 'foo'];
        yield [Type::class, 'type'];
        yield [FooBarHTMLType::class, 'foo_bar_html'];
        yield [Foo1Bar2Type::class, 'foo1_bar2'];
        yield [FBooType::class, 'f_boo'];
    }

    private function createFieldMock(string $name = 'name'): FieldConfig
    {
        $mock = $this->getMockBuilder(FieldConfig::class)->getMock();
        $mock->expects(self::any())->method('getName')->willReturn($name);
        $mock->expects(self::any())->method('getType')->willReturn($this->resolvedType);
        $mock->expects(self::any())->method('getOptions')->willReturn([]);

        return $mock;
    }

    private function createSearchFieldViewMock(): SearchFieldView
    {
        return $this->createMock(SearchFieldView::class);
    }

    private function assertStructureCalled(string $name): void
    {
        if (! isset($this->calls[$name])) {
            $this->fail(\sprintf('No call found with name "%s", found: "%s"', $name, implode('", "', array_keys($this->calls))));
        }

        self::assertSame([$this->parentType, $this->type, $this->extension1, $this->extension2], $this->calls[$name]);
    }
}

/** @internal */
final class UsageTrackingFieldType extends AbstractFieldType
{
    use FieldUsageTrackingTrait;

    public function getParent(): string
    {
        return UsageTrackingParentFieldType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->calls['configureOptions'][] = $this;

        $resolver->setDefault('b', 'b_default');
        $resolver->setDefined('label');
        $resolver->setRequired('foo');
    }
}

/** @internal */
final class UsageTrackingParentFieldType extends AbstractFieldType
{
    use FieldUsageTrackingTrait;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->calls['configureOptions'][] = $this;

        $resolver->setDefault('a', 'a_default');
    }
}

/** @internal */
final class UsageTrackingFieldTypeExtension extends AbstractFieldTypeExtension
{
    use ExtensionUsageTrackingTrait;

    /**
     * @param mixed[]              $calls
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        array &$calls,
        private array $defaultOptions,
    ) {
        $this->calls = &$calls;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->calls['configureOptions'][] = $this;

        $resolver->setDefaults($this->defaultOptions);
    }

    public function getExtendedType(): string
    {
        return SearchFieldType::class;
    }
}

/** @internal */
trait FieldUsageTrackingTrait
{
    /** @var array<string, array<int, object>> */
    private array $calls;

    /** @param mixed[] $calls */
    public function __construct(array &$calls)
    {
        $this->calls = &$calls;
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        $this->calls['buildType'][] = $this;
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        $this->calls['buildView'][] = $this;
    }
}

/** @internal */
trait ExtensionUsageTrackingTrait
{
    /** @var array<string, array<int, object>> */
    private array $calls;

    public function buildType(FieldConfig $builder, array $options): void
    {
        $this->calls['buildType'][] = $this;
    }

    public function buildView(FieldConfig $config, SearchFieldView $view): void
    {
        $this->calls['buildView'][] = $this;
    }
}
