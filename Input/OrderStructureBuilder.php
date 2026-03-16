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

namespace Rollerworks\Component\Search\Input;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\DataTransformer;
use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Exception\OrderStructureException;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\FieldSet;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\StructureBuilder;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 */
final class OrderStructureBuilder implements StructureBuilder
{
    private readonly FieldSet $fieldSet;
    private readonly ValuesGroup $valuesGroup;
    private ?FieldConfig $fieldConfig = null;
    private ?ValuesBag $valuesBag = null;
    private ?DataTransformer $inputTransformer;

    /** @var array<string, mixed> */
    private array $order = [];

    public function __construct(
        ProcessorConfig $config,
        private readonly Validator $validator,
        private ErrorList $errorList,
        private readonly string $path = 'order',
        private readonly bool $viewFormat = false,
    ) {
        $this->fieldSet = $config->getFieldSet();
        $this->valuesGroup = new ValuesGroup();
    }

    public function getErrors(): ErrorList
    {
        return $this->errorList;
    }

    public function getCurrentPath(): string
    {
        return $this->path;
    }

    public function getRootGroup(): ValuesGroup
    {
        return $this->valuesGroup;
    }

    public function enterGroup(string $groupLocal = 'AND', string $path = '[%d]'): void
    {
        throw OrderStructureException::noGrouping();
    }

    public function leaveGroup(): void
    {
        throw OrderStructureException::noGrouping();
    }

    public function field(string $name, string $path): void
    {
        if (! $this->valuesGroup->hasField($name)) {
            $this->valuesGroup->addField($name, new ValuesBag());
        }

        $this->fieldConfig = $this->fieldSet->get($name);
        $this->inputTransformer = $this->viewFormat ? $this->fieldConfig->getViewTransformer() : $this->fieldConfig->getNormTransformer();

        $this->valuesBag = $this->valuesGroup->getField($name);

        $this->validator->initializeContext($this->fieldConfig, $this->errorList);
    }

    public function simpleValue(mixed $value, string $path): void
    {
        if ($this->valuesBag === null) {
            throw new \LogicException('Cannot add value to unknown bag.');
        }

        $name = $this->fieldConfig->getName();

        if ($this->valuesBag->count()) {
            throw OrderStructureException::invalidValue($name);
        }

        $path = str_replace('{pos}', $name, $path);
        $modelVal = $this->inputToNorm($value, $path);

        if ($modelVal !== null) {
            $this->validator->validate($modelVal, 'simple', $value, $path);
        }

        $this->valuesBag->addSimpleValue($modelVal);
        $this->order[$name] = $modelVal;
    }

    public function excludedSimpleValue(mixed $value, string $path): void
    {
        throw OrderStructureException::invalidValue($this->fieldConfig->getName());
    }

    public function rangeValue(mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, array $path): void
    {
        throw OrderStructureException::invalidValue($this->fieldConfig->getName());
    }

    public function excludedRangeValue(mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, array $path): void
    {
        throw OrderStructureException::invalidValue($this->fieldConfig->getName());
    }

    public function comparisonValue(mixed $operator, mixed $value, array $path): void
    {
        throw OrderStructureException::invalidValue($this->fieldConfig->getName());
    }

    public function patterMatchValue(mixed $type, mixed $value, bool $caseInsensitive, array $path): void
    {
        throw OrderStructureException::invalidValue($this->fieldConfig->getName());
    }

    public function endValues(): void
    {
        $this->fieldConfig = null;
        $this->valuesBag = null;
    }

    public function getOrder(): ?SearchOrder
    {
        return $this->order === [] ? null : new SearchOrder($this->order);
    }

    private function addError(ConditionErrorMessage $error): void
    {
        $this->errorList[] = $error;
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @return mixed returns null when the value is empty or invalid.
     *               Note: When the value is invalid an error is registered
     */
    private function inputToNorm(mixed $value, string $path): mixed
    {
        if ($this->inputTransformer !== null) {
            try {
                return $this->inputTransformer->reverseTransform($value);
            } catch (TransformationFailedException $e) {
                $this->addError($this->transformationExceptionToError($e, $path));

                return null;
            }
        }

        if ($value !== null && ! \is_scalar($value)) {
            $e = new \RuntimeException(
                \sprintf(
                    'Norm value of type %s is not a scalar value or null and not cannot be ' .
                    'converted to a string. You must set a NormTransformer for field "%s" with type "%s".',
                    \gettype($value),
                    $this->fieldConfig->getName(),
                    $this->fieldConfig->getType()->getInnerType()::class
                )
            );

            $error = new ConditionErrorMessage(
                $path,
                $this->fieldConfig->getOption('invalid_message', $e->getMessage()),
                $this->fieldConfig->getOption('invalid_message', $e->getMessage()),
                $this->fieldConfig->getOption('invalid_message_parameters', []),
                null,
                $e
            );

            $this->addError($error);

            return null;
        }

        return $value === '' ? null : $value;
    }

    private function transformationExceptionToError($e, string $path): ConditionErrorMessage
    {
        $invalidMessage = $e->getInvalidMessage();

        if ($invalidMessage !== null) {
            $error = new ConditionErrorMessage(
                $path,
                $invalidMessage,
                $invalidMessage,
                $e->getInvalidMessageParameters(),
                null,
                $e
            );
        } else {
            $error = new ConditionErrorMessage(
                $path,
                $this->fieldConfig->getOption('invalid_message', $e->getMessage()),
                $this->fieldConfig->getOption('invalid_message', $e->getMessage()),
                $this->fieldConfig->getOption('invalid_message_parameters', []),
                null,
                $e
            );
        }

        return $error;
    }
}
