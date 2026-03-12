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

use Rollerworks\Component\Search\DataTransformer;
use Rollerworks\Component\Search\Exception\BadMethodCallException;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\ValueComparator;

/**
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 */
final class OrderField implements FieldConfig
{
    private ?DataTransformer $normTransformer = null;
    private ?DataTransformer $viewTransformer = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $options
     *
     * @throws \InvalidArgumentException When the name is invalid
     */
    public function __construct(
        private readonly string $name,
        private readonly ResolvedFieldType $type,
        private readonly array $options = [],
    ) {
        if (! preg_match('/^@_?[a-zA-Z][a-zA-Z0-9_\-]*$/D', $name)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'The name "%s" contains illegal characters. Name must start with @' .
                    'and only contain letters, digits, numbers, underscores ("_") and hyphens ("-").',
                    $name
                )
            );
        }
    }

    public static function isOrder(string $name): bool
    {
        return $name[0] === '@';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ResolvedFieldType
    {
        return $this->type;
    }

    public function supportValueType(string $type): bool
    {
        return false;
    }

    public function setValueTypeSupport(string $type, bool $enabled): never
    {
        throw new BadMethodCallException(
            'OrderField does not support supporting custom value types'
        );
    }

    public function setValueComparator(ValueComparator $comparator): never
    {
        throw new BadMethodCallException(
            'OrderField does not support supporting custom value comparator'
        );
    }

    public function getValueComparator(): ?ValueComparator
    {
        return null;
    }

    public function setViewTransformer(?DataTransformer $viewTransformer = null): void
    {
        $this->viewTransformer = $viewTransformer;
    }

    public function getViewTransformer(): ?DataTransformer
    {
        return $this->viewTransformer;
    }

    public function setNormTransformer(?DataTransformer $viewTransformer = null): void
    {
        $this->normTransformer = $viewTransformer;
    }

    public function getNormTransformer(): ?DataTransformer
    {
        return $this->normTransformer;
    }

    public function isConfigLocked(): bool
    {
        return false;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    public function getOption(string $name, $default = null)
    {
        if (\array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return $default;
    }

    public function createView(FieldSetView $fieldSet): SearchFieldView
    {
        $view = new SearchFieldView($fieldSet);

        $this->type->buildFieldView($view, $this, $this->options);

        return $view;
    }

    public function setAttribute(string $name, mixed $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function finalizeConfig(): void
    {
        // Noop
    }
}
