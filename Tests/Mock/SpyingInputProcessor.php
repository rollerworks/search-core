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

namespace Rollerworks\Component\Search\Tests\Mock;

use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class SpyingInputProcessor implements InputProcessor
{
    private ?ProcessorConfig $config = null;
    private mixed $input = null;

    public static function getCondition(): SearchCondition
    {
        $valuesGroup = new ValuesGroup(ValuesGroup::GROUP_LOGICAL_OR);
        $valuesGroup->addField('id', (new ValuesBag())->addSimpleValue('1'));

        return new SearchCondition(new FieldSetStub(), $valuesGroup);
    }

    public function process(ProcessorConfig $config, mixed $input): SearchCondition
    {
        $this->config = $config;
        $this->input = $input;

        return self::getCondition();
    }

    public function getConfig(): ?ProcessorConfig
    {
        return $this->config;
    }

    public function getInput(): mixed
    {
        return $this->input;
    }
}
