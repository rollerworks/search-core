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

namespace Rollerworks\Component\Search;

use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Field\OrderField;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @psalm-type Sorting = array<string, 'asc'|'desc'>
 */
final class SearchOrder
{
    /** @psalm-var Sorting */
    private readonly array $fields;

    /** @psalm-var Sorting */
    private readonly array $prepend;

    /** @psalm-var Sorting */
    private readonly array $append;

    /** @psalm-var Sorting */
    private array $finalSorting;

    private readonly ValuesGroup $valuesGroup;

    /**
     * Creates a new SearchOrder.
     *
     * The $prepend fields always appear first in the sorting order.
     * The $append fields always appear last in the sorting order.
     *
     * Any fields added with $values will always overwrite the values provided
     * in $prepend and $append, while keeping there original position.
     *
     * @param ValuesGroup|array<string, 'desc'|'asc'|'DESC'|'ASC'> $values
     * @param array<string, 'desc'|'asc'|'DESC'|'ASC'>             $prepend
     * @param array<string, 'desc'|'asc'|'DESC'|'ASC'>             $append
     */
    public function __construct(
        ValuesGroup | array $values,
        array $prepend = [],
        array $append = [],
    ) {
        if ($values instanceof ValuesGroup) {
            trigger_deprecation('rollerworks/search', '2.0-BETA14', 'Passing a "%s" to "%s()" is deprecated, pass an associative array fields and there directions instead.', ValuesGroup::class, __METHOD__);

            if ($values->hasGroups()) {
                throw new InvalidArgumentException('A SearchOrder must have a single-level structure. Only fields with single values are accepted.');
            }

            $fields = [];

            foreach ($values->getFields() as $fieldName => $valuesBag) {
                if ($valuesBag->count() !== 1 || ! $valuesBag->hasSimpleValues()) {
                    throw new InvalidArgumentException(\sprintf('Field "%s" must have a single value only.', $fieldName));
                }

                $fields[$fieldName] = current($valuesBag->getSimpleValues());
            }

            $values = $fields;
        }

        $this->prepend = $this->processFields($prepend);
        $this->fields = $this->processFields($values);
        $this->append = $this->processFields($append);
        $this->finalSorting = array_merge($this->prepend, $this->fields);

        // Only append fields that are not already in prepend or fields
        $this->finalSorting += $this->append;

        $valuesGroup = new ValuesGroup();

        foreach ($this->finalSorting as $fieldName => $direction) {
            $valuesGroup->addField($fieldName, (new ValuesBag())->addSimpleValue($direction));
        }
        $this->valuesGroup = $valuesGroup;
    }

    public function getValuesGroup(): ValuesGroup
    {
        return $this->valuesGroup;
    }

    /**
     * @psalm-return Sorting
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @psalm-return Sorting
     */
    public function getAppend(): array
    {
        return $this->append;
    }

    /**
     * @psalm-return Sorting
     */
    public function getPrepend(): array
    {
        return $this->prepend;
    }

    /**
     * Gets the final sorting order.
     *
     * This is the final sorting order, which is the combination fields that are
     * always-sorted fields and fields provided by user-input.
     *
     * @psalm-return Sorting
     */
    public function getSorting(): array
    {
        return $this->finalSorting;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @psalm-return Sorting
     */
    private function processFields(array $values): array
    {
        $fields = [];

        foreach ($values as $fieldName => $direction) {
            if (! OrderField::isOrder($fieldName)) {
                throw new InvalidArgumentException(\sprintf('Field "%s" is not a valid ordering field. Expected either "@%1$s".', $fieldName));
            }

            if (! \is_string($direction)) {
                throw new InvalidArgumentException(\sprintf('Field "%s" direction must be a string, "%s" given.', $fieldName, get_debug_type($direction)));
            }

            $direction = mb_strtolower($direction);

            if (! \in_array($direction, ['desc', 'asc'], true)) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'Invalid direction provided "%s" for field "%s", must be either "asc" or "desc" (case insensitive).',
                        $direction,
                        $fieldName
                    )
                );
            }

            $fields[$fieldName] = $direction;
        }

        return $fields;
    }
}
