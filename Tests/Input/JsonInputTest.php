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

namespace Rollerworks\Component\Search\Tests\Input;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Rollerworks\Component\Search\Input\JsonInput;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\PatternMatch;
use Rollerworks\Component\Search\Value\Range;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class JsonInputTest extends InputProcessorTestCase
{
    protected function getProcessor(): InputProcessor
    {
        return new JsonInput();
    }

    /**
     * @test
     */
    public function it_errors_on_invalid_json(): void
    {
        $config = new ProcessorConfig($this->getFieldSet());
        $error = ConditionErrorMessage::rawMessage(
            '{]',
            "Input does not contain valid JSON: \nState mismatch (invalid or malformed JSON)"
        );

        $this->assertConditionContainsErrorsWithoutCause('{]', $config, [$error]);
    }

    /**
     * @test
     *
     * @dataProvider provide_invalid_structures
     */
    public function it_validates_structure(array $structure, array $errors): void
    {
        $this->assertConditionContainsErrorsWithoutCause(
            json_encode($structure, \JSON_THROW_ON_ERROR),
            new ProcessorConfig($this->getFieldSet(order: true)),
            $errors
        );
    }

    public static function provide_invalid_structures(): iterable
    {
        yield 'Unknown key at root level' => [
            [
                'date' => [],
            ],
            [new ConditionErrorMessage('', 'Unexpected key "date" found in structure, expected only: "fields", "groups", "order", "logical-case".')],
        ];

        yield 'Unknown key at field level' => [
            [
                'fields' => [
                    'date' => [
                        'values' => ['1'],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date]', 'Unexpected key "values" found in structure, expected only: "simple-values", "excluded-simple-values", "ranges", "excluded-ranges", "comparisons", "pattern-matchers".')],
        ];

        yield 'Wrong key at field level' => [
            [
                'fields' => [
                    'date' => [
                        '1',
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date]', 'Expected structure to be an array.')],
        ];

        yield 'Unknown key at nested level' => [
            [
                'groups' => [
                    'date' => [],
                ],
            ],
            [new ConditionErrorMessage('[groups]', 'Expected structure to be an array, got "object" instead.')],
        ];

        yield 'Unexpected value for single-values' => [
            [
                'fields' => [
                    'date' => [
                        'simple-values' => ['first' => '2014-12-16'],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][simple-values]', 'Expected structure to be an array, got "object" instead.')],
        ];

        yield 'Unexpected value for excluded-single-values' => [
            [
                'fields' => [
                    'date' => [
                        'excluded-simple-values' => ['first' => '2014-12-16'],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][excluded-simple-values]', 'Expected structure to be an array, got "object" instead.')],
        ];

        yield 'Unexpected value for ranges' => [
            [
                'fields' => [
                    'date' => [
                        'ranges' => [['lower' => '2014-12-16', 'upper' => '2014-12-18', 'bounded' => 'first']],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][ranges][0]', 'Unexpected key "bounded" found in structure, expected only: "lower", "upper", "inclusive-lower", "inclusive-upper".')],
        ];

        yield 'Unexpected bound-type for ranges' => [
            [
                'fields' => [
                    'date' => [
                        'ranges' => [['lower' => '2014-12-16', 'upper' => '2014-12-18', 'inclusive-lower' => 'no']],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][ranges][0][inclusive-lower]', 'Expected value to be a bool, got "string" instead.')],
        ];

        yield 'Missing lower-bound for ranges' => [
            [
                'fields' => [
                    'date' => [
                        'ranges' => [['upper' => '2014-12-18']],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][ranges][0]', 'Expected value-structure to contain the following keys: "lower", "upper". But the following keys are missing: "lower".')],
        ];

        yield 'Missing type for comparison' => [
            [
                'fields' => [
                    'date' => [
                        'comparisons' => [['upper' => '2014-12-18']],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][date][comparisons][0]', 'Expected value-structure to contain the following keys: "value", "operator". But the following keys are missing: "value", "operator".')],
        ];

        yield 'Missing type for pattern-matchers' => [
            [
                'fields' => [
                    'name' => [
                        'pattern-matchers' => [['value' => '2014-12-18']],
                    ],
                ],
            ],
            [new ConditionErrorMessage('[fields][name][pattern-matchers][0]', 'Expected value-structure to contain the following keys: "value", "type". But the following keys are missing: "type".')],
        ];
    }

    public static function provideEmptyInputTests(): iterable
    {
        return [
            [''],
            ['{}'],
        ];
    }

    public static function provideSingleValuePairTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2', '٤٤٤٦٥٤٦٠٠', '30', '30L'],
                                'excluded-simple-values' => ['value3'],
                            ],
                        ],
                        'order' => [
                            'date' => 'uP',
                        ],
                    ]
                ),
                ['@date' => 'ASC'],
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2', '٤٤٤٦٥٤٦٠٠', '30', '30L'],
                                'excluded-simple-values' => ['value3'],
                            ],
                        ],
                    ]
                ),
                ['@date' => 'DESC', '@id' => 'ASC'],
            ],
            [
                json_encode(
                    []
                ),
                ['@date' => 'DESC', '@id' => 'ASC'],
            ],
        ];
    }

    public static function provideMultipleValues(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                            'date' => [
                                'simple-values' => ['2014-12-16T00:00:00Z'],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideRangeValues(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'ranges' => [
                                    ['lower' => 1, 'upper' => 10],
                                    ['lower' => 15, 'upper' => 30],
                                    ['lower' => 100, 'upper' => 200, 'inclusive-lower' => false],
                                    ['lower' => 310, 'upper' => 400, 'inclusive-upper' => false],
                                ],
                                'excluded-ranges' => [
                                    ['lower' => 50, 'upper' => 70, 'inclusive-lower' => true],
                                ],
                            ],
                            'date' => [
                                'ranges' => [
                                    ['lower' => '2014-12-16T00:00:00Z', 'upper' => '2014-12-20T00:00:00Z'],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideComparisonValues(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'comparisons' => [
                                    ['value' => 1, 'operator' => '>'],
                                    ['value' => 2, 'operator' => '<'],
                                    ['value' => 5, 'operator' => '<='],
                                    ['value' => 8, 'operator' => '>='],
                                    ['value' => 20, 'operator' => '<>'],
                                ],
                            ],
                            'date' => [
                                'comparisons' => [
                                    ['value' => '2014-12-16T00:00:00Z', 'operator' => '>='],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideMatcherValues(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'pattern-matchers' => [
                                    ['value' => 'value', 'type' => 'CONTAINS'],
                                    ['value' => 'value2', 'type' => 'STARTS_WITH', 'case-insensitive' => true],
                                    ['value' => 'value3', 'type' => 'ENDS_WITH'],
                                    ['value' => 'value4', 'type' => 'NOT_CONTAINS'],
                                    ['value' => 'value5', 'type' => 'NOT_CONTAINS', 'case-insensitive' => true],
                                    ['value' => 'value9', 'type' => 'EQUALS'],
                                    ['value' => 'value10', 'type' => 'NOT_EQUALS'],
                                    ['value' => 'value11', 'type' => 'EQUALS', 'case-insensitive' => true],
                                    ['value' => 'value12', 'type' => 'NOT_EQUALS', 'case-insensitive' => true],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideGroupTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                        ],
                        'groups' => [
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value3', 'value4'],
                                    ],
                                ],
                            ],
                            [
                                'logical-case' => 'OR',
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value8', 'value10'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideRootLogicalTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                        ],
                    ]
                ),
            ],
            [
                json_encode(
                    [
                        'logical-case' => 'AND',
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                        ],
                    ]
                ),
            ],
            [
                json_encode(
                    [
                        'logical-case' => 'OR',
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                        ],
                    ]
                ),
                ValuesGroup::GROUP_LOGICAL_OR,
            ],
        ];
    }

    public static function provideMultipleSubGroupTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2'],
                                    ],
                                ],
                            ],
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value3', 'value4'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideNestedGroupTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'fields' => [
                                            'name' => [
                                                'simple-values' => ['value', 'value2'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideValueOverflowTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['value', 'value2', 'value3', 'value4'],
                            ],
                        ],
                    ]
                ),
                'name',
                '[fields][name][simple-values][3]',
            ],
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2', 'value3', 'value4'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                'name',
                '[groups][0][fields][name][simple-values][3]',
            ],
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'fields' => [
                                            'name' => [
                                                'simple-values' => ['value', 'value2', 'value3', 'value4'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                'name',
                '[groups][0][groups][0][fields][name][simple-values][3]',
            ],
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'fields' => [
                                            'name' => [
                                                'simple-values' => ['value', 'value2'],
                                            ],
                                        ],
                                    ],
                                    [
                                        'fields' => [
                                            'name' => [
                                                'simple-values' => ['value', 'value2', 'value3', 'value4'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                'name',
                '[groups][0][groups][1][fields][name][simple-values][3]',
            ],
        ];
    }

    public static function provideGroupsOverflowTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2'],
                                    ],
                                ],
                            ],
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2'],
                                    ],
                                ],
                            ],
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2'],
                                    ],
                                ],
                            ],
                            [
                                'fields' => [
                                    'name' => [
                                        'simple-values' => ['value', 'value2'],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                '[groups][3]',
            ],
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'groups' => [
                                            [
                                                'fields' => [
                                                    'name' => [
                                                        'simple-values' => ['value', 'value2'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'groups' => [
                                            [
                                                'fields' => [
                                                    'name' => [
                                                        'simple-values' => ['value', 'value2'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'fields' => [
                                                    'name' => [
                                                        'simple-values' => ['value', 'value2'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'fields' => [
                                                    'name' => [
                                                        'simple-values' => ['value', 'value2'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'fields' => [
                                                    'name' => [
                                                        'simple-values' => ['value', 'value2'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                '[groups][0][groups][1][groups][3]',
            ],
        ];
    }

    public static function provideNestingLevelExceededTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'fields' => [
                                            'name' => [
                                                'simple-values' => ['value', 'value2'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                '[groups][0][groups][0]',
            ],
        ];
    }

    public static function providePrivateFieldTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            '_id' => [
                                'simple-values' => [1, 2],
                            ],
                        ],
                    ]
                ),
                '_id',
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'simple-values' => [1, 2],
                            ],
                            '_id' => [
                                'simple-values' => [1, 2],
                            ],
                        ],
                    ]
                ),
                '_id',
            ],
        ];
    }

    public static function provideUnknownFieldTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'field2' => [
                                'simple-values' => ['value', 'value2'],
                            ],
                        ],
                    ]
                ),
            ],
        ];
    }

    public static function provideUnsupportedValueTypeExceptionTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'no-range-field' => [
                                'ranges' => [['lower' => 10, 'upper' => 20]],
                            ],
                        ],
                    ]
                ),
                'no-range-field',
                Range::class,
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'no-range-field' => [
                                'excluded-ranges' => [['lower' => 10, 'upper' => 20]],
                            ],
                        ],
                    ]
                ),
                'no-range-field',
                Range::class,
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'no-compares-field' => [
                                'comparisons' => [['value' => 10, 'operator' => '>']],
                            ],
                        ],
                    ]
                ),
                'no-compares-field',
                Compare::class,
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'no-matchers-field' => [
                                'pattern-matchers' => [['value' => 'foo', 'type' => 'CONTAINS']],
                            ],
                        ],
                    ]
                ),
                'no-matchers-field',
                PatternMatch::class,
            ],
        ];
    }

    /**
     * @return array[]
     */
    public static function provideInvalidRangeTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'ranges' => [
                                    ['lower' => 30, 'upper' => 10],
                                    ['lower' => 50, 'upper' => 60],
                                    ['lower' => 40, 'upper' => 20],
                                ],
                            ],
                        ],
                    ]
                ),
                ['[fields][id][ranges][0]', '[fields][id][ranges][2]'],
            ],
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'excluded-ranges' => [
                                    ['lower' => 30, 'upper' => 10],
                                    ['lower' => 50, 'upper' => 60],
                                    ['lower' => 40, 'upper' => 20],
                                ],
                            ],
                        ],
                    ]
                ),
                ['[fields][id][excluded-ranges][0]', '[fields][id][excluded-ranges][2]'],
            ],
        ];
    }

    public static function provideInvalidValueTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'id' => [
                                'simple-values' => ['foo', '30', 'bar'],
                                'comparisons' => [['operator' => '>', 'value' => 'life']],
                            ],
                        ],
                    ]
                ),
                [
                    new ConditionErrorMessage('[fields][id][simple-values][0]', 'This value is not valid.'),
                    new ConditionErrorMessage('[fields][id][simple-values][2]', 'This value is not valid.'),
                    new ConditionErrorMessage('[fields][id][comparisons][0][value]', 'This value is not valid.'),
                ],
            ],
        ];
    }

    public static function provideInvalidWithMessageValueTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'fields' => [
                            'name' => [
                                'simple-values' => ['foo', '30'],
                            ],
                        ],
                    ]
                ),
                [
                    new ConditionErrorMessage('[fields][name][simple-values][0]', 'I explicitly refuse the accept this value.', 'I explicitly refuse the accept this value.', ['value' => 'foo']),
                    new ConditionErrorMessage('[fields][name][simple-values][1]', 'I explicitly refuse the accept this value.', 'I explicitly refuse the accept this value.', ['value' => '30']),
                ],
            ],
        ];
    }

    public static function provideNestedErrorsTests(): iterable
    {
        return [
            [
                json_encode(
                    [
                        'groups' => [
                            [
                                'groups' => [
                                    [
                                        'fields' => [
                                            'date' => [
                                                'simple-values' => ['value'],
                                            ],
                                        ],
                                    ],
                                    [
                                        'fields' => [
                                            'date' => [
                                                'simple-values' => ['value', 'value2'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]
                ),
                [
                    new ConditionErrorMessage('[groups][0][groups][0][fields][date][simple-values][0]', 'This value is not valid.'),
                    new ConditionErrorMessage('[groups][0][groups][1][fields][date][simple-values][0]', 'This value is not valid.'),
                    new ConditionErrorMessage('[groups][0][groups][1][fields][date][simple-values][1]', 'This value is not valid.'),
                ],
            ],
        ];
    }

    protected function onNotSuccessfulTest(\Throwable $t): never
    {
        if ($t instanceof InvalidSearchConditionException) {
            echo 'Error: ' . $t->getMessage() . \PHP_EOL;

            foreach ($t->getErrors() as $error) {
                echo \sprintf('- %s: %s', $error->path, $error->message);

                if ($error->cause instanceof \Exception) {
                    echo ' Cause: ' . $error->cause->getMessage();
                }

                echo \PHP_EOL;
            }
        }

        parent::onNotSuccessfulTest($t);
    }
}
