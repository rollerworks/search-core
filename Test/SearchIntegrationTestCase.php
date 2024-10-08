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

namespace Rollerworks\Component\Search\Test;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Rollerworks\Component\Search\Exception\SearchException;
use Rollerworks\Component\Search\Extension\Core\Type\IntegerType;
use Rollerworks\Component\Search\Extension\Core\Type\TextType;
use Rollerworks\Component\Search\FieldSet;
use Rollerworks\Component\Search\GenericFieldSetBuilder;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\Searches;
use Rollerworks\Component\Search\SearchFactory;
use Rollerworks\Component\Search\SearchFactoryBuilder;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
abstract class SearchIntegrationTestCase extends TestCase
{
    use ProphecyTrait;

    /**
     * @var SearchFactoryBuilder|null
     */
    protected $factoryBuilder;

    /**
     * @var SearchFactory|null
     */
    private $searchFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factoryBuilder = Searches::createSearchFactoryBuilder();
    }

    protected function getFactory(): SearchFactory
    {
        if ($this->searchFactory === null) {
            $this->factoryBuilder->addExtensions($this->getExtensions());
            $this->factoryBuilder->addTypes($this->getTypes());
            $this->factoryBuilder->addTypeExtensions($this->getTypeExtensions());

            $this->searchFactory = $this->factoryBuilder->getSearchFactory();
        }

        return $this->searchFactory;
    }

    protected function getExtensions(): array
    {
        return [];
    }

    protected function getTypes(): array
    {
        return [];
    }

    protected function getTypeExtensions(): array
    {
        return [];
    }

    /**
     * @return FieldSet|GenericFieldSetBuilder
     */
    protected function getFieldSet(bool $build = true) // XXX This should be split into two separate methods, and moved to a Trait
    {
        $fieldSet = new GenericFieldSetBuilder($this->getFactory());
        $fieldSet->set($this->getFactory()->createField('id', IntegerType::class));
        $fieldSet->add('name', TextType::class);
        $fieldSet->add('restrict', TextType::class);

        return $build ? $fieldSet->getFieldSet() : $fieldSet;
    }

    protected static function assertConditionsEquals(SearchCondition $expectedCondition, SearchCondition $actualCondition): void
    {
        try {
            // First try the "simple" method, it's possible this fails due to index mismatches.
            self::assertEquals($expectedCondition, $actualCondition);
        } catch (\Exception $e) {
            // No need for custom implementations here.
            // The reindexValuesGroup can be used for custom implementations (when needed).
            $actualCondition = new SearchCondition(
                $actualCondition->getFieldSet(),
                self::reindexValuesGroup($actualCondition->getValuesGroup())
            );

            self::assertEquals($expectedCondition, $actualCondition);
        }
    }

    /**
     * @param ConditionErrorMessage[] $errors
     */
    protected function assertConditionContainsErrorsWithoutCause($input, ProcessorConfig $config, array $errors, ?InputProcessor $processor = null): void
    {
        if (! $processor) {
            if (! method_exists($this, 'getProcessor')) {
                throw new \InvalidArgumentException('When $processor is not provided, the getProcessor() method should be defined.');
            }

            $processor = $this->getProcessor();
        }

        try {
            $processor->process($config, $input);

            self::fail('Condition should be invalid.');
        } catch (InvalidSearchConditionException $e) {
            $errorsList = $e->getErrors();

            foreach ($errorsList as $error) {
                // Remove cause to make assertion possible.
                $error->cause = null;
            }

            foreach ($errors as $error) {
                $error->cause = null;
            }

            self::assertEquals($errors, $errorsList);
        }
    }

    /**
     * @param ConditionErrorMessage[] $errors
     */
    protected function assertConditionContainsErrors($input, ProcessorConfig $config, array $errors, ?InputProcessor $processor = null): void
    {
        if (! $processor) {
            if (! method_exists($this, 'getProcessor')) {
                throw new \InvalidArgumentException('When $processor is not provided, the getProcessor() method should be defined.');
            }

            $processor = $this->getProcessor();
        }

        try {
            $processor->process($config, $input);

            self::fail('Condition should be invalid: ' . var_export($input, true));
        } catch (InvalidSearchConditionException $e) {
            self::assertEquals($errors, $e->getErrors());
        }
    }

    protected static function reindexValuesGroup(ValuesGroup $valuesGroup): ValuesGroup
    {
        $newValuesGroup = new ValuesGroup($valuesGroup->getGroupLogical());

        foreach ($valuesGroup->getGroups() as $group) {
            $newValuesGroup->addGroup(self::reindexValuesGroup($group));
        }

        foreach ($valuesGroup->getFields() as $name => $valuesBag) {
            $newValuesBag = new ValuesBag();

            foreach ($valuesBag->getSimpleValues() as $value) {
                $newValuesBag->addSimpleValue($value);
            }

            foreach ($valuesBag->getExcludedSimpleValues() as $value) {
                $newValuesBag->addExcludedSimpleValue($value);
            }

            // use array_merge to renumber indexes and prevent mismatches.
            foreach ($valuesBag->all() as $type => $values) {
                foreach (array_merge([], $values) as $value) {
                    $newValuesBag->add($value);
                }
            }

            $newValuesGroup->addField($name, $newValuesBag);
        }

        return $newValuesGroup;
    }

    protected function assertConditionEquals($input, SearchCondition $condition, InputProcessor $processor, ProcessorConfig $config): void
    {
        try {
            self::assertEquals($condition, $processor->process($config, $input));
        } catch (\Exception $e) {
            if (\function_exists('dump')) {
                dump($e);
            } else {
                echo 'Please install symfony/var-dumper as dev-requirement to get a readable structure.' . \PHP_EOL;

                // Don't use var-dump or print-r as this crashes php...
                echo $e::class . '::' . (string) $e;
            }

            self::fail('Condition contains errors.');
        }
    }

    /**
     * @deprecated use a proper catch type instead
     */
    protected static function detectSystemException(\Exception $exception): void
    {
        if (! $exception instanceof SearchException) {
            throw $exception;
        }
    }
}
