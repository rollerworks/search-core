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

namespace Rollerworks\Component\Search\Value;

/**
 * A ValuesBag holds all the values per type.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class ValuesBag implements \Countable
{
    private int $valuesCount = 0;

    /** @var array<int, mixed> */
    private array $simpleValues = [];

    /** @var array<int, mixed> */
    private array $simpleExcludedValues = [];

    /** @var array<class-string<ValueHolder>, ValueHolder[]> */
    private array $values = [];

    /**
     * @param class-string<ValueHolder>|'simpleValues'|'simpleValue'|'simpleExcludedValue'|'simpleExcludedValues'|null $type
     */
    public function count(?string $type = null): int
    {
        if ($type === null) {
            return $this->valuesCount;
        }

        switch ($type) {
            case 'simpleValues':
            case 'simpleValue':
                return \count($this->simpleValues);

            case 'simpleExcludedValues':
            case 'simpleExcludedValue':
                return \count($this->simpleExcludedValues);

            default:
                return \count($this->values[$type] ?? []);
        }
    }

    /**
     * @return array{simpleValues: array<int, mixed>, simpleExcludedValues: array<int, mixed>, values: array<class-string<ValueHolder>, ValueHolder[]>, valuesCount: int}
     */
    public function __serialize(): array
    {
        return [
            'simpleValues' => $this->simpleValues,
            'simpleExcludedValues' => $this->simpleExcludedValues,
            'values' => $this->values,
            'valuesCount' => $this->valuesCount,
        ];
    }

    /**
     * @param array{simpleValues: array<int, mixed>, simpleExcludedValues: array<int, mixed>, values: array<class-string<ValueHolder>, ValueHolder[]>, valuesCount: int} $data
     */
    public function __unserialize(array $data): void
    {
        [
            'simpleValues' => $this->simpleValues,
            'simpleExcludedValues' => $this->simpleExcludedValues,
            'values' => $this->values,
            'valuesCount' => $this->valuesCount,
        ] = $data;
    }

    /**
     * @return mixed[]
     */
    public function getSimpleValues(): array
    {
        return $this->simpleValues;
    }

    /**
     * @return $this
     */
    public function addSimpleValue(mixed $value): static
    {
        $this->simpleValues[] = $value;
        ++$this->valuesCount;

        return $this;
    }

    public function hasSimpleValues(): bool
    {
        return \count($this->simpleValues) > 0;
    }

    /**
     * @return $this
     */
    public function removeSimpleValue(int $index): static
    {
        if (isset($this->simpleValues[$index])) {
            unset($this->simpleValues[$index]);

            --$this->valuesCount;
        }

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getExcludedSimpleValues(): array
    {
        return $this->simpleExcludedValues;
    }

    /**
     * @return $this
     */
    public function addExcludedSimpleValue(mixed $value): static
    {
        $this->simpleExcludedValues[] = $value;
        ++$this->valuesCount;

        return $this;
    }

    public function hasExcludedSimpleValues(): bool
    {
        return \count($this->simpleExcludedValues) > 0;
    }

    /**
     * Remove a simple excluded value by index.
     *
     * @return $this
     */
    public function removeExcludedSimpleValue(int $index): static
    {
        if (isset($this->simpleExcludedValues[$index])) {
            unset($this->simpleExcludedValues[$index]);

            --$this->valuesCount;
        }

        return $this;
    }

    /**
     * Get all values from a specific type.
     *
     * @template T of ValueHolder
     *
     * @param class-string<T> $type
     *
     * @return T[]
     */
    public function get(string $type): array
    {
        return $this->values[$type] ?? [];
    }

    /**
     * Check if the bag has values for a specific type.
     *
     * @param class-string<ValueHolder> $type
     */
    public function has(string $type): bool
    {
        return isset($this->values[$type]) && \count($this->values[$type]) > 0;
    }

    /**
     * Remove a value by type and index.
     *
     * @param class-string<ValueHolder> $type
     *
     * @return $this
     */
    public function remove(string $type, int $index): static
    {
        if (isset($this->values[$type][$index])) {
            unset($this->values[$type][$index]);

            --$this->valuesCount;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function add(ValueHolder $value): static
    {
        $this->values[$value::class][] = $value;
        ++$this->valuesCount;

        return $this;
    }

    /**
     * @return array<string, ValueHolder[]>
     */
    public function all(): array
    {
        return $this->values;
    }
}
