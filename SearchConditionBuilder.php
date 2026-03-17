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

use Rollerworks\Component\Search\Exception\BadMethodCallException;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Field\OrderField;
use Rollerworks\Component\Search\Input\AbstractInput;
use Rollerworks\Component\Search\Value\ValuesGroup;

final class SearchConditionBuilder
{
    /** @var array<string, 'ASC'|'DESC'> */
    private ?array $order = [];

    private ValuesGroup $valuesGroup;
    private ?self $primaryCondition = null;

    /**
     * @param string $logical eg. one of the following ValuesGroup class constants value:
     *                        GROUP_LOGICAL_OR or GROUP_LOGICAL_AND
     */
    public static function create(FieldSet $fieldSet, string $logical = ValuesGroup::GROUP_LOGICAL_AND): self
    {
        return new self($logical, $fieldSet);
    }

    private function __construct(
        string $logical,
        private readonly FieldSet $fieldSet,
        private readonly ?self $parent = null,
    ) {
        $this->valuesGroup = new ValuesGroup($logical);
    }

    /**
     * @param ValuesGroup::GROUP_LOGICAL_* $logical
     *
     * @return $this
     */
    public function setGroupLogical(string $logical): self
    {
        $this->valuesGroup->setGroupLogical($logical);

        return $this;
    }

