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

namespace Rollerworks\Component\Search\Field;

use Rollerworks\Component\Search\Exception\UnexpectedTypeException;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\PatternMatch;
use Rollerworks\Component\Search\Value\Range;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class GenericResolvedFieldType implements ResolvedFieldType
{
    private ?OptionsResolver $optionsResolver = null;

    /**
     * @param FieldTypeExtension[] $typeExtensions
     *
     * @throws UnexpectedTypeException When at least one of the given extensions is not an FieldTypeExtension
     */
    public function __construct(
        private readonly FieldType $innerType,
        private readonly array $typeExtensions = [],
        private readonly ?ResolvedFieldType $parent = null,
    ) {
        foreach ($typeExtensions as $extension) {
            if (! $extension instanceof FieldTypeExtension) {
                throw new UnexpectedTypeException($extension, FieldTypeExtension::class);
            }
        }
    }

    public function getParent(): ?ResolvedFieldType
    {
        return $this->parent;
    }

    public function getInnerType(): FieldType
    {
        return $this->innerType;
    }

    public function getTypeExtensions(): array
    {
        return $this->typeExtensions;
    }

    public function createField(string $name, array $options = []): FieldConfig
    {
        try {
            $options = $this->getOptionsResolver()->resolve($options);
        } catch (ExceptionInterface $e) {
            throw new $e(\sprintf('An error has occurred resolving the options of the field "%s" with type "%s": ', $name, get_debug_type($this->getInnerType())) . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->newField($name, $options);
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        if ($this->parent !== null) {
            $this->parent->buildType($config, $options);
        }

        $this->innerType->buildType($config, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildType($config, $options);
        }
    }

    public function createFieldView(FieldConfig $config, FieldSetView $view): SearchFieldView
    {
        $viewObj = $this->newView($view);
        $viewObj->vars = array_merge($view->vars, [
            'name' => $config->getName(),
            'accept_ranges' => $config->supportValueType(Range::class),
            'accept_compares' => $config->supportValueType(Compare::class),
            'accept_pattern_matchers' => $config->supportValueType(PatternMatch::class),
        ]);

        return $viewObj;
    }

    public function buildFieldView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        if ($this->parent !== null) {
            $this->parent->buildFieldView($view, $config, $options);
        }

        $this->innerType->buildView($view, $config, $options);

        foreach ($this->typeExtensions as $extension) {
            $extension->buildView($config, $view);
        }
    }

    public function getBlockPrefix(): string
    {
        return $this->innerType->getBlockPrefix();
    }

    public function getOptionsResolver(): OptionsResolver
    {
        if ($this->optionsResolver !== null) {
            return $this->optionsResolver;
        }

        $this->optionsResolver = $this->parent !== null ? clone $this->parent->getOptionsResolver() : new OptionsResolver();
        $this->innerType->configureOptions($this->optionsResolver);

        foreach ($this->typeExtensions as $extension) {
            $extension->configureOptions($this->optionsResolver);
        }

        return $this->optionsResolver;
    }

    /**
     * Creates a new SearchField instance.
     *
     * Override this method if you want to customize the field class.
     */
    protected function newField($name, array $options): FieldConfig
    {
        return OrderField::isOrder($name) ? new OrderField($name, $this, $options) : new SearchField($name, $this, $options);
    }

    /**
     * Creates a new SearchFieldView instance.
     *
     * Override this method if you want to customize the view class.
     */
    protected function newView(FieldSetView $view): SearchFieldView
    {
        return new SearchFieldView($view);
    }
}
