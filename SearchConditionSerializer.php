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

/**
 * SearchConditionSerializer, serializes a search condition for persistent storage.
 *
 * In practice this serializes the root ValuesGroup and of the condition
 * and bundles it with the FieldSet-name for further unserializing.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SearchConditionSerializer
{
    public function __construct(
        private readonly SearchFactory $searchFactory,
    ) {
    }

    /**
     * Serialize a SearchCondition.
     *
     * The returned value is an array you can safely serialize yourself.
     * This is not done already because storing a serialized SearchCondition
     * in a php session would serialize the serialized result again.
     *
     * Caution: The FieldSet must be loadable from the SearchFactory.
     *
     * @return array{0: string, 1: string} ['FieldSet-name', 'serialized ValuesGroup object']
     */
    public function serialize(SearchCondition $searchCondition): array
    {
        $setName = $searchCondition->getFieldSet()->getSetName();

        return [$setName, serialize($searchCondition->getValuesGroup())];
    }

    /**
     * Unserialize a serialized SearchCondition.
     *
     * @param array{0: string, 1: string} $searchCondition [FieldSet-name, serialized ValuesGroup object]
     *
     * @throws InvalidArgumentException when serialized SearchCondition is invalid
     *                                  (invalid structure or failed to unserialize)
     */
    public function unserialize(array $searchCondition): SearchCondition
    {
        if (\count($searchCondition) !== 2 || ! isset($searchCondition[0], $searchCondition[1])) {
            throw new InvalidArgumentException(
                'Serialized search condition must be exactly two values ["FieldSet-name", "serialized ValuesGroup"].'
            );
        }

        $fieldSet = $this->searchFactory->createFieldSet($searchCondition[0]);

        set_error_handler(static function (int $errNo, string $errstr, string $errFile, int $errLine): void {
            throw new InvalidArgumentException('Unable to unserialize invalid value.', $errNo, new \ErrorException($errstr, $errNo, $errNo, $errFile, $errLine));
        });

        try {
            return new SearchCondition($fieldSet, unserialize($searchCondition[1], ['allowed_classes' => true]));
        } finally {
            restore_error_handler();
        }
    }
}
