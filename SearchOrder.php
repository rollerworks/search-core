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
 */
final class SearchOrder
{
    /** @var array <string, 'desc'|'asc'> */
    private readonly array $fields;

    private readonly ValuesGroup $valuesGroup;

    /**
     * @param ValuesGroup|array<string, 'desc'|'asc'|'DESC'|'ASC'> $values
     */
    public function __construct(
        ValuesGroup | array $values,
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

        $valuesGroup = new ValuesGroup();
        $fields = [];

        foreach ($values as $fieldName => $direction) {
            if (! OrderField::isOrder($fieldName)) {
                throw new InvalidArgumentException(\sprintf('Field "%s" is not a valid ordering field. Expected either "@%1$s".', $fieldName));
            }

            if (! \is_string($direction)) {
                throw new InvalidArgumentException(\sprintf('Field "%s" direction must be a string.', $fieldName));
            }

            $direction = mb_strtolower($direction);

            if (! \in_array($direction, ['desc', 'asc'], true)) {
                throw new InvalidArgumentException(\sprintf('Invalid direction provided "%s" for field "%s", must be either "asc" or "desc" (case insensitive).', $direction, $fieldName));
            }

            $valuesGroup->addField($fieldName, (new ValuesBag())->addSimpleValue($direction));
            $fields[$fieldName] = $direction;
        }

        $this->fields = $fields;
        $this->valuesGroup = $valuesGroup;
    }

    public function getValuesGroup(): ValuesGroup
    {
        return $this->valuesGroup;
    }

    /**
     * @return array<string, 'desc'|'asc'>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
