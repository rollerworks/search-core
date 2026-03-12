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

use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * Works as a wrapper around the ValuesGroup, and ValuesBag transforming
 * input while ensuring restrictions are honored.
 *
 * @psalm-type ErrorPath = array{0: string, 1: string, 2: string}
 *
 * @internal
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
interface StructureBuilder
{
    public function getErrors(): ErrorList;

    /**
     * @return string|string[]
     */
    public function getCurrentPath(): string | array;

    public function getRootGroup(): ValuesGroup;

    public function enterGroup(string $groupLocal = 'AND', string $path = '[%d]'): void;

    public function leaveGroup(): void;

    public function field(string $name, string $path): void;

    public function simpleValue(mixed $value, string $path): void;

    public function excludedSimpleValue(mixed $value, string $path): void;

    /**
     * @param ErrorPath $path [path, lower-path-pattern, upper-path-pattern]
     */
    public function rangeValue(mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, array $path): void;

    /**
     * @param ErrorPath $path [path, lower-path-pattern, upper-path-pattern]
     */
    public function excludedRangeValue(mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, array $path): void;

    /**
     * @param ErrorPath $path [base-path, operator-path, value-path]
     */
    public function comparisonValue(mixed $operator, mixed $value, array $path): void;

    /**
     * @param ErrorPath $path [base-path, value-path, type-path]
     */
    public function patterMatchValue(mixed $type, mixed $value, bool $caseInsensitive, array $path): void;

    public function endValues();
}
