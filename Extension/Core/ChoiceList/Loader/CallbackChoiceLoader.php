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

namespace Rollerworks\Component\Search\Extension\Core\ChoiceList\Loader;

use Rollerworks\Component\Search\Extension\Core\ChoiceList\ArrayChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceLoaderTrait;

/**
 * Loads an {@link ArrayChoiceList} instance from a callable returning an array of choices.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoader implements ChoiceLoader
{
    use ChoiceLoaderTrait;

    /** @var callable */
    private mixed $callback;

    /**
     * @param callable $callback          The callable returning an array of choices
     * @param bool     $valuesAreConstant Indicate whether values are constant
     *                                    (not dependent of there position)
     */
    public function __construct(
        callable $callback,
        private bool $valuesAreConstant = false,
    ) {
        $this->callback = $callback;
    }

    public function loadChoiceList(?callable $value = null): ChoiceList
    {
        if ($this->choiceList !== null) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(($this->callback)(), $value);
    }

    public function isValuesConstant(): bool
    {
        return $this->valuesAreConstant;
    }
}