    /**
     * Add the result-ordering of the (primary) search condition
     * and returns the SearchConditionBuilder.
     *
     * Note: This method can only be used at the root-level of the condition,
     * or at the root-level of a primary condition. Unlike the other methods
     * this doesn't require calling end() to close the condition level.
     *
     * ```
     * ->order('@name', 'ASC')
     * ->order('@id', 'DESC')
     * ->field('name')
     *   ->addSimpleValue('my value')
     *   ->addSimpleValue('my value 2')
     * ->end() // return to the ValuesGroupBuilder or SearchConditionBuilder
     * ```
     *
     * Or (with primary condition)
     *
     * ```
     * ->order('@name', 'ASC')
     * ->primaryCondition()
     *     ->order('@id', 'DESC')
     * ->end() // Returns to the main condition
     * ```
     *
     * @param string                         $name      The field-name (must be a valid known ordering field like "@id")
     * @param 'asc'|'desc'|'ASC'|'DESC'|null $direction Use null to remove the ordering for the field
     *
     * @return $this
     */
    public function order(string $name, ?string $direction = 'ASC'): self
    {
        if ($this->parent && $this->parent->primaryCondition !== $this) {
            throw new BadMethodCallException('Cannot add ordering at nested levels.');
        }

        if (! OrderField::isOrder($name)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" is not a valid ordering field. Expected either "@%1$s".', $name));
        }

        $this->fieldSet->get($name);

        if ($direction === null) {
            unset($this->order[$name]);

            return $this;
        }

        $direction = mb_strtoupper($direction);

        if (! \in_array($direction, ['DESC', 'ASC'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid direction provided "%s" for field "%s", must be either "ASC" OR "DESC" (case insensitive).', $direction, $name));
        }

        $this->order[$name] = $direction;

        return $this;
    }

    /**
     * Clears all the field sorting-orders (if any).
     *
     * @return $this
     */
    public function clearOrder(): self
    {
        $this->order = [];

        return $this;
    }

    /**
     * Create a new ValuesGroup level and returns a SearchConditionBuilder instance
     * for for building the nested group structure.
     *
     * Afterwards the group can be expended with fields or additional subgroups:
     *
     * ```
     * ->group()
     *     ->field('name')
     *         ->...
     *     ->end() // return to the ValuesGroup.
     * ->end() // return to the parent ValuesGroup level
     * ```
     *
     * Note: Groups cannot be altered or removed with the builder after creation.
     *
     * @param string $logical eg. one of the following ValuesGroup class constants value:
     *                        GROUP_LOGICAL_OR or GROUP_LOGICAL_AND
     */
    public function group(string $logical = ValuesGroup::GROUP_LOGICAL_AND): self
    {
        $builder = new self($logical, $this->fieldSet, $this);
        $this->valuesGroup->addGroup($builder->valuesGroup);

        return $builder;
    }

    /**
     * Add/expend a field's ValuesBag on 'this' ValuesGroup and returns
     * a ValuesBagBuilder for adding values to the field.
     *
     * Note: Values must be in the model format, they are not transformed!
     *
     * The ValuesBagBuilder is subset of ValuesBag, which provides a developer
     * friendly interface to construct a ValuesBag structure for the field.
     *
     * ```
     * ->field('name')
     *   ->addSimpleValue('my value')
     *   ->addSimpleValue('my value 2')
     * ->end() // return to the ValuesGroup level
     * ```
     */
    public function field(string $name): ValuesBagBuilder
    {
        if (OrderField::isOrder($name)) {
            throw new InvalidArgumentException(\sprintf('Unable to configure ordering of "%s" with field(), use the order() method instead.', $name));
        }

        if ($this->valuesGroup->hasField($name)) {
            /** @var ValuesBagBuilder $valuesBag */
            $valuesBag = $this->valuesGroup->getField($name);
        } else {
            $this->fieldSet->get($name);

            $valuesBag = new ValuesBagBuilder($this);
            $this->valuesGroup->addField($name, $valuesBag);
        }

        return $valuesBag;
    }

    /**
     * Add/overwrites a field's ValuesBag on this ValuesGroup and returns
     * a ValuesBagBuilder for adding values to the field.
     *
     * Note: Values must be in the model format, they are not transformed!
     *
     * The ValuesBagBuilder is subset of ValuesBag, which provides a developer
     * friendly interface to construct a ValuesBag structure for the field.
     *
     * ```
     * ->overwriteField('name')
     *   ->addSimpleValue('my value')
     *   ->addSimpleValue('my value 2')
     * ->end() // return to the ValuesGroup level
     * ```
     */
    public function overwriteField(string $name): ValuesBagBuilder
    {
        if (OrderField::isOrder($name)) {
            throw new InvalidArgumentException(\sprintf('Unable to configure ordering of "%s" with overwriteField(), use the order() method instead. Call clearOrder() if you need to remove previously set orderings.', $name));
        }

        $this->fieldSet->get($name);

        $valuesBag = new ValuesBagBuilder($this);
        $this->valuesGroup->addField($name, $valuesBag);

        return $valuesBag;
    }

    /**
     * Close the condition building level (field or group) and return
     * to the previous builder level.
     */
    public function end(): self
    {
        return $this->parent ?? $this;
    }

    /**
     * Build a SearchPrimaryCondition structure.
     *
     * Note: This will overwrite any existing primary-condition of this builder.
     *
     * @return self A SearchConditionBuilder for the primary-condition structure
     *
     * @throws BadMethodCallException when called at a nested level
     */
    public function primaryCondition(): self
    {
        if ($this->parent) {
            throw new BadMethodCallException('Cannot add primaryCondition at nested level.');
        }

        $builder = new self(ValuesGroup::GROUP_LOGICAL_AND, $this->fieldSet, $this);
        $this->primaryCondition = $builder;

        return $builder;
    }

    /**
     * Build and returns the SearchCondition object using the groups and fields.
     *
     * Tip: This method can be called at any level, explicitly calling end() is not required.
     */
    public function getSearchCondition(): SearchCondition
    {
        if ($this->parent) {
            return $this->parent->getSearchCondition();
        }

        // This the root of the condition so now traverse back up the hierarchy.
        // We need to re-create the condition using actual objects.
        $rootValuesGroup = new ValuesGroup($this->valuesGroup->getGroupLogical());
        $this->normalizeValueGroup($this->valuesGroup, $rootValuesGroup);

        $searchCondition = new SearchCondition($this->fieldSet, $rootValuesGroup);

        if ($this->order) {
            $searchCondition->setOrder(new SearchOrder($this->order));
        }

        $searchCondition->setPrimaryCondition($this->getPrimaryCondition());

        AbstractInput::finalizeOrdering($searchCondition);

        return $searchCondition;
    }

    /**
     * Normalize the SearchConditionBuilder structure to a ValuesGroup structure
     * that can be used by the SearchCondition.
     *
     * Otherwise the final SearchCondition would contain a SearchConditionBuilder instance.
     */
    private function normalizeValueGroup(ValuesGroup $currentValuesGroup, ValuesGroup $rootValuesGroup): void
    {
        foreach ($currentValuesGroup->getGroups() as $group) {
            $subGroup = new ValuesGroup($group->getGroupLogical());
            $this->normalizeValueGroup($group, $subGroup);

            $rootValuesGroup->addGroup($subGroup);
        }

        foreach ($currentValuesGroup->getFields() as $name => $values) {
            if ($values instanceof ValuesBagBuilder) {
                $values = $values->toValuesBag();
            }

            $rootValuesGroup->addField($name, $values);
        }
    }

    /**
     * Gets the resolved SearchPrimaryCondition (if any).
     *
     * This method can be used as an alternative to getSearchCondition()
     * if you (only) need to get the PrimaryCondition.
     */
    public function getPrimaryCondition(): ?SearchPrimaryCondition
    {
        if (! $this->primaryCondition) {
            return null;
        }

        $rootValuesGroup = new ValuesGroup($this->primaryCondition->valuesGroup->getGroupLogical());
        $this->normalizeValueGroup($this->primaryCondition->valuesGroup, $rootValuesGroup);

        $primaryCondition = new SearchPrimaryCondition($rootValuesGroup);

        if ($this->primaryCondition->order) {
            $primaryCondition->setOrder(new SearchOrder($this->primaryCondition->order));
        }

        return $primaryCondition;
    }

    public function __serialize(): array
    {
        throw new \LogicException('Unable serialize a SearchConditionBuilder. Call getSearchCondition() and serialize the SearchCondition itself.');
    }
}
