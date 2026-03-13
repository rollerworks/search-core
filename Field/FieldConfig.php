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
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Value\ValueHolder;
use Rollerworks\Component\Search\ValueComparator;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @method void finalizeConfig()
 */
interface FieldConfig
{
    public function getName(): string;

    /**
     * Returns the field type used to construct the field.
     */
    public function getType(): ResolvedFieldType;

    /**
     * Returns whether value-type $type is accepted by the field.
     *
     * @param class-string<ValueHolder> $type
     */
    public function supportValueType(string $type): bool;

    /**
     * Sets whether value-type $type is accepted by the field.
     *
     * @param class-string<ValueHolder> $type
     */
    public function setValueTypeSupport(string $type, bool $enabled);

    /**
     * Set a {@link ValueComparator} used for validating specific value types.
     *
     * The range and compare value-types require a ValueComparator to be set.
     */
    public function setValueComparator(ValueComparator $comparator);

    public function getValueComparator(): ?ValueComparator;

    /**
     * Sets a view transformer for the field.
     *
     * * The reverseTransform method of the transformer is used to convert
     *   data from the model format to the view format.
     *
     * * The transform method of the transformer is used to convert from the
     *   view to the model format.
     *
     * The view format is a user-friendly representation, mostly localized
     * depending on the configuration of the field. For a date field,
     * the view format is for example either 'd-m-Y' or 'm-d-Y', depending on the locale.
     *
     * @param DataTransformer|null $viewTransformer Use null to remove the transformer
     */
    public function setViewTransformer(?DataTransformer $viewTransformer = null);

    /**
     * Returns the view transformer of the field.
     */
    public function getViewTransformer(): ?DataTransformer;

    /**
     * Sets a normalize transformer for the field.
     *
     * * The transform method of the transformer is used to convert data from the
     *   normalized format to the model format.
     *
     * * The reverseTransform method of the transformer is used to convert from the
     *   model format to the normalized format.
     *
     * The normalized format is a normalized representation of the data,
     * used for input provided from a query-string or API. This format is always
     * the same regardless of the locale.
     *
     * For a date field, the normalized format is for example always in
     * the 'Y-m-D' format.
     *
     * @param DataTransformer|null $viewTransformer Use null to remove the transformer
     */
    public function setNormTransformer(?DataTransformer $viewTransformer = null);

    /**
     * Returns the normalized-value transformer of the field.
     */
    public function getNormTransformer(): ?DataTransformer;

    /**
     * Returns whether the field's data is locked.
     *
     * A locked field is not allowed to be modified.
     */
    public function isConfigLocked(): bool;

    /**
     * Returns all options passed during the construction of the field.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    public function hasOption(string $name): bool;

    /**
     * Returns the value of a specific option.
     */
    public function getOption(string $name, $default = null);

    /**
     * Returns a new SearchFieldView for the SearchField.
     */
    public function createView(FieldSetView $fieldSet): SearchFieldView;

    /**
     * Sets the value for an attribute.
     */
    public function setAttribute(string $name, $value);

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes);

    /**
     * Returns additional attributes of the field.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    public function hasAttribute(string $name): bool;

    /**
     * @param mixed $default The value returned if the attribute does not exist
     */
    public function getAttribute(string $name, $default = null);
}
