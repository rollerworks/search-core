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
use Rollerworks\Component\Search\Exception\InvalidConfigurationException;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Value\RequiresComparatorValueHolder;
use Rollerworks\Component\Search\Value\ValueHolder;
use Rollerworks\Component\Search\ValueComparator;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SearchField implements FieldConfig
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<class-string<ValueHolder>, bool> */
    private array $supportedValueTypes = [];

    private ?ValueComparator $valueComparator = null;
    private ?DataTransformer $viewTransformer = null;
    private ?DataTransformer $normTransformer = null;
    private bool $locked = false;

    /**
     * @param array<string, mixed> $options
     *
     * @throws \InvalidArgumentException When the name contains illegal characters
     */
    public function __construct(
        private readonly string $name,
        private readonly ResolvedFieldType $type,
        private readonly array $options = [],
    ) {
        if (! preg_match('/^_?[a-zA-Z][a-zA-Z0-9_\-]*$/D', $name)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'The name "%s" contains illegal characters. Name must start with a letter or underscore ' .
                    'and only contain letters, digits, numbers, underscores ("_") and hyphens ("-").',
                    $name
                )
            );
        }
    }

    public function supportValueType(string $type): bool
    {
        return $this->supportedValueTypes[$type] ?? false;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setValueTypeSupport(string $type, bool $enabled)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

        $this->supportedValueTypes[$type] = $enabled;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ResolvedFieldType
    {
        return $this->type;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setValueComparator(ValueComparator $comparator)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

        $this->valueComparator = $comparator;

        return $this;
    }

    public function getValueComparator(): ?ValueComparator
    {
        return $this->valueComparator;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setViewTransformer(?DataTransformer $viewTransformer = null)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

        $this->viewTransformer = $viewTransformer;

        return $this;
    }

    public function getViewTransformer(): ?DataTransformer
    {
        return $this->viewTransformer;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setNormTransformer(?DataTransformer $viewTransformer = null)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

        $this->normTransformer = $viewTransformer;

        return $this;
    }

    public function getNormTransformer(): ?DataTransformer
    {
        return $this->normTransformer;
    }

    /**
     * Finalize the configuration and mark config as locked.
     *
     * After calling this method, setter methods can be no longer called.
     *
     * @throws InvalidConfigurationException when a supported value-type requires
     *                                       a value comparator but none is set
     */
    public function finalizeConfig(): void
    {
        if ($this->locked) {
            return;
        }

        if ($this->valueComparator === null) {
            foreach ($this->supportedValueTypes as $type => $supported) {
                if ($supported && isset(class_implements($type)[RequiresComparatorValueHolder::class])) {
                    throw new InvalidConfigurationException(
                        \sprintf(
                            'Supported value-type "%s" requires a value comparator but none is set for field "%s" with type "%s".',
                            $type,
                            $this->getName(),
                            \get_class($this->getType()->getInnerType())
                        )
                    );
                }
            }
        }

        $this->locked = true;
    }

    public function isConfigLocked(): bool
    {
        return $this->locked;
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

    /**
     * @throws BadMethodCallException when the configuration is not locked
     */
    public function createView(FieldSetView $fieldSet): SearchFieldView
    {
        if (! $this->locked) {
            throw new BadMethodCallException(
                'Unable to create SearchFieldView when configuration is not locked.'
            );
        }

        $view = new SearchFieldView($fieldSet);

        $this->type->buildFieldView($view, $this, $this->options);

        return $view;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setAttribute(string $name, mixed $value)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @throws BadMethodCallException when the setter is called after the config is locked
     */
    public function setAttributes(array $attributes)
    {
        if ($this->locked) {
            throw new BadMethodCallException(
                'SearchField setter methods cannot be accessed anymore once the data is locked.'
            );
        }

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
}
